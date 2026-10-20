<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Project $project */
        $project = $this->resource;

        return [
            'key' => $project->key,
            'name' => $project->name,
            'created_at' => $project->created_at?->toAtomString(),
            'updated_at' => $project->updated_at?->toAtomString(),
        ];
    }
}
