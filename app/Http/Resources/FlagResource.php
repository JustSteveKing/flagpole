<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Flag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class FlagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Flag $flag */
        $flag = $this->resource;

        return [
            'key' => $flag->key,
            'name' => $flag->name,
            'description' => $flag->description,
            'type' => $flag->type->value,
            'variants' => array_map(static fn(array $variant): array => [
                'name' => $variant['name'],
                'value' => $flag->type->normalise($variant['value']),
            ], $flag->variants),
            'archived_at' => $flag->archived_at?->toAtomString(),
            'created_at' => $flag->created_at?->toAtomString(),
            'updated_at' => $flag->updated_at?->toAtomString(),
        ];
    }
}
