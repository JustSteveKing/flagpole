<?php

declare(strict_types=1);

namespace App\Http\Requests\FlagSettings;

use App\Flags\SettingsRules;
use App\Http\Resources\FlagSettingResource;
use App\Models\FlagSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use JustSteveKing\Toolbox\MergePatch\MergePatch;

/**
 * A JSON Merge Patch (RFC 7396) of a flag's settings in one environment. The patch is applied to the
 * settings as the API shows them, and the merged document is validated, so a patch that leaves the
 * rules alone is still checked against the rules already stored (RFC-0004).
 */
final class UpdateFlagSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $setting = $this->setting();

        if ( ! $setting instanceof FlagSetting) {
            // Scribe reads these rules with no route, to document the body.
            return ['enabled' => ['boolean'], 'off_variant' => ['string'], 'default' => ['array'], 'rules' => ['array']];
        }

        $current = $this->current($setting);

        return [
            // Read-only: they may be sent back with their current values.
            'environment' => ['required', 'string', Rule::in([$current['environment'] ?? null])],
            'updated_at' => ['nullable', Rule::in([$current['updated_at'] ?? null])],
            ...SettingsRules::for($setting->flag),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'environment.in' => __('api.flags.read_only'),
            'updated_at.in' => __('api.flags.read_only'),
            ...SettingsRules::messages(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        $setting = $this->setting();

        if ( ! $setting instanceof FlagSetting) {
            return [];
        }

        $merged = app(MergePatch::class)->applyTo($this->current($setting));

        /** @var array<string, mixed> $document */
        $document = is_array($merged) ? $merged : [];

        return $document;
    }

    /**
     * @return array<string, mixed>
     */
    private function current(FlagSetting $setting): array
    {
        /** @var array<string, mixed> */
        return json_decode((string) json_encode(new FlagSettingResource($setting)->resolve($this)), true);
    }

    private function setting(): ?FlagSetting
    {
        $setting = $this->route('settings');

        return $setting instanceof FlagSetting ? $setting : null;
    }
}
