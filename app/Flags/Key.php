<?php

declare(strict_types=1);

namespace App\Flags;

/**
 * The rule every key in Flagpole follows: projects, environments, flags and variants. Lowercase
 * letters and digits, in runs separated by single hyphens, at most 64 characters (RFC-0003). Keys
 * appear in URLs and webhook payloads, and never change once created.
 */
final class Key
{
    /**
     * For route constraints, which take a pattern without delimiters or anchors.
     */
    public const string ROUTE_PATTERN = '[a-z0-9]+(?:-[a-z0-9]+)*';

    public const int MAX_LENGTH = 64;

    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return ['string', 'max:' . self::MAX_LENGTH, 'regex:/^' . self::ROUTE_PATTERN . '$/'];
    }
}
