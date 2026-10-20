<?php

declare(strict_types=1);

namespace App\Flags;

use App\Flags\Events\FlagChanged;
use App\Models\Environment;
use App\Models\Flag;
use App\Models\FlagSetting;
use App\Models\Project;
use App\Webhooks\Webhooks;
use Illuminate\Support\Facades\DB;

/**
 * Every write to a flag goes through here, so each one does the same four things in one
 * transaction (RFC-0004): save the change; increment the version of every environment whose
 * results it can change, which is what the bulk evaluation ETag is built from (ADR-0003); record it
 * in the flag's history; and dispatch its webhook event, which Kit queues once the transaction
 * commits (RFC-0007). A write that changes nothing does none of them.
 *
 * The actor is who made the change. Without one, as when seeding, the project's owner is recorded
 * with no token.
 */
final readonly class FlagWriter
{
    public function __construct(
        private Webhooks $webhooks,
    ) {}

    /**
     * @param  list<array{name: string, value: mixed}>  $variants
     */
    public function create(Project $project, string $key, string $name, ?string $description, FlagType $type, array $variants, ?Actor $actor = null): Flag
    {
        return DB::transaction(function () use ($project, $key, $name, $description, $type, $variants, $actor): Flag {
            $flag = $project->flags()->create([
                'key' => $key,
                'name' => $name,
                'description' => $description,
                'type' => $type,
                'variants' => FlagType::Boolean === $type ? Flag::BOOLEAN_VARIANTS : $variants,
            ]);

            foreach ($project->environments as $environment) {
                $flag->settings()->create(['environment_id' => $environment->id, ...FlagSetting::startingStateFor($flag)]);
            }

            $this->bump(array_values($project->environments()->pluck('id')->all()));
            $this->record($flag, null, 'flag.created', null, $flag->document(), $actor);
            $this->webhooks->dispatch($project->user, FlagChanged::created($flag));

            return $flag;
        });
    }

    /**
     * @param  array{name?: string, description?: string|null, variants?: list<array{name: string, value: mixed}>}  $changes
     */
    public function update(Flag $flag, array $changes, ?Actor $actor = null): Flag
    {
        return DB::transaction(function () use ($flag, $changes, $actor): Flag {
            $before = $flag->document();
            $flag->fill($changes);

            if ( ! $flag->isDirty()) {
                return $flag;
            }

            $changed = array_values(array_intersect(['name', 'description', 'variants'], array_keys($flag->getDirty())));
            $flag->save();

            // Only the variants' values reach an evaluation. A name or description does not.
            if (in_array('variants', $changed, true)) {
                $this->bump($this->environmentIds($flag));
            }

            $this->record($flag, null, 'flag.updated', $before, $flag->document(), $actor);
            $this->webhooks->dispatch($flag->project->user, FlagChanged::updated($flag, $changed));

            return $flag;
        });
    }

    public function archive(Flag $flag, ?Actor $actor = null): Flag
    {
        return $this->setArchived($flag, archived: true, actor: $actor);
    }

    public function restore(Flag $flag, ?Actor $actor = null): Flag
    {
        return $this->setArchived($flag, archived: false, actor: $actor);
    }

    /**
     * @param  array{enabled: bool, off_variant: string, default: array<string, mixed>, rules: list<array<string, mixed>>}  $settings
     */
    public function updateSettings(FlagSetting $setting, array $settings, ?Actor $actor = null): FlagSetting
    {
        return DB::transaction(function () use ($setting, $settings, $actor): FlagSetting {
            $before = $setting->document();
            $setting->fill($settings);

            if ( ! $setting->isDirty()) {
                return $setting;
            }

            $changed = array_values(array_intersect(['enabled', 'off_variant', 'default', 'rules'], array_keys($setting->getDirty())));
            $setting->save();

            $this->bump([$setting->environment_id]);
            $this->record($setting->flag, $setting->environment_id, 'flag.settings.updated', $before, $setting->document(), $actor);
            $this->webhooks->dispatch($setting->flag->project->user, FlagChanged::settingsUpdated($setting, $changed));

            return $setting;
        });
    }

    /**
     * A new environment gets every flag in its project, archived ones included, so restoring a
     * flag later finds settings waiting for it (RFC-0003).
     */
    public function addEnvironment(Environment $environment): void
    {
        foreach ($environment->project->flags as $flag) {
            $flag->settings()->create(['environment_id' => $environment->id, ...FlagSetting::startingStateFor($flag)]);
        }
    }

    private function setArchived(Flag $flag, bool $archived, ?Actor $actor): Flag
    {
        if ($flag->isArchived() === $archived) {
            return $flag;
        }

        return DB::transaction(function () use ($flag, $archived, $actor): Flag {
            $before = $flag->document();
            $flag->archived_at = $archived ? now() : null;
            $flag->save();

            $this->bump($this->environmentIds($flag));
            $this->record($flag, null, $archived ? 'flag.archived' : 'flag.restored', $before, $flag->document(), $actor);
            $this->webhooks->dispatch($flag->project->user, $archived ? FlagChanged::archived($flag) : FlagChanged::restored($flag));

            return $flag;
        });
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    private function record(Flag $flag, ?string $environmentId, string $action, ?array $before, array $after, ?Actor $actor): void
    {
        $flag->changes()->create([
            'environment_id' => $environmentId,
            'action' => $action,
            'user_id' => $actor?->user->id ?? $flag->project->user_id,
            'token_id' => $actor?->token?->id,
            'token_name' => $actor?->token?->name,
            'before' => $before,
            'after' => $after,
        ]);
    }

    /**
     * @return list<string>
     */
    private function environmentIds(Flag $flag): array
    {
        /** @var list<string> */
        return $flag->settings()->pluck('environment_id')->all();
    }

    /**
     * Through the query builder, so neither the environment's revision nor its updated_at moves:
     * the environment's own representation has not changed, only what it evaluates to.
     *
     * @param  list<mixed>  $environmentIds
     */
    private function bump(array $environmentIds): void
    {
        if ([] === $environmentIds) {
            return;
        }

        Environment::query()->whereKey($environmentIds)->toBase()->increment('version');
    }
}
