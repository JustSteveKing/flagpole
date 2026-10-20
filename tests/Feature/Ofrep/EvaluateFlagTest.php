<?php

declare(strict_types=1);

use App\Flags\FlagType;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->project = projectWithEnvironments(User::factory()->create());
    $this->flag = flagIn($this->project);
    $this->key = sdkKey($this->project);
});

it('evaluates a flag in the environment of the key', function (): void {
    setSettings($this->flag, 'production', ['enabled' => true, 'rules' => [[
        'conditions' => [['attribute' => 'email', 'operator' => 'ends_with', 'values' => ['@example.com']]],
        'serve' => ['variant' => 'on'],
    ]]]);

    ofrep('flags/new-checkout', $this->key, '{"context":{"email":"jane@example.com"}}')
        ->assertOk()
        ->assertExactJson(['key' => 'new-checkout', 'value' => true, 'variant' => 'on', 'reason' => 'TARGETING_MATCH']);

    // Development is untouched, so a development key gets the starting state.
    ofrep('flags/new-checkout', sdkKey($this->project, 'development'), '{"context":{"email":"jane@example.com"}}')
        ->assertOk()
        ->assertJsonPath('reason', 'DISABLED');
});

it('keeps a whole-number float a float', function (): void {
    $ratio = flagIn($this->project, 'ratio', FlagType::Float, [['name' => 'one', 'value' => 1.0], ['name' => 'half', 'value' => 0.5]]);

    expect(ofrep('flags/ratio', $this->key)->assertOk()->getContent())->toContain('"value":1.0');
    unset($ratio);
});

it('answers FLAG_NOT_FOUND for a missing or archived flag', function (): void {
    $this->flag->forceFill(['archived_at' => now()])->save();

    foreach (['missing', 'new-checkout'] as $key) {
        ofrep("flags/{$key}", $this->key)
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['key' => $key, 'errorCode' => 'FLAG_NOT_FOUND', 'errorDetails' => __('ofrep.flag_not_found', ['key' => $key])]);
    }
});

it('answers TARGETING_KEY_MISSING when a split is reached without one', function (): void {
    setSettings($this->flag, 'production', ['enabled' => true, 'default' => ['split' => [['variant' => 'on', 'weight' => 5000], ['variant' => 'off', 'weight' => 5000]]]]);

    ofrep('flags/new-checkout', $this->key)
        ->assertStatus(400)
        ->assertJsonPath('errorCode', 'TARGETING_KEY_MISSING');

    ofrep('flags/new-checkout', $this->key, '{"context":{"targetingKey":"user-2"}}')
        ->assertOk()
        ->assertJsonPath('reason', 'SPLIT');
});

it('answers INVALID_CONTEXT and PARSE_ERROR for a bad body', function (string $body, string $code): void {
    ofrep('flags/new-checkout', $this->key, $body)
        ->assertStatus(400)
        ->assertJsonPath('key', 'new-checkout')
        ->assertJsonPath('errorCode', $code);
})->with([
    'no context' => ['{}', 'INVALID_CONTEXT'],
    'a context that is a list' => ['{"context":[1,2]}', 'INVALID_CONTEXT'],
    'a context that is a string' => ['{"context":"x"}', 'INVALID_CONTEXT'],
    'a numeric targetingKey' => ['{"context":{"targetingKey":42}}', 'INVALID_CONTEXT'],
    'a body that is not an object' => ['[]', 'INVALID_CONTEXT'],
    'not JSON' => ['{"context":', 'PARSE_ERROR'],
]);

it('translates errorDetails and never errorCode', function (): void {
    ofrep('flags/missing', $this->key, headers: ['Accept-Language' => 'es'])
        ->assertNotFound()
        ->assertHeader('Content-Language', 'es')
        ->assertJsonPath('errorCode', 'FLAG_NOT_FOUND')
        ->assertJsonPath('errorDetails', 'No hay ningún flag missing en este entorno.');
});

it('answers an unexpected failure with OFREP\'s 500 body', function (): void {
    // Variants with no names cannot be stored through the API; written directly, they make the
    // evaluator fail in a way nothing anticipates.
    $this->flag->newQuery()->whereKey($this->flag->id)->update(['variants' => json_encode([['value' => true]])]);

    ofrep('flags/new-checkout', $this->key)
        ->assertStatus(500)
        ->assertExactJson(['errorDetails' => __('ofrep.server_error')]);
});

it('refuses requests without a working SDK key, with Kit\'s problem details', function (): void {
    $this->call('POST', '/ofrep/v1/evaluate/flags/new-checkout', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], '{"context":{}}')
        ->assertProblem(401);

    ofrep('flags/new-checkout', 'nonsense')->assertProblem(401);
    ofrep('flags/new-checkout', sdkKey($this->project, 'production', now()->subMinute()))->assertProblem(401);

    $user = $this->project->user;
    ofrep('flags/new-checkout', $user->createToken('session', ['*'])->plainTextToken)->assertProblem(403);
    ofrep('flags/new-checkout', ownerToken($user, ['projects:read', 'flags:read']))->assertProblem(403);
});

it('refuses a body that is not JSON by its Content-Type', function (): void {
    $this->call('POST', '/ofrep/v1/evaluate/flags/new-checkout', [], [], [], ['CONTENT_TYPE' => 'text/plain', 'HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => "Bearer {$this->key}"], 'context')
        ->assertProblem(415);
});

it('cannot reach another project\'s flags', function (): void {
    $other = projectWithEnvironments(User::factory()->create(), 'other');
    flagIn($other, 'secret');

    ofrep('flags/secret', $this->key)->assertNotFound();
    expect(Project::query()->count())->toBe(2);
});
