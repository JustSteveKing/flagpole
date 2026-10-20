<?php

declare(strict_types=1);

namespace App\Flags;

/**
 * What a rule or a default serves: one variant, or a split across variants by weight (RFC-0004).
 * Validation has already checked the shape; this keeps only the members that mean something.
 */
final class Serve
{
    /**
     * @return array{variant: string}|array{split: list<array{variant: string, weight: int}>}
     */
    public static function from(mixed $serve): array
    {
        $serve = is_array($serve) ? $serve : [];

        if (is_string($serve['variant'] ?? null)) {
            return ['variant' => $serve['variant']];
        }

        $split = [];

        foreach (is_array($serve['split'] ?? null) ? $serve['split'] : [] as $part) {
            if (is_array($part) && is_string($part['variant'] ?? null) && is_int($part['weight'] ?? null)) {
                $split[] = ['variant' => $part['variant'], 'weight' => $part['weight']];
            }
        }

        return ['split' => $split];
    }
}
