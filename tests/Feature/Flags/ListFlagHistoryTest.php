<?php

declare(strict_types=1);

use App\Flags\FlagWriter;
use App\Models\Flag;
use App\Models\FlagChange;
use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

const HISTORY = '/v1/projects/checkout/flags/new-checkout/history';

function settingsTag(string $environment = 'production'): string
{
    return (string) Flag::query()->sole()->settings()->whereHas('environment', fn(Builder $query) => $query->where('key', $environment))->sole()->entityTag()?->toString();
}

it('records each change through the API once, with the token that made it, newest first', function (): void {
    $user = User::factory()->create();
    projectWithEnvironments($user);
    $token = $user->createToken('ci-pipeline', ['flags:read', 'flags:write'])->plainTextToken;

    $this->withToken($token)->postJson('/v1/projects/checkout/flags', ['key' => 'new-checkout', 'name' => 'New checkout', 'type' => 'boolean'])->assertCreated();
    mergePatch('/v1/projects/checkout/flags/new-checkout/settings/production', ['enabled' => true], $token, ['If-Match' => settingsTag()])->assertOk();
    // Changes nothing, so records nothing.
    mergePatch('/v1/projects/checkout/flags/new-checkout/settings/production', ['enabled' => true], $token, ['If-Match' => settingsTag()])->assertOk();

    $this->withToken($token)
        ->getJson(HISTORY)
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.action', 'flag.settings.updated')
        ->assertJsonPath('0.environment', 'production')
        ->assertJsonPath('0.token.name', 'ci-pipeline')
        ->assertJsonPath('0.before.enabled', false)
        ->assertJsonPath('0.after.enabled', true)
        ->assertJsonPath('1.action', 'flag.created')
        ->assertJsonPath('1.environment', null)
        ->assertJsonPath('1.before', null)
        ->assertJsonPath('1.after.key', 'new-checkout')
        ->assertLinkHeader('first');
});

it('narrows to one environment', function (): void {
    $user = User::factory()->create();
    $flag = flagIn(projectWithEnvironments($user));
    setSettings($flag, 'production', ['enabled' => true]);
    setSettings($flag, 'development', ['enabled' => true]);

    $this->withToken(flagToken($user))
        ->getJson(HISTORY . '?environment=development')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.environment', 'development');
});

it('keeps a revoked token\'s name, without its ID', function (): void {
    $user = User::factory()->create();
    projectWithEnvironments($user);
    $token = $user->createToken('ci-pipeline', ['flags:read', 'flags:write']);

    $this->withToken($token->plainTextToken)->postJson('/v1/projects/checkout/flags', ['key' => 'new-checkout', 'name' => 'New checkout', 'type' => 'boolean'])->assertCreated();
    PersonalAccessToken::query()->whereKey($token->accessToken->id)->delete();

    $this->withToken(flagToken($user))
        ->getJson(HISTORY)
        ->assertOk()
        ->assertJsonPath('0.token', ['id' => null, 'name' => 'ci-pipeline']);
});

it('records archiving, restoring and changes to the flag itself', function (): void {
    $user = User::factory()->create();
    $flag = flagIn(projectWithEnvironments($user));
    $writer = app(FlagWriter::class);

    $writer->update($flag, ['name' => 'Renamed']);
    $writer->archive($flag);
    $writer->restore($flag);

    expect(FlagChange::query()->orderBy('id')->pluck('action')->all())
        ->toBe(['flag.created', 'flag.updated', 'flag.archived', 'flag.restored']);
});

it('goes with the environment whose settings it records', function (): void {
    $user = User::factory()->create();
    $flag = flagIn(projectWithEnvironments($user));
    setSettings($flag, 'production', ['enabled' => true]);

    $this->withToken(ownerToken($user))->deleteJson('/v1/projects/checkout/environments/production')->assertNoContent();

    expect(FlagChange::query()->pluck('action')->all())->toBe(['flag.created']);
});

it('requires the flags:read ability', function (): void {
    $user = User::factory()->create();
    flagIn(projectWithEnvironments($user));

    $this->withToken(flagToken($user, ['projects:read']))->getJson(HISTORY)->assertProblem(403);
});
