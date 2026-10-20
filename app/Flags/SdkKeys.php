<?php

declare(strict_types=1);

namespace App\Flags;

use App\Models\Environment;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\NewAccessToken;

/**
 * Issues SDK keys: Sanctum tokens owned by an environment, able to do one thing (ADR-0004). It sits
 * beside Kit's AccessTokens, which issues tokens for users only and always with an expiry, and
 * leaves that class untouched (ADR-0008).
 */
final class SdkKeys
{
    /**
     * Never in sanctum.abilities.available or default, so no user's token can hold it.
     */
    public const string ABILITY = 'ofrep:evaluate';

    /**
     * No expiry unless one is asked for: a key that expires on its own turns every flag in
     * production back to its code default without anyone changing anything (RFC-0001).
     */
    public function issue(Environment $environment, string $name, ?Carbon $expiresAt): NewAccessToken
    {
        return $environment->createToken($name, [self::ABILITY], $expiresAt);
    }
}
