<?php

declare(strict_types=1);

use App\Flags\FlagType;
use App\Models\Flag;
use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function flagTag(): string
{
    return (string) Flag::query()->sole()->entityTag()?->toString();
}

function colourFlag(User $user): Flag
{
    return flagIn(projectWithEnvironments($user), 'colour', FlagType::String, [['name' => 'blue', 'value' => '#0055ff'], ['name' => 'green', 'value' => '#00aa55'], ['name' => 'red', 'value' => '#ff0000']]);
}

it('renames a flag without bumping any environment', function (): void {
    $user = User::factory()->create();
    $flag = flagIn(projectWithEnvironments($user));
    $before = environmentVersions($flag->project);

    mergePatch('/v1/projects/checkout/flags/new-checkout', ['name' => 'Checkout redesign'], flagToken($user), ['If-Match' => flagTag()])
        ->assertOk()
        ->assertJsonPath('data.name', 'Checkout redesign');

    expect(environmentVersions($flag->project))->toBe($before);
});

it('changes a variant value, bumping every environment', function (): void {
    $user = User::factory()->create();
    $flag = colourFlag($user);
    $before = environmentVersions($flag->project);
    $variants = [['name' => 'blue', 'value' => '#0000ff'], ['name' => 'green', 'value' => '#00aa55'], ['name' => 'red', 'value' => '#ff0000']];

    mergePatch('/v1/projects/checkout/flags/colour', ['variants' => $variants], flagToken($user), ['If-Match' => flagTag()])
        ->assertOk()
        ->assertJsonPath('data.variants.0.value', '#0000ff');

    expect(environmentVersions($flag->project))->toBe(array_map(fn(int $version): int => $version + 1, $before));
});

it('refuses removing a variant that production still uses, naming production', function (): void {
    $user = User::factory()->create();
    $flag = colourFlag($user);
    $production = $flag->settings()->whereHas('environment', fn(Builder $query) => $query->where('key', 'production'))->sole();
    $production->forceFill(['default' => ['variant' => 'red']])->save();

    mergePatch('/v1/projects/checkout/flags/colour', ['variants' => [['name' => 'blue', 'value' => '#0055ff'], ['name' => 'green', 'value' => '#00aa55']]], flagToken($user), ['If-Match' => flagTag()])
        ->assertProblem(422)
        ->assertJsonPath('errors.variants.0', __('api.flags.variant_in_use', ['environments' => 'production']));

    mergePatch('/v1/projects/checkout/flags/colour', ['variants' => [['name' => 'blue', 'value' => '#0055ff'], ['name' => 'red', 'value' => '#ff0000']]], flagToken($user), ['If-Match' => flagTag()])
        ->assertOk();
});

it('refuses changing the key, the type, or a boolean flag\'s variants', function (): void {
    $user = User::factory()->create();
    flagIn(projectWithEnvironments($user));
    $uri = '/v1/projects/checkout/flags/new-checkout';

    mergePatch($uri, ['key' => 'renamed'], flagToken($user), ['If-Match' => flagTag()])
        ->assertProblem(422)
        ->assertJsonPath('errors.key.0', __('api.keys.immutable'));
    mergePatch($uri, ['type' => 'string'], flagToken($user), ['If-Match' => flagTag()])
        ->assertProblem(422)
        ->assertJsonPath('errors.type.0', __('api.flags.type_immutable'));
    mergePatch($uri, ['variants' => [['name' => 'yes', 'value' => true], ['name' => 'no', 'value' => false]]], flagToken($user), ['If-Match' => flagTag()])
        ->assertProblem(422);
});

it('refuses changes to an archived flag with 409', function (): void {
    $user = User::factory()->create();
    flagIn(projectWithEnvironments($user))->forceFill(['archived_at' => now()])->save();

    mergePatch('/v1/projects/checkout/flags/new-checkout', ['name' => 'Renamed'], flagToken($user), ['If-Match' => flagTag()])
        ->assertProblem(409)
        ->assertJsonPath('detail', __('api.flags.archived'));
});

it('requires If-Match, and refuses a stale one', function (): void {
    $user = User::factory()->create();
    flagIn(projectWithEnvironments($user));
    $stale = flagTag();

    mergePatch('/v1/projects/checkout/flags/new-checkout', ['name' => 'First'], flagToken($user))->assertProblem(428);
    mergePatch('/v1/projects/checkout/flags/new-checkout', ['name' => 'First'], flagToken($user), ['If-Match' => $stale])->assertOk();
    mergePatch('/v1/projects/checkout/flags/new-checkout', ['name' => 'Second'], flagToken($user), ['If-Match' => $stale])->assertPreconditionFailed();
});

it('requires the flags:write ability', function (): void {
    $user = User::factory()->create();
    flagIn(projectWithEnvironments($user));

    mergePatch('/v1/projects/checkout/flags/new-checkout', ['name' => 'Renamed'], flagToken($user, ['flags:read']), ['If-Match' => flagTag()])
        ->assertProblem(403);
});
