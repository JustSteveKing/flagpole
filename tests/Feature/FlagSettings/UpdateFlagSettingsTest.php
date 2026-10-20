<?php

declare(strict_types=1);

use App\Flags\FlagType;
use App\Models\FlagSetting;
use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const PRODUCTION = '/v1/projects/checkout/flags/new-checkout/settings/production';

function productionSettings(): FlagSetting
{
    return FlagSetting::query()->whereHas('environment', fn(Builder $query) => $query->where('key', 'production'))->sole();
}

function productionTag(): string
{
    return (string) productionSettings()->entityTag()?->toString();
}

/**
 * @return array<string, mixed>
 */
function staffRule(): array
{
    return [
        'description' => 'Staff see it first',
        'conditions' => [['attribute' => 'email', 'operator' => 'ends_with', 'values' => ['@example.com']]],
        'serve' => ['variant' => 'on'],
    ];
}

it('turns a flag on in one environment, bumping only that environment', function (): void {
    $user = User::factory()->create();
    $flag = flagIn(projectWithEnvironments($user));
    $before = environmentVersions($flag->project);

    mergePatch(PRODUCTION, ['enabled' => true], flagToken($user), ['If-Match' => productionTag()])
        ->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertAcceptsPatch();

    expect(environmentVersions($flag->project))->toBe(['development' => $before['development'], 'production' => $before['production'] + 1]);
});

it('keeps the stored rules when a patch leaves them out, and replaces them all when it sends them', function (): void {
    $user = User::factory()->create();
    flagIn(projectWithEnvironments($user));
    $split = ['split' => [['variant' => 'on', 'weight' => 1000], ['variant' => 'off', 'weight' => 9000]]];

    mergePatch(PRODUCTION, ['enabled' => true, 'rules' => [staffRule(), ['conditions' => [['attribute' => 'country', 'operator' => 'in', 'values' => ['GB']]], 'serve' => $split]]], flagToken($user), ['If-Match' => productionTag()])
        ->assertOk()
        ->assertJsonCount(2, 'data.rules')
        ->assertJsonPath('data.rules.1.serve', $split)
        ->assertJsonPath('data.rules.1.description', null);

    mergePatch(PRODUCTION, ['enabled' => false], flagToken($user), ['If-Match' => productionTag()])
        ->assertOk()
        ->assertJsonCount(2, 'data.rules');

    mergePatch(PRODUCTION, ['rules' => [staffRule()]], flagToken($user), ['If-Match' => productionTag()])
        ->assertOk()
        ->assertJsonCount(1, 'data.rules');
});

it('records nothing and bumps nothing for a patch that changes nothing', function (): void {
    $user = User::factory()->create();
    $flag = flagIn(projectWithEnvironments($user));
    $tag = productionTag();
    $before = environmentVersions($flag->project);

    mergePatch(PRODUCTION, ['enabled' => false], flagToken($user), ['If-Match' => $tag])->assertOk();

    expect(productionTag())->toBe($tag)
        ->and(environmentVersions($flag->project))->toBe($before);
});

it('refuses settings that break a rule', function (array $patch, string $field): void {
    $user = User::factory()->create();
    flagIn(projectWithEnvironments($user));

    mergePatch(PRODUCTION, $patch, flagToken($user), ['If-Match' => productionTag()])
        ->assertProblem(422)
        ->assertJsonValidationErrorFor($field);
})->with([
    'an unknown off variant' => [['off_variant' => 'maybe'], 'off_variant'],
    'an unknown default variant' => [['default' => ['variant' => 'maybe']], 'default.variant'],
    'a split that does not add up' => [['default' => ['variant' => null, 'split' => [['variant' => 'on', 'weight' => 5000], ['variant' => 'off', 'weight' => 4000]]]], 'default.split'],
    'a split repeating a variant' => [['default' => ['variant' => null, 'split' => [['variant' => 'on', 'weight' => 5000], ['variant' => 'on', 'weight' => 5000]]]], 'default.split.1.variant'],
    'a serve with both' => [['default' => ['split' => [['variant' => 'on', 'weight' => 10000]]]], 'default.variant'],
    'a serve with neither' => [['default' => ['variant' => null]], 'default.variant'],
    'a rule with no conditions' => [['rules' => [['conditions' => [], 'serve' => ['variant' => 'on']]]], 'rules.0.conditions'],
    'an unknown operator' => [['rules' => [['conditions' => [['attribute' => 'plan', 'operator' => 'like', 'values' => ['pro']]], 'serve' => ['variant' => 'on']]]], 'rules.0.conditions.0.operator'],
    'a number operator given text' => [['rules' => [['conditions' => [['attribute' => 'age', 'operator' => 'gt', 'values' => ['18']]], 'serve' => ['variant' => 'on']]]], 'rules.0.conditions.0'],
    'a number operator given two values' => [['rules' => [['conditions' => [['attribute' => 'age', 'operator' => 'gte', 'values' => [18, 21]]], 'serve' => ['variant' => 'on']]]], 'rules.0.conditions.0'],
    'a string operator given a number' => [['rules' => [['conditions' => [['attribute' => 'email', 'operator' => 'ends_with', 'values' => [5]]], 'serve' => ['variant' => 'on']]]], 'rules.0.conditions.0'],
    'a changed environment' => [['environment' => 'development'], 'environment'],
]);

it('refuses more than 50 rules', function (): void {
    $user = User::factory()->create();
    flagIn(projectWithEnvironments($user));

    mergePatch(PRODUCTION, ['rules' => array_fill(0, 51, staffRule())], flagToken($user), ['If-Match' => productionTag()])
        ->assertProblem(422)
        ->assertJsonValidationErrorFor('rules');
});

it('takes a split default, keeping only what a serve means', function (): void {
    $user = User::factory()->create();
    flagIn(projectWithEnvironments($user), 'colour', FlagType::String, [['name' => 'blue', 'value' => 'b'], ['name' => 'green', 'value' => 'g']]);
    $uri = '/v1/projects/checkout/flags/colour/settings/production';

    mergePatch($uri, ['default' => ['variant' => null, 'split' => [['variant' => 'blue', 'weight' => 2500, 'note' => 'ignored'], ['variant' => 'green', 'weight' => 7500]]]], flagToken($user), ['If-Match' => productionTag()])
        ->assertOk()
        ->assertJsonPath('data.default', ['split' => [['variant' => 'blue', 'weight' => 2500], ['variant' => 'green', 'weight' => 7500]]]);
});

it('refuses changes to an archived flag with 409', function (): void {
    $user = User::factory()->create();
    flagIn(projectWithEnvironments($user))->forceFill(['archived_at' => now()])->save();

    mergePatch(PRODUCTION, ['enabled' => true], flagToken($user), ['If-Match' => productionTag()])
        ->assertProblem(409);
});

it('stops the second of two people editing the same settings', function (): void {
    $user = User::factory()->create();
    flagIn(projectWithEnvironments($user));
    $both = productionTag();

    mergePatch(PRODUCTION, ['enabled' => true], flagToken($user), ['If-Match' => $both])->assertOk();
    mergePatch(PRODUCTION, ['rules' => [staffRule()]], flagToken($user), ['If-Match' => $both])->assertPreconditionFailed();

    expect(productionSettings()->rules)->toBe([]);
});

it('requires If-Match', function (): void {
    $user = User::factory()->create();
    flagIn(projectWithEnvironments($user));

    mergePatch(PRODUCTION, ['enabled' => true], flagToken($user))->assertProblem(428);
});

it('requires the flags:write ability', function (): void {
    $user = User::factory()->create();
    flagIn(projectWithEnvironments($user));

    mergePatch(PRODUCTION, ['enabled' => true], flagToken($user, ['flags:read']), ['If-Match' => productionTag()])
        ->assertProblem(403);
});
