<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasRevision;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use JustSteveKing\Toolbox\ConditionalRequests\Contracts\HasValidators;
use Override;

/**
 * A flag's settings in one environment (RFC-0004).
 *
 * @property string $id
 * @property string $flag_id
 * @property string $environment_id
 * @property bool $enabled
 * @property string $off_variant
 * @property array<string, mixed> $default
 * @property list<array<string, mixed>> $rules
 * @property int $revision
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Flag $flag
 * @property-read Environment $environment
 */
final class FlagSetting extends Model implements HasValidators
{
    use HasRevision;
    use HasUlids;

    /**
     * @var array<string, int|bool>
     */
    #[Override]
    protected $attributes = ['revision' => 1, 'enabled' => false];

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = ['environment_id', 'enabled', 'off_variant', 'default', 'rules'];

    /**
     * The settings a flag starts with in an environment: disabled, serving its starting variant,
     * with no rules.
     *
     * @return array{enabled: false, off_variant: string, default: array{variant: string}, rules: list<never>}
     */
    public static function startingStateFor(Flag $flag): array
    {
        $variant = $flag->startingVariant();

        return ['enabled' => false, 'off_variant' => $variant, 'default' => ['variant' => $variant], 'rules' => []];
    }

    /**
     * Every variant these settings name: the off variant, the default, and each rule's serve.
     *
     * @return list<string>
     */
    public function usedVariants(): array
    {
        $serves = [$this->default, ...array_map(static fn(array $rule): mixed => $rule['serve'] ?? null, $this->rules)];
        $names = [$this->off_variant];

        foreach ($serves as $serve) {
            if ( ! is_array($serve)) {
                continue;
            }

            if (is_string($serve['variant'] ?? null)) {
                $names[] = $serve['variant'];
            }

            foreach (is_array($serve['split'] ?? null) ? $serve['split'] : [] as $part) {
                if (is_array($part) && is_string($part['variant'] ?? null)) {
                    $names[] = $part['variant'];
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return BelongsTo<Flag, $this>
     */
    public function flag(): BelongsTo
    {
        return $this->belongsTo(Flag::class);
    }

    /**
     * @return BelongsTo<Environment, $this>
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'default' => 'array',
            'rules' => 'array',
            'revision' => 'integer',
        ];
    }
}
