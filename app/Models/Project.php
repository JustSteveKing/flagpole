<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasRevision;
use Database\Factories\ProjectFactory;
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
 * @property string $user_id
 * @property string $key
 * @property string $name
 * @property int $revision
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
final class Project extends Model implements HasValidators
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;
    use HasRevision;
    use HasUlids;

    /**
     * The environments every new project starts with, by key (RFC-0003).
     */
    public const array STARTING_ENVIRONMENTS = [
        'development' => 'Development',
        'production' => 'Production',
    ];

    /**
     * The database defaults, so a model fresh from create() has an ETag without a reload.
     *
     * @var array<string, int>
     */
    #[Override]
    protected $attributes = ['revision' => 1];

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = ['key', 'name'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Environment, $this>
     */
    public function environments(): HasMany
    {
        return $this->hasMany(Environment::class);
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
        ];
    }
}
