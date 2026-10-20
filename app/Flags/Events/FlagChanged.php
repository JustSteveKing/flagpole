<?php

declare(strict_types=1);

namespace App\Flags\Events;

use App\Models\Flag;
use App\Models\FlagSetting;
use App\Webhooks\WebhookEvent;

/**
 * The webhook events a change to a flag sends (RFC-0007). They say what changed and not what it
 * changed to: the full document is one request away for a subscriber with flags:read, and keeping
 * rules out of the payload keeps targeting data out of third-party endpoints. Fields can be added
 * here and never removed, because subscribers parse them.
 */
final readonly class FlagChanged implements WebhookEvent
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(
        private string $type,
        private string $subject,
        private array $data,
    ) {}

    public static function created(Flag $flag): self
    {
        return self::about('flag.created', $flag, ['name' => $flag->name, 'type' => $flag->type->value]);
    }

    /**
     * @param  list<string>  $changed
     */
    public static function updated(Flag $flag, array $changed): self
    {
        return self::about('flag.updated', $flag, ['changed' => $changed]);
    }

    public static function archived(Flag $flag): self
    {
        return self::about('flag.archived', $flag, []);
    }

    public static function restored(Flag $flag): self
    {
        return self::about('flag.restored', $flag, []);
    }

    /**
     * @param  list<string>  $changed
     */
    public static function settingsUpdated(FlagSetting $settings, array $changed): self
    {
        return self::about('flag.settings.updated', $settings->flag, [
            'environment' => $settings->environment->key,
            'enabled' => $settings->enabled,
            'changed' => $changed,
        ]);
    }

    public function type(): string
    {
        return $this->type;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function about(string $type, Flag $flag, array $data): self
    {
        $project = $flag->project->key;

        return new self($type, "{$project}/{$flag->key}", ['project' => $project, 'flag' => $flag->key, ...$data]);
    }
}
