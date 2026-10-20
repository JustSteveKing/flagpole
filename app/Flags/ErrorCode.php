<?php

declare(strict_types=1);

namespace App\Flags;

/**
 * OFREP's error codes, which providers act on. Never translated (ADR-0002).
 */
enum ErrorCode: string
{
    case FlagNotFound = 'FLAG_NOT_FOUND';
    case TargetingKeyMissing = 'TARGETING_KEY_MISSING';
    case InvalidContext = 'INVALID_CONTEXT';
    case ParseError = 'PARSE_ERROR';
    case General = 'GENERAL';

    /**
     * The errorDetails that go with the code, in the request's language.
     */
    public function details(string $key): string
    {
        return __('ofrep.' . mb_strtolower($this->value), ['key' => $key]);
    }
}
