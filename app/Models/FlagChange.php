<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use Override;

/**
 * One change to a flag or its settings in one environment (RFC-0007).
 *
 * @property string $id
 * @property string $flag_id
 * @property string|null $environment_id
 * @property string $action
 * @property string $user_id
 * @property int|null $token_id
 * @property string|null $token_name
 * @property array<string, mixed>|null $before
 * @property array<string, mixed> $after
 * @property Carbon $created_at
 * @property-read Environment|null $environment
 * @property-read PersonalAccessToken|null $token
 */
final class FlagChange extends Model
{
    use HasUlids;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = ['environment_id', 'action', 'user_id', 'token_id', 'token_name', 'before', 'after'];

    /**
     * @return BelongsTo<Environment, $this>
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * Null once the token has been deleted; token_name keeps what it was called.
     *
     * @return BelongsTo<PersonalAccessToken, $this>
     */
    public function token(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'token_id');
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'token_id' => 'integer',
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
