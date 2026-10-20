<?php

declare(strict_types=1);

namespace App\Http\Requests\Flags\Concerns;

use App\Flags\FlagType;
use App\Flags\Key;
use Closure;

/**
 * The rules for a flag's variants, shared by creating and changing one (RFC-0004).
 */
trait ValidatesVariants
{
    /**
     * @return array<string, mixed>
     */
    private function variantRules(?FlagType $type): array
    {
        return [
            'variants.*' => ['array'],
            'variants.*.name' => ['required', ...Key::rules(), 'distinct'],
            'variants.*.value' => ['present', static function (string $attribute, mixed $value, Closure $fail) use ($type): void {
                if ($type instanceof FlagType && ! $type->accepts($value)) {
                    $fail(__('api.flags.variant_type', ['type' => $type->value]));
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function variantMessages(): array
    {
        return [
            'variants.*.name.regex' => __('api.keys.format'),
            'variants.*.name.distinct' => __('api.flags.variant_repeated'),
        ];
    }
}
