<?php

declare(strict_types=1);

namespace App\Flags;

/**
 * Why an evaluation came out as it did: OFREP's reason codes (REF-0001).
 */
enum Reason: string
{
    case Static = 'STATIC';
    case TargetingMatch = 'TARGETING_MATCH';
    case Split = 'SPLIT';
    case Disabled = 'DISABLED';
    // OFREP has no DEFAULT, which OpenFeature uses when rules exist and none matched (RFC-0001).
    case Unknown = 'UNKNOWN';
}
