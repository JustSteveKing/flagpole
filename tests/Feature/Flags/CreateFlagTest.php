<?php

declare(strict_types=1);
use App\Models\Flag;
use App\Models\FlagSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a boolean flag, disabled everywhere and serving off, and bumps every environment', function (): void {
    $user = User::factory()->create();
    $project = projectWithEnvironments($user);
    $before = environmentVersions($project);

    $this->withToken(flagToken($user))
        ->postJson('/v1/projects/checkout/flags', ['key' => 'new-checkout', 'name' => 'New checkout', 'type' => 'boolean'])
        ->assertCreated()
        ->assertJsonPath('data.key', 'new-checkout')
        ->assertJsonPath('data.type', 'boolean')
        ->assertJsonPath('data.variants', [['name' => 'on', 'value' => true], ['name' => 'off', 'value' => false]]);

    $settings = FlagSetting::query()->get();

    expect($settings)->toHaveCount(2)
        ->and($settings->every(fn(FlagSetting $setting): bool => false === $setting->enabled && 'off' === $setting->off_variant && ['variant' => 'off'] === $setting->default && [] === $setting->rules))->toBeTrue()
        ->and(environmentVersions($project))->toBe(array_map(fn(int $version): int => $version + 1, $before));
});

it('creates a flag of each other type, starting on its first variant', function (string $type, array $variants): void {
    $user = User::factory()->create();
    projectWithEnvironments($user);

    $this->withToken(flagToken($user))
        ->postJson('/v1/projects/checkout/flags', ['key' => 'typed', 'name' => 'Typed', 'type' => $type, 'variants' => $variants])
        ->assertCreated()
        ->assertJsonPath('data.variants.0.name', $variants[0]['name']);

    expect(FlagSetting::query()->first()?->off_variant)->toBe($variants[0]['name']);
})->with([
    'string' => ['string', [['name' => 'blue', 'value' => '#0055ff'], ['name' => 'green', 'value' => '#00aa55']]],
    'integer' => ['integer', [['name' => 'small', 'value' => 10], ['name' => 'large', 'value' => 100]]],
    'float' => ['float', [['name' => 'low', 'value' => 0.1], ['name' => 'high', 'value' => 2]]],
    'object' => ['object', [['name' => 'a', 'value' => ['limit' => 5]], ['name' => 'b', 'value' => []]]],
]);

it('keeps an empty object an object', function (): void {
    $user = User::factory()->create();
    projectWithEnvironments($user);

    $this->withToken(flagToken($user))
        ->postJson('/v1/projects/checkout/flags', ['key' => 'config', 'name' => 'Config', 'type' => 'object', 'variants' => [['name' => 'empty', 'value' => []], ['name' => 'full', 'value' => ['a' => 1]]]])
        ->assertCreated();

    $content = $this->withToken(flagToken($user))->get('/v1/projects/checkout/flags/config', ['Accept' => 'application/json'])->getContent();

    expect($content)->toContain('"value":{}');
});

it('refuses variants on a boolean flag, and requires them on the others', function (): void {
    $user = User::factory()->create();
    projectWithEnvironments($user);

    $this->withToken(flagToken($user))
        ->postJson('/v1/projects/checkout/flags', ['key' => 'a', 'name' => 'A', 'type' => 'boolean', 'variants' => Flag::BOOLEAN_VARIANTS])
        ->assertProblem(422)
        ->assertJsonPath('errors.variants.0', __('api.flags.boolean_variants'));

    $this->withToken(flagToken($user))
        ->postJson('/v1/projects/checkout/flags', ['key' => 'b', 'name' => 'B', 'type' => 'string'])
        ->assertProblem(422)
        ->assertJsonValidationErrorFor('variants');
});

it('refuses a variant value of the wrong type', function (string $type, mixed $value): void {
    $user = User::factory()->create();
    projectWithEnvironments($user);

    $this->withToken(flagToken($user))
        ->postJson('/v1/projects/checkout/flags', ['key' => 'typed', 'name' => 'Typed', 'type' => $type, 'variants' => [['name' => 'a', 'value' => $value], ['name' => 'b', 'value' => $value]]])
        ->assertProblem(422)
        ->assertJsonPath('errors', fn(array $errors): bool => isset($errors['variants.0.value']));
})->with([
    'integer given 1.5' => ['integer', 1.5],
    'string given a number' => ['string', 5],
    'object given a list' => ['object', [1, 2]],
    'float given a string' => ['float', '1.5'],
]);

it('refuses repeated variant names, too few variants, and a taken key', function (): void {
    $user = User::factory()->create();
    flagIn(projectWithEnvironments($user), 'taken');

    $this->withToken(flagToken($user))
        ->postJson('/v1/projects/checkout/flags', ['key' => 'a', 'name' => 'A', 'type' => 'string', 'variants' => [['name' => 'x', 'value' => '1'], ['name' => 'x', 'value' => '2']]])
        ->assertProblem(422);

    $this->withToken(flagToken($user))
        ->postJson('/v1/projects/checkout/flags', ['key' => 'b', 'name' => 'B', 'type' => 'string', 'variants' => [['name' => 'x', 'value' => '1']]])
        ->assertProblem(422);

    $this->withToken(flagToken($user))
        ->postJson('/v1/projects/checkout/flags', ['key' => 'taken', 'name' => 'Taken', 'type' => 'boolean'])
        ->assertProblem(422)
        ->assertJsonPath('errors.key.0', __('api.flags.key_taken'));
});

it('requires authentication', function (): void {
    $this->postJson('/v1/projects/checkout/flags', [])->assertProblem(401);
});

it('requires the flags:write ability', function (): void {
    $user = User::factory()->create();
    projectWithEnvironments($user);

    $this->withToken(flagToken($user, ['flags:read']))
        ->postJson('/v1/projects/checkout/flags', ['key' => 'a', 'name' => 'A', 'type' => 'boolean'])
        ->assertProblem(403);
});

it('does not store a refusal for flags:write, so a retry with the ability creates the flag', function (): void {
    $user = User::factory()->create();
    projectWithEnvironments($user);
    $body = ['key' => 'new-checkout', 'name' => 'New checkout', 'type' => 'boolean'];

    $this->withToken(flagToken($user, ['flags:read']))
        ->withHeader('Idempotency-Key', 'flag-retry-1')
        ->postJson('/v1/projects/checkout/flags', $body)
        ->assertProblem(403);

    $this->app['auth']->forgetGuards();

    $this->withToken(flagToken($user))
        ->withHeader('Idempotency-Key', 'flag-retry-1')
        ->postJson('/v1/projects/checkout/flags', $body)
        ->assertCreated();

    expect(Flag::query()->count())->toBe(1);
});
