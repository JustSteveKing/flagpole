<?php

declare(strict_types=1);

use App\Flags\Bucket;
use App\Flags\FlagType;

// Known answers, checked against an independent implementation. If one of these changes, every
// running rollout has been reshuffled, which only a new ADR may do (ADR-0006).
it('puts a subject in the bucket the definition says', function (string $flag, string $subject, int $bucket): void {
    expect(Bucket::for($flag, $subject))->toBe($bucket);
})->with([
    ['new-checkout', 'user-1', 7643],
    ['new-checkout', 'user-2', 4137],
    ['new-checkout', 'jane@example.com', 5383],
    ['theme', 'user-1', 3576],
    ['theme', '', 88],
    ['a', 'b', 2620],
]);

it('spreads subjects evenly enough that a 10% rollout gets about 10%', function (): void {
    $inside = 0;

    for ($subject = 0; $subject < 100_000; $subject++) {
        if (Bucket::for('rollout', "user-{$subject}") < 1_000) {
            $inside++;
        }
    }

    expect(abs($inside / 100_000 - 0.10))->toBeLessThan(0.005);
});

it('buckets the same subject differently for different flags', function (): void {
    $same = 0;

    for ($subject = 0; $subject < 1_000; $subject++) {
        $same += (int) (Bucket::for('first', "user-{$subject}") < 1_000 && Bucket::for('second', "user-{$subject}") < 1_000);
    }

    // Independent flags put about 1% of subjects in the first 10% of both.
    expect($same)->toBeLessThan(30);
});

it('limits object values by bytes, not characters', function (): void {
    // 6,000 euro signs are 36,000 bytes of JSON once escaped: over the limit, though the value has
    // fewer than 16,384 characters.
    expect(FlagType::Object->accepts(['text' => str_repeat('€', 6_000)]))->toBeFalse()
        ->and(FlagType::Object->accepts(['text' => str_repeat('a', 6_000)]))->toBeTrue();
});
