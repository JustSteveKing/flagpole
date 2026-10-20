<?php

declare(strict_types=1);

namespace App\Flags;

use App\Models\Environment;
use App\Models\Flag;
use App\Models\FlagSetting;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Every write that can change what a flag evaluates to goes through here, so each one does the same
 * things in one transaction: save the change, and increment the version of every environment
 * whose results it can change, which is what the bulk evaluation ETag is built from (RFC-0004,
 * ADR-0003). A write that changes nothing does none of it.
 */
final readonly class FlagWriter
{
    /**
     * @param  list<array{name: string, value: mixed}>  $variants
     */
    public function create(Project $project, string $key, string $name, ?string $description, FlagType $type, array $variants): Flag
    {
        return DB::transaction(function () use ($project, $key, $name, $description, $type, $variants): Flag {
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

            return $flag;
        });
    }

    /**
     * @param  array{name?: string, description?: string|null, variants?: list<array{name: string, value: mixed}>}  $changes
     */
    public function update(Flag $flag, array $changes): Flag
    {
        return DB::transaction(function () use ($flag, $changes): Flag {
            $flag->fill($changes);

            if ( ! $flag->isDirty()) {
                return $flag;
            }

            // Only the variants' values reach an evaluation. A name or description does not.
            $affectsEvaluation = $flag->isDirty('variants');

            $flag->save();

            if ($affectsEvaluation) {
                $this->bump($this->environmentIds($flag));
            }

            return $flag;
        });
    }

    public function archive(Flag $flag): Flag
    {
        return $this->setArchived($flag, archived: true);
    }

    public function restore(Flag $flag): Flag
    {
        return $this->setArchived($flag, archived: false);
    }

    /**
     * @param  array{enabled: bool, off_variant: string, default: array<string, mixed>, rules: list<array<string, mixed>>}  $settings
     */
    public function updateSettings(FlagSetting $setting, array $settings): FlagSetting
    {
        return DB::transaction(function () use ($setting, $settings): FlagSetting {
            $setting->fill($settings);

            if ( ! $setting->isDirty()) {
                return $setting;
            }

            $setting->save();

            $this->bump([$setting->environment_id]);

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

    private function setArchived(Flag $flag, bool $archived): Flag
    {
        if ($flag->isArchived() === $archived) {
            return $flag;
        }

        return DB::transaction(function () use ($flag, $archived): Flag {
            $flag->archived_at = $archived ? now() : null;
            $flag->save();

            $this->bump($this->environmentIds($flag));

            return $flag;
        });
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
