<?php

declare(strict_types=1);

namespace App\Flags;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Who made a change, and with which token, for the flag's history (RFC-0007).
 */
final readonly class Actor
{
    public function __construct(
        public User $user,
        public ?PersonalAccessToken $token,
    ) {}

    public static function of(User $user): self
    {
        $token = $user->currentAccessToken();

        // Sanctum types the current token as always present, but a user loaded without one has none.
        return new self($user, $token instanceof PersonalAccessToken ? $token : null); // @phpstan-ignore instanceof.alwaysTrue
    }
}
