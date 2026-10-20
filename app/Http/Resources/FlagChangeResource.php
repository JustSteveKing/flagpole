<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FlagChange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class FlagChangeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FlagChange $change */
        $change = $this->resource;

        return [
            'id' => $change->id,
            'action' => $change->action,
            'environment' => $change->environment?->key,
            // There is no user field: a project has one owner, so it is always them (ADR-0005).
            'token' => null === $change->token_name ? null : [
                'id' => null === $change->token ? null : (string) $change->token->id,
                'name' => $change->token_name,
            ],
            'before' => $change->before,
            'after' => $change->after,
            'created_at' => $change->created_at->toAtomString(),
        ];
    }
}
