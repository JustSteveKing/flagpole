<?php

declare(strict_types=1);

namespace App\Flags\Ofrep;

use App\Flags\ErrorCode;
use stdClass;

/**
 * The body of an OFREP evaluation, {"context": {...}}, read by hand: a Form Request would answer
 * a bad body with problem details, which OFREP providers cannot read (ADR-0002).
 */
final readonly class EvaluationRequest
{
    /**
     * @param  array<string, mixed>  $context  For the evaluator.
     * @param  string  $canonicalContext  For the bulk ETag: keys sorted at every depth.
     */
    private function __construct(
        public ?ErrorCode $error,
        public array $context,
        public string $canonicalContext,
    ) {}

    public static function fromBody(string $body): self
    {
        // Decoded with objects kept as objects, so {} and [] stay different.
        $decoded = json_decode($body);

        if (JSON_ERROR_NONE !== json_last_error()) {
            return self::failed(ErrorCode::ParseError);
        }

        $context = $decoded instanceof stdClass ? ($decoded->context ?? null) : null;

        if ( ! $context instanceof stdClass) {
            return self::failed(ErrorCode::InvalidContext);
        }

        if (property_exists($context, 'targetingKey') && ! is_string($context->targetingKey)) {
            return self::failed(ErrorCode::InvalidContext);
        }

        /** @var array<string, mixed> $array */
        $array = json_decode((string) json_encode($context), true);

        return new self(null, $array, (string) json_encode(self::canonical($context), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    private static function failed(ErrorCode $error): self
    {
        return new self($error, [], '');
    }

    private static function canonical(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $members = get_object_vars($value);
            ksort($members, SORT_STRING);

            return (object) array_map(self::canonical(...), $members);
        }

        return is_array($value) ? array_map(self::canonical(...), $value) : $value;
    }
}
