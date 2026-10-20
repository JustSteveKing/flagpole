<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Kit's PersonalAccessTokenResource without abilities, which are always ofrep:evaluate, and without
 * is_current, which a management token can never be (RFC-0006).
 */
final class SdkKeyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PersonalAccessToken $token */
        $token = $this->resource;

        return [
            'id' => (string) $token->id,
            'name' => $token->name,
            'last_used_at' => $token->last_used_at?->toAtomString(),
            'expires_at' => $token->expires_at?->toAtomString(),
            'created_at' => $token->created_at?->toAtomString(),
        ];
    }
}
