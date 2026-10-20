<?php

declare(strict_types=1);

use App\Flags\ErrorCode;
use App\Flags\Evaluator;
use App\Flags\FlagType;
use App\Flags\Reason;
use App\Models\Flag;
use App\Models\FlagSetting;

/**
 * @param  list<array<string, mixed>>  $rules
 * @param  array<string, mixed>  $default
 */
function settings(bool $enabled = true, array $rules = [], array $default = ['variant' => 'off'], string $off = 'off'): FlagSetting
{
    return new FlagSetting(['enabled' => $enabled, 'off_variant' => $off, 'default' => $default, 'rules' => $rules]);
}

function booleanFlag(string $key = 'new-checkout'): Flag
{
    return new Flag(['key' => $key, 'name' => 'Flag', 'type' => FlagType::Boolean, 'variants' => Flag::BOOLEAN_VARIANTS]);
}

/**
 * @param  list<mixed>  $values
 * @return array<string, mixed>
 */
function onWhen(string $attribute, string $operator, array $values): array
{
    return ['conditions' => [['attribute' => $attribute, 'operator' => $operator, 'values' => $values]], 'serve' => ['variant' => 'on']];
}

describe('the order of evaluation', function (): void {
    it('serves the off variant while disabled, whatever the rules say', function (): void {
        $resolution = new Evaluator()->evaluate(booleanFlag(), settings(false, [onWhen('plan', 'in', ['pro'])], ['variant' => 'on']), ['plan' => 'pro']);

        expect($resolution->value)->toBeFalse()
            ->and($resolution->variant)->toBe('off')
            ->and($resolution->reason)->toBe(Reason::Disabled);
    });

    it('lets the first matching rule decide', function (): void {
        $rules = [
            ['conditions' => [['attribute' => 'plan', 'operator' => 'in', 'values' => ['pro']]], 'serve' => ['variant' => 'off']],
            onWhen('plan', 'in', ['pro']),
        ];

        $resolution = new Evaluator()->evaluate(booleanFlag(), settings(true, $rules, ['variant' => 'on']), ['plan' => 'pro']);

        expect($resolution->variant)->toBe('off')->and($resolution->reason)->toBe(Reason::TargetingMatch);
    });

    it('needs every condition in a rule to match', function (): void {
        $rule = ['conditions' => [
            ['attribute' => 'plan', 'operator' => 'in', 'values' => ['pro']],
            ['attribute' => 'country', 'operator' => 'in', 'values' => ['GB']],
        ], 'serve' => ['variant' => 'on']];

        $evaluator = new Evaluator();

        expect($evaluator->evaluate(booleanFlag(), settings(true, [$rule]), ['plan' => 'pro', 'country' => 'GB'])->variant)->toBe('on')
            ->and($evaluator->evaluate(booleanFlag(), settings(true, [$rule]), ['plan' => 'pro', 'country' => 'FR'])->variant)->toBe('off');
    });

    it('gives the default STATIC with no rules, and UNKNOWN when rules exist and none matched', function (): void {
        $evaluator = new Evaluator();

        expect($evaluator->evaluate(booleanFlag(), settings(true, [], ['variant' => 'on']), [])->reason)->toBe(Reason::Static)
            ->and($evaluator->evaluate(booleanFlag(), settings(true, [onWhen('plan', 'in', ['pro'])], ['variant' => 'on']), [])->reason)->toBe(Reason::Unknown);
    });
});

describe('splits', function (): void {
    it('serves a split with SPLIT, by bucket', function (): void {
        // new-checkout.user-2 is bucket 4137, new-checkout.user-1 is 7643 (BucketTest).
        $split = ['split' => [['variant' => 'on', 'weight' => 5000], ['variant' => 'off', 'weight' => 5000]]];
        $evaluator = new Evaluator();

        expect($evaluator->evaluate(booleanFlag(), settings(true, [], $split), ['targetingKey' => 'user-2'])->variant)->toBe('on')
            ->and($evaluator->evaluate(booleanFlag(), settings(true, [], $split), ['targetingKey' => 'user-1'])->variant)->toBe('off')
            ->and($evaluator->evaluate(booleanFlag(), settings(true, [], $split), ['targetingKey' => 'user-1'])->reason)->toBe(Reason::Split);
    });

    it('keeps the first 10% where they were when a rollout grows to 20%', function (): void {
        $evaluator = new Evaluator();
        $at = static fn(int $weight): FlagSetting => settings(true, [], ['split' => [['variant' => 'on', 'weight' => $weight], ['variant' => 'off', 'weight' => 10_000 - $weight]]]);

        foreach (range(0, 499) as $subject) {
            $context = ['targetingKey' => "user-{$subject}"];

            if ('on' === $evaluator->evaluate(booleanFlag(), $at(1_000), $context)->variant) {
                expect($evaluator->evaluate(booleanFlag(), $at(2_000), $context)->variant)->toBe('on');
            }
        }
    });

    it('fails with TARGETING_KEY_MISSING only when a split is reached without one', function (): void {
        $split = ['split' => [['variant' => 'on', 'weight' => 10_000]]];
        $evaluator = new Evaluator();

        expect($evaluator->evaluate(booleanFlag(), settings(true, [], $split), [])->error)->toBe(ErrorCode::TargetingKeyMissing)
            ->and($evaluator->evaluate(booleanFlag(), settings(true, [['conditions' => [['attribute' => 'plan', 'operator' => 'in', 'values' => ['pro']]], 'serve' => $split]]), [])->isFailure())->toBeFalse();
    });
});

describe('conditions', function (): void {
    it('matches as each operator says', function (string $operator, array $values, mixed $actual, bool $matches): void {
        $resolution = new Evaluator()->evaluate(booleanFlag(), settings(true, [onWhen('attribute', $operator, $values)]), ['attribute' => $actual]);

        expect('on' === $resolution->variant)->toBe($matches);
    })->with([
        'in, a match' => ['in', ['pro', 'team'], 'team', true],
        'in, no match' => ['in', ['pro'], 'free', false],
        'in, a string is not a number' => ['in', [1], '1', false],
        'in, numbers compare by value' => ['in', [1.0], 1, true],
        'in, booleans' => ['in', [true], true, true],
        'not_in, absent from the list' => ['not_in', ['beta'], 'stable', true],
        'not_in, in the list' => ['not_in', ['beta'], 'beta', false],
        'starts_with' => ['starts_with', ['jane'], 'jane@example.com', true],
        'ends_with' => ['ends_with', ['@example.com'], 'jane@example.com', true],
        'ends_with is case-sensitive' => ['ends_with', ['@example.com'], 'jane@EXAMPLE.com', false],
        'contains' => ['contains', ['@'], 'jane@example.com', true],
        'a string operator on a number' => ['contains', ['1'], 123, false],
        'lt' => ['lt', [18], 17, true],
        'lt, equal' => ['lt', [18], 18, false],
        'lte, equal' => ['lte', [18], 18, true],
        'gt' => ['gt', [18], 19.5, true],
        'gte, equal' => ['gte', [18], 18, true],
        'gte, below' => ['gte', [18], 17, false],
        'a numeric string is not a number' => ['gte', [18], '42', false],
    ]);

    it('never matches a missing or null attribute, not_in included', function (string $operator, array $values): void {
        $evaluator = new Evaluator();

        foreach ([[], ['attribute' => null]] as $context) {
            expect($evaluator->evaluate(booleanFlag(), settings(true, [onWhen('attribute', $operator, $values)]), $context)->variant)->toBe('off');
        }
    })->with([
        ['in', ['x']],
        ['not_in', ['x']],
        ['starts_with', ['x']],
        ['ends_with', ['x']],
        ['contains', ['x']],
        ['lt', [1]],
        ['lte', [1]],
        ['gt', [1]],
        ['gte', [1]],
    ]);
});

describe('values', function (): void {
    it('serves the variant value with the flag type', function (): void {
        $flag = new Flag(['key' => 'ratio', 'name' => 'Ratio', 'type' => FlagType::Float, 'variants' => [['name' => 'one', 'value' => 1], ['name' => 'half', 'value' => 0.5]]]);
        $object = new Flag(['key' => 'config', 'name' => 'Config', 'type' => FlagType::Object, 'variants' => [['name' => 'empty', 'value' => []], ['name' => 'full', 'value' => ['a' => 1]]]]);

        expect(new Evaluator()->evaluate($flag, settings(true, [], ['variant' => 'one'], 'one'), [])->value)->toBe(1.0)
            ->and(new Evaluator()->evaluate($object, settings(true, [], ['variant' => 'empty'], 'empty'), [])->value)->toEqual(new stdClass());
    });
});
