<?php

declare(strict_types=1);

namespace App\Flags;

use App\Models\Flag;
use App\Models\FlagSetting;

/**
 * Turns a flag's settings in one environment and an evaluation context into a resolution
 * (RFC-0005). It reads nothing but its arguments: no database, no request, no container, which
 * keeps it safe under Octane and lets its every rule be tested without HTTP.
 */
final class Evaluator
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function evaluate(Flag $flag, FlagSetting $settings, array $context): Resolution
    {
        if ( ! $settings->enabled) {
            return $this->variant($flag, $settings->off_variant, Reason::Disabled);
        }

        foreach ($settings->rules as $rule) {
            $conditions = is_array($rule['conditions'] ?? null) ? $rule['conditions'] : [];

            if ([] !== $conditions && array_all($conditions, fn(mixed $condition): bool => is_array($condition) && $this->matches($condition, $context))) {
                return $this->serve($flag, $rule['serve'] ?? null, $context, Reason::TargetingMatch);
            }
        }

        return $this->serve($flag, $settings->default, $context, [] === $settings->rules ? Reason::Static : Reason::Unknown);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function serve(Flag $flag, mixed $serve, array $context, Reason $reason): Resolution
    {
        $serve = Serve::from($serve);

        if (isset($serve['variant'])) {
            return $this->variant($flag, $serve['variant'], $reason);
        }

        // A split needs a subject to bucket, and only fails for want of one when it is reached.
        $targetingKey = $context['targetingKey'] ?? null;

        if ( ! is_string($targetingKey)) {
            return Resolution::failed($flag->key, ErrorCode::TargetingKeyMissing);
        }

        $bucket = Bucket::for($flag->key, $targetingKey);
        $ceiling = 0;

        foreach ($serve['split'] as $part) {
            $ceiling += $part['weight'];

            if ($bucket < $ceiling) {
                return $this->variant($flag, $part['variant'], Reason::Split);
            }
        }

        // Unreachable while the weights add up to 10,000, which validation insists on.
        return Resolution::failed($flag->key, ErrorCode::General);
    }

    private function variant(Flag $flag, string $name, Reason $reason): Resolution
    {
        foreach ($flag->variants as $variant) {
            if ($variant['name'] === $name) {
                return Resolution::served($flag->key, $flag->type->normalise($variant['value']), $name, $reason);
            }
        }

        return Resolution::failed($flag->key, ErrorCode::General);
    }

    /**
     * A missing or null attribute never matches, for any operator, not_in included. An attribute
     * of the wrong type does not match either: one badly typed property should not stop every
     * flag evaluating (RFC-0005).
     *
     * @param  array<array-key, mixed>  $condition
     * @param  array<string, mixed>  $context
     */
    private function matches(array $condition, array $context): bool
    {
        $attribute = is_string($condition['attribute'] ?? null) ? $condition['attribute'] : null;
        $operator = is_string($condition['operator'] ?? null) ? Operator::tryFrom($condition['operator']) : null;
        $values = is_array($condition['values'] ?? null) ? array_values($condition['values']) : [];
        $actual = null === $attribute ? null : ($context[$attribute] ?? null);

        if (null === $operator || null === $actual) {
            return false;
        }

        return match ($operator) {
            Operator::In => $this->isAny($actual, $values),
            Operator::NotIn => ! $this->isAny($actual, $values),
            Operator::StartsWith => is_string($actual) && array_any($values, static fn(mixed $value): bool => is_string($value) && str_starts_with($actual, $value)),
            Operator::EndsWith => is_string($actual) && array_any($values, static fn(mixed $value): bool => is_string($value) && str_ends_with($actual, $value)),
            Operator::Contains => is_string($actual) && array_any($values, static fn(mixed $value): bool => is_string($value) && str_contains($actual, $value)),
            Operator::LessThan => -1 === $this->compare($actual, $values),
            Operator::LessThanOrEqual => in_array($this->compare($actual, $values), [-1, 0], true),
            Operator::GreaterThan => 1 === $this->compare($actual, $values),
            Operator::GreaterThanOrEqual => in_array($this->compare($actual, $values), [0, 1], true),
        };
    }

    /**
     * Equality with type: the string "1" is not the number 1. Numbers compare by value, so 1 is
     * 1.0.
     *
     * @param  list<mixed>  $values
     */
    private function isAny(mixed $actual, array $values): bool
    {
        return array_any($values, static function (mixed $value) use ($actual): bool {
            $numbers = (is_int($actual) || is_float($actual)) && (is_int($value) || is_float($value));

            return $numbers ? $actual === $value : $actual === $value;
        });
    }

    /**
     * The spaceship result of the attribute against the one value, or null when either is not a
     * number, which never matches. A numeric string is not a number.
     *
     * @param  list<mixed>  $values
     */
    private function compare(mixed $actual, array $values): ?int
    {
        $value = $values[0] ?? null;

        if ( ! is_int($actual) && ! is_float($actual) || ! is_int($value) && ! is_float($value)) {
            return null;
        }

        return $actual <=> $value;
    }
}
