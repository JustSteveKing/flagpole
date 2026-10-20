<?php

declare(strict_types=1);

use App\Flags\SdkKeys;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

function keyToken(User $user, array $abilities = ['sdk-keys:read', 'sdk-keys:write']): string
{
    return ownerToken($user, $abilities);
}

function production(): Environment
{
    return Environment::query()->where('key', 'production')->sole();
}

const KEYS = '/v1/projects/checkout/environments/production/sdk-keys';

it('creates a key that can only evaluate, never expires, and shows its secret once', function (): void {
    $user = User::factory()->create();
    projectWithEnvironments($user);

    $response = $this->withToken(keyToken($user))
        ->postJson(KEYS, ['name' => 'checkout-web production'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'checkout-web production')
        ->assertJsonPath('data.expires_at', null)
        ->assertJsonPath('meta.token_type', 'Bearer')
        ->assertJsonMissingPath('data.abilities');

    $token = PersonalAccessToken::findToken((string) $response->json('meta.token'));

    expect($token?->tokenable)->toBeInstanceOf(Environment::class)
        ->and($token?->tokenable?->is(production()))->toBeTrue()
        ->and($token?->abilities)->toBe([SdkKeys::ABILITY]);

    $this->withToken(keyToken($user))
        ->getJson(KEYS)
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonMissingPath('0.token');
});

it('takes an expiry in the future, with no maximum', function (): void {
    $user = User::factory()->create();
    projectWithEnvironments($user);

    $this->withToken(keyToken($user))
        ->postJson(KEYS, ['name' => 'long', 'expires_at' => now()->addYears(5)->toAtomString()])
        ->assertCreated();

    $this->withToken(keyToken($user))
        ->postJson(KEYS, ['name' => 'past', 'expires_at' => now()->subDay()->toAtomString()])
        ->assertProblem(422)
        ->assertJsonValidationErrorFor('expires_at');
});

it('lists an environment\'s keys newest first, and none from other environments', function (): void {
    $user = User::factory()->create();
    projectWithEnvironments($user);
    $keys = app(SdkKeys::class);
    $keys->issue(production(), 'first', null);
    $keys->issue(production(), 'second', null);
    $keys->issue(Environment::query()->where('key', 'development')->sole(), 'elsewhere', null);

    $this->withToken(keyToken($user))
        ->getJson(KEYS)
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.name', 'second');
});

it('revokes a key, and only one belonging to that environment', function (): void {
    $user = User::factory()->create();
    projectWithEnvironments($user);
    $keys = app(SdkKeys::class);
    $mine = $keys->issue(production(), 'mine', null)->accessToken;
    $other = $keys->issue(Environment::query()->where('key', 'development')->sole(), 'other', null)->accessToken;

    $this->withToken(keyToken($user))->deleteJson(KEYS . '/' . $other->id)->assertProblem(404);
    $this->withToken(keyToken($user))->deleteJson(KEYS . '/' . $mine->id)->assertNoContent();

    expect(PersonalAccessToken::query()->whereKey($mine->id)->exists())->toBeFalse()
        ->and(PersonalAccessToken::query()->whereKey($other->id)->exists())->toBeTrue();
});

it('keeps SDK keys apart from the owner\'s own tokens', function (): void {
    $user = User::factory()->create();
    projectWithEnvironments($user);
    $sdkKey = app(SdkKeys::class)->issue(production(), 'web', null)->accessToken;
    $session = $user->createToken('session', ['auth:tokens:read', 'auth:tokens:delete'])->plainTextToken;

    $this->withToken($session)
        ->getJson('/v1/auth/tokens')
        ->assertOk()
        ->assertJsonMissing(['name' => 'web']);

    $this->withToken($session)->deleteJson('/v1/auth/tokens')->assertSuccessful();

    expect(PersonalAccessToken::query()->whereKey($sdkKey->id)->exists())->toBeTrue();
});

it('gives an SDK key no access to the management API', function (): void {
    $user = User::factory()->create();
    projectWithEnvironments($user);
    $secret = app(SdkKeys::class)->issue(production(), 'web', null)->plainTextToken;

    $this->withToken($secret)->getJson('/v1/projects')->assertProblem(403);
    $this->withToken($secret)->getJson('/v1/auth/me')->assertProblem(403);
});

it('removes an environment\'s keys with the environment, and a project\'s with the project', function (): void {
    $user = User::factory()->create();
    projectWithEnvironments($user);
    $keys = app(SdkKeys::class);
    $keys->issue(production(), 'production', null);
    $keys->issue(Environment::query()->where('key', 'development')->sole(), 'development', null);

    $this->withToken(ownerToken($user))->deleteJson('/v1/projects/checkout/environments/production')->assertNoContent();
    expect(PersonalAccessToken::query()->count())->toBe(2);

    $this->withToken(ownerToken($user))->deleteJson('/v1/projects/checkout')->assertNoContent();
    expect(PersonalAccessToken::query()->where('tokenable_type', Environment::class)->count())->toBe(0);
});

it('requires authentication', function (): void {
    $this->getJson(KEYS)->assertProblem(401);
});

it('requires the sdk-keys abilities', function (): void {
    $user = User::factory()->create();
    projectWithEnvironments($user);

    $this->withToken(keyToken($user, ['projects:read']))->getJson(KEYS)->assertProblem(403);
    $this->withToken(keyToken($user, ['sdk-keys:read']))->postJson(KEYS, ['name' => 'web'])->assertProblem(403);
});
