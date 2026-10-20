<?php

declare(strict_types=1);

namespace App\Flags;

/**
 * What evaluating one flag came to: a value, its variant and the reason, or an error code.
 */
final readonly class Resolution
{
    private function __construct(
        public string $key,
        public mixed $value,
        public ?string $variant,
        public ?Reason $reason,
        public ?ErrorCode $error,
    ) {}

    public static function served(string $key, mixed $value, string $variant, Reason $reason): self
    {
        return new self($key, $value, $variant, $reason, null);
    }

    public static function failed(string $key, ErrorCode $error): self
    {
        return new self($key, null, null, null, $error);
    }

    public function isFailure(): bool
    {
        return $this->error instanceof ErrorCode;
    }
}
