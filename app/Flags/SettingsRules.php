<?php

declare(strict_types=1);

namespace App\Flags;

use App\Models\Flag;
use Closure;
use Illuminate\Validation\Rule;

/**
 * The validation rules for a settings document (RFC-0004). They are written for the merged
 * document, never the patch.
 */
final class SettingsRules
{
    public const int MAX_RULES = 50;

    public const int MAX_CONDITIONS = 10;

    public const int MAX_VALUES = 100;

    /**
     * A split's weights are hundredths of a percent, so they add up to this (ADR-0006).
     */
    public const int TOTAL_WEIGHT = 10_000;

    /**
     * @return array<string, mixed>
     */
    public static function for(Flag $flag): array
    {
        $variants = Rule::in($flag->variantNames());

        return [
            'enabled' => ['required', 'boolean'],
            'off_variant' => ['required', 'string', $variants],
            ...self::serve('default', $variants),
            'rules' => ['present', 'array', 'list', 'max:' . self::MAX_RULES],
            'rules.*' => ['array'],
            'rules.*.description' => ['nullable', 'string', 'max:255'],
            'rules.*.conditions' => ['required', 'array', 'list', 'min:1', 'max:' . self::MAX_CONDITIONS],
            'rules.*.conditions.*' => ['array', self::conditionValues()],
            'rules.*.conditions.*.attribute' => ['required', 'string', 'max:255'],
            'rules.*.conditions.*.operator' => ['required', 'string', Rule::enum(Operator::class)],
            'rules.*.conditions.*.values' => ['required', 'array', 'list', 'min:1', 'max:' . self::MAX_VALUES],
            'rules.*.conditions.*.values.*' => ['present'],
            ...self::serve('rules.*.serve', $variants),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'off_variant.in' => __('api.flags.unknown_variant'),
            'default.variant.in' => __('api.flags.unknown_variant'),
            'default.split.*.variant.in' => __('api.flags.unknown_variant'),
            'rules.*.serve.variant.in' => __('api.flags.unknown_variant'),
            'rules.*.serve.split.*.variant.in' => __('api.flags.unknown_variant'),
            'default.split.*.variant.distinct' => __('api.flags.split_repeats_variant'),
            'rules.*.serve.split.*.variant.distinct' => __('api.flags.split_repeats_variant'),
        ];
    }

    /**
     * A serve is a variant or a split, never both.
     *
     * @return array<string, mixed>
     */
    private static function serve(string $path, object $variants): array
    {
        return [
            $path => ['required', 'array'],
            "{$path}.variant" => ["required_without:{$path}.split", "prohibits:{$path}.split", 'string', $variants],
            "{$path}.split" => ["required_without:{$path}.variant", 'array', 'list', 'min:1', self::weightsAddUp()],
            "{$path}.split.*.variant" => ['required', 'string', 'distinct', $variants],
            "{$path}.split.*.weight" => ['required', 'integer', 'min:0', 'max:' . self::TOTAL_WEIGHT],
        ];
    }

    private static function weightsAddUp(): Closure
    {
        return static function (string $attribute, mixed $split, Closure $fail): void {
            if ( ! is_array($split)) {
                return;
            }

            $total = array_sum(array_map(static fn(mixed $part): int => is_array($part) && is_int($part['weight'] ?? null) ? $part['weight'] : 0, $split));

            if (self::TOTAL_WEIGHT !== $total) {
                $fail(__('api.flags.split_total', ['total' => self::TOTAL_WEIGHT]));
            }
        };
    }

    private static function conditionValues(): Closure
    {
        return static function (string $attribute, mixed $condition, Closure $fail): void {
            if ( ! is_array($condition) || ! is_string($condition['operator'] ?? null) || ! is_array($condition['values'] ?? null)) {
                return;
            }

            $operator = Operator::tryFrom($condition['operator']);

            if (null !== $operator && ! $operator->accepts(array_values($condition['values']))) {
                $fail(__('api.flags.operator_values.' . $operator->value));
            }
        };
    }
}
