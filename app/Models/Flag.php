<?php

declare(strict_types=1);

namespace App\Models;

use App\Flags\FlagType;
use App\Models\Concerns\HasRevision;
use Database\Factories\FlagFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use JustSteveKing\Toolbox\ConditionalRequests\Contracts\HasValidators;
use Override;

/**
 * @property string $id
 * @property string $project_id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property FlagType $type
 * @property list<array{name: string, value: mixed}> $variants
 * @property Carbon|null $archived_at
 * @property int $revision
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 */
final class Flag extends Model implements HasValidators
{
    /** @use HasFactory<FlagFactory> */
    use HasFactory;
    use HasRevision;
    use HasUlids;

    /**
     * A boolean flag always has exactly these (RFC-0004).
     */
    public const array BOOLEAN_VARIANTS = [
        ['name' => 'on', 'value' => true],
        ['name' => 'off', 'value' => false],
    ];

    /**
     * @var array<string, int>
     */
    #[Override]
    protected $attributes = ['revision' => 1];

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = ['key', 'name', 'description', 'type', 'variants'];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<FlagSetting, $this>
     */
    public function settings(): HasMany
    {
        return $this->hasMany(FlagSetting::class);
    }

    /**
     * @return HasMany<FlagChange, $this>
     */
    public function changes(): HasMany
    {
        return $this->hasMany(FlagChange::class);
    }

    /**
     * The flag as its history records it (RFC-0007).
     *
     * @return array<string, mixed>
     */
    public function document(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'variants' => $this->variants,
            'archived_at' => $this->archived_at?->toAtomString(),
        ];
    }

    public function isArchived(): bool
    {
        return null !== $this->archived_at;
    }

    /**
     * @return list<string>
     */
    public function variantNames(): array
    {
        return array_column($this->variants, 'name');
    }

    /**
     * What a new flag serves, and what it serves while disabled: off for a boolean flag, and the
     * first variant for the others (RFC-0004).
     */
    public function startingVariant(): string
    {
        return FlagType::Boolean === $this->type ? 'off' : $this->variantNames()[0];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'type' => FlagType::class,
            'variants' => 'array',
            'archived_at' => 'datetime',
            'revision' => 'integer',
        ];
    }
}
