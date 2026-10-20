<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasRevision;
use Database\Factories\EnvironmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use JustSteveKing\Toolbox\ConditionalRequests\Contracts\HasValidators;
use Laravel\Sanctum\HasApiTokens;
use Override;

/**
 * @property string $id
 * @property string $project_id
 * @property string $key
 * @property string $name
 * @property int $revision
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 */
final class Environment extends Model implements HasValidators
{
    use HasApiTokens;
    /** @use HasFactory<EnvironmentFactory> */
    use HasFactory;
    use HasRevision;
    use HasUlids;

    /**
     * The database defaults, so a model fresh from create() has an ETag without a reload.
     *
     * @var array<string, int>
     */
    #[Override]
    protected $attributes = ['revision' => 1, 'version' => 1];

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = ['key', 'name'];

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
    public function flagSettings(): HasMany
    {
        return $this->hasMany(FlagSetting::class);
    }

    /**
     * SDK keys are Sanctum tokens owned by an environment (ADR-0004). A morph relationship has no
     * foreign key, so nothing in the database removes them when the environment goes.
     */
    protected static function booted(): void
    {
        self::deleting(static function (Environment $environment): void {
            $environment->tokens()->delete();
        });
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'version' => 'integer',
        ];
    }
}
