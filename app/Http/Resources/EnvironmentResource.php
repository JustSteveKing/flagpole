<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Environment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class EnvironmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Environment $environment */
        $environment = $this->resource;

        return [
            'key' => $environment->key,
            'name' => $environment->name,
            'created_at' => $environment->created_at?->toAtomString(),
            'updated_at' => $environment->updated_at?->toAtomString(),
        ];
    }
}
