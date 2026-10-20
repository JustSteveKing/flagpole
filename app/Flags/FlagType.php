<?php

declare(strict_types=1);

namespace App\Flags;

use stdClass;

/**
 * The value types a flag can have: the ones OFREP can return (ADR-0001).
 */
enum FlagType: string
{
    case Boolean = 'boolean';
    case String = 'string';
    case Integer = 'integer';
    case Float = 'float';
    case Object = 'object';

    /**
     * The largest object value, as JSON (RFC-0004).
     */
    public const int MAX_OBJECT_BYTES = 16_384;

    public function accepts(mixed $value): bool
    {
        return match ($this) {
            self::Boolean => is_bool($value),
            self::String => is_string($value),
            self::Integer => is_int($value),
            // JSON has one number type, so 2 is as good a float as 2.0.
            self::Float => is_int($value) || is_float($value),
            // A JSON object decodes to an array with string keys, or to [] when empty. A list is a
            // JSON array, which is not an object.
            self::Object => is_array($value)
                && ([] === $value || ! array_is_list($value))
                && mb_strlen((string) json_encode($value)) <= self::MAX_OBJECT_BYTES,
        };
    }

    /**
     * A value as it is stored and served: floats stay floats, and an empty object stays an object
     * when it is encoded again.
     */
    public function normalise(mixed $value): mixed
    {
        return match ($this) {
            self::Float => is_int($value) ? (float) $value : $value,
            self::Object => [] === $value ? new stdClass() : $value,
            default => $value,
        };
    }
}
