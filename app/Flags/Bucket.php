<?php

declare(strict_types=1);

namespace App\Flags;

/**
 * Where a subject falls in a split (ADR-0006): one of 10,000 buckets, from the first four bytes of
 * the SHA-256 of the flag key, a full stop and the targeting key, read as an unsigned big-endian
 * 32-bit integer. Stable across servers and restarts, and different for every flag, so the same
 * subjects do not get every rollout first. Changing it reshuffles every running rollout.
 */
final class Bucket
{
    public const int COUNT = 10_000;

    public static function for(string $flagKey, string $targetingKey): int
    {
        // N reads the first four bytes of the raw digest. There is no substr here on purpose: a
        // multibyte-aware rewrite of it would cut characters, not bytes, and move every subject.
        /** @var array{1: int} $unpacked */
        $unpacked = unpack('N', hash('sha256', $flagKey . '.' . $targetingKey, true));

        return $unpacked[1] % self::COUNT;
    }
}
