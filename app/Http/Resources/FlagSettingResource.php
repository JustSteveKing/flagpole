<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FlagSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class FlagSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FlagSetting $setting */
        $setting = $this->resource;

        return [
            'environment' => $setting->environment->key,
            'enabled' => $setting->enabled,
            'off_variant' => $setting->off_variant,
            'default' => $setting->default,
            'rules' => $setting->rules,
            'updated_at' => $setting->updated_at?->toAtomString(),
        ];
    }
}
