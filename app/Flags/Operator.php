<?php

declare(strict_types=1);

namespace App\Flags;

/**
 * How a rule's condition compares a context attribute with its values (RFC-0004). What a match
 * means is the evaluator's job (RFC-0005); this says which values each operator takes.
 */
enum Operator: string
{
    case In = 'in';
    case NotIn = 'not_in';
    case StartsWith = 'starts_with';
    case EndsWith = 'ends_with';
    case Contains = 'contains';
    case LessThan = 'lt';
    case LessThanOrEqual = 'lte';
    case GreaterThan = 'gt';
    case GreaterThanOrEqual = 'gte';

    /**
     * @param  list<mixed>  $values
     */
    public function accepts(array $values): bool
    {
        return match ($this) {
            self::In, self::NotIn => array_all($values, static fn(mixed $value): bool => is_string($value) || is_int($value) || is_float($value) || is_bool($value)),
            self::StartsWith, self::EndsWith, self::Contains => array_all($values, static fn(mixed $value): bool => is_string($value)),
            self::LessThan, self::LessThanOrEqual, self::GreaterThan, self::GreaterThanOrEqual => 1 === count($values) && (is_int($values[0]) || is_float($values[0])),
        };
    }
}
