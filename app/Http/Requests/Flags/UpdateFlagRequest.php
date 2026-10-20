<?php

declare(strict_types=1);

namespace App\Http\Requests\Flags;

use App\Flags\FlagType;
use App\Http\Requests\Flags\Concerns\ValidatesVariants;
use App\Http\Resources\FlagResource;
use App\Models\Flag;
use App\Models\FlagSetting;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use JustSteveKing\Toolbox\MergePatch\MergePatch;

/**
 * A JSON Merge Patch (RFC 7396) of a flag. The patch is applied to the flag as the API shows it,
 * and the merged document is what gets validated.
 */
final class UpdateFlagRequest extends FormRequest
{
    use ValidatesVariants;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $flag = $this->flag();

        return [
            // Keys and types never change. The current ones may be sent back.
            'key' => ['required', 'string', Rule::in([$flag?->key])],
            'type' => ['required', 'string', Rule::in([$flag?->type->value])],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'variants' => ['required', 'array', 'list', 'min:2', 'max:20', $this->booleanVariantsFixed(), $this->usedVariantsKept()],
            ...$this->variantRules($flag?->type),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.in' => __('api.keys.immutable'),
            'type.in' => __('api.flags.type_immutable'),
            ...$this->variantMessages(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        $flag = $this->flag();

        if ( ! $flag instanceof Flag) {
            return [];
        }

        /** @var array<string, mixed> $current */
        $current = json_decode((string) json_encode(new FlagResource($flag)->resolve($this)), true);
        $merged = app(MergePatch::class)->applyTo(array_intersect_key($current, array_flip(['key', 'type', 'name', 'description', 'variants'])));

        /** @var array<string, mixed> $document */
        $document = is_array($merged) ? $merged : [];

        return $document;
    }

    private function booleanVariantsFixed(): Closure
    {
        return function (string $attribute, mixed $variants, Closure $fail): void {
            if (FlagType::Boolean === $this->flag()?->type && Flag::BOOLEAN_VARIANTS !== $variants) {
                $fail(__('api.flags.boolean_variants'));
            }
        };
    }

    /**
     * A variant the settings of any environment still name cannot be removed or renamed.
     */
    private function usedVariantsKept(): Closure
    {
        return function (string $attribute, mixed $variants, Closure $fail): void {
            $flag = $this->flag();

            if ( ! $flag instanceof Flag || ! is_array($variants)) {
                return;
            }

            $names = array_values(array_filter(array_map(static fn(mixed $variant): mixed => is_array($variant) ? ($variant['name'] ?? null) : null, $variants), is_string(...)));
            $environments = $flag->settings()->with('environment')->get()
                ->filter(static fn(FlagSetting $setting): bool => [] !== array_diff($setting->usedVariants(), $names))
                ->map(static fn(FlagSetting $setting): string => $setting->environment->key)
                ->sort()
                ->values()
                ->all();

            if ([] !== $environments) {
                $fail(__('api.flags.variant_in_use', ['environments' => implode(', ', $environments)]));
            }
        };
    }

    /**
     * Null only when Scribe reads the rules, with no route, to document the body.
     */
    private function flag(): ?Flag
    {
        $flag = $this->route('flag');

        return $flag instanceof Flag ? $flag : null;
    }
}
