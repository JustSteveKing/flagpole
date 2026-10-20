<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->project = projectWithEnvironments(User::factory()->create());
    $this->key = sdkKey($this->project);
});

it('evaluates every flag that is not archived, by key, with an ETag', function (): void {
    flagIn($this->project, 'zebra');
    $rollout = flagIn($this->project, 'rollout');
    flagIn($this->project, 'archived')->forceFill(['archived_at' => now()])->save();
    setSettings($rollout, 'production', ['enabled' => true, 'default' => ['split' => [['variant' => 'on', 'weight' => 10_000]]]]);

    ofrep('flags', $this->key)
        ->assertOk()
        ->assertHeader('ETag')
        ->assertExactJson(['flags' => [
            ['key' => 'rollout', 'errorCode' => 'TARGETING_KEY_MISSING', 'errorDetails' => __('ofrep.targeting_key_missing', ['key' => 'rollout'])],
            ['key' => 'zebra', 'value' => false, 'variant' => 'off', 'reason' => 'DISABLED'],
        ]]);
});

it('answers 304 with the ETag and no body while nothing has changed', function (): void {
    flagIn($this->project);
    $etag = (string) ofrep('flags', $this->key, '{"context":{"targetingKey":"user-1"}}')->headers->get('ETag');

    $response = ofrep('flags', $this->key, '{"context":{"targetingKey":"user-1"}}', ['If-None-Match' => $etag])
        ->assertStatus(304)
        ->assertHeader('ETag', $etag);

    expect($response->getContent())->toBe('');
});

it('compares If-None-Match weakly, takes a list, and never matches "*"', function (): void {
    flagIn($this->project);
    $etag = (string) ofrep('flags', $this->key)->headers->get('ETag');

    ofrep('flags', $this->key, headers: ['If-None-Match' => 'W/' . $etag])->assertStatus(304);
    ofrep('flags', $this->key, headers: ['If-None-Match' => '"stale", ' . $etag])->assertStatus(304);
    ofrep('flags', $this->key, headers: ['If-None-Match' => '*'])->assertOk();
    ofrep('flags', $this->key, headers: ['If-None-Match' => '"stale"'])->assertOk();
});

it('changes the ETag when the flags change, and only for the environment that changed', function (): void {
    $flag = flagIn($this->project);
    $development = sdkKey($this->project, 'development');
    $production = (string) ofrep('flags', $this->key)->headers->get('ETag');
    $developmentTag = (string) ofrep('flags', $development)->headers->get('ETag');

    setSettings($flag, 'production', ['enabled' => true]);

    ofrep('flags', $this->key, headers: ['If-None-Match' => $production])->assertOk()->assertJsonPath('flags.0.reason', 'STATIC');
    ofrep('flags', $development, headers: ['If-None-Match' => $developmentTag])->assertStatus(304);
});

it('changes the ETag when the context changes, but not when its keys are reordered', function (): void {
    flagIn($this->project);
    $etag = (string) ofrep('flags', $this->key, '{"context":{"plan":"pro","country":"GB","user":{"b":1,"a":2}}}')->headers->get('ETag');

    ofrep('flags', $this->key, '{"context":{"user":{"a":2,"b":1},"country":"GB","plan":"pro"}}', ['If-None-Match' => $etag])->assertStatus(304);
    ofrep('flags', $this->key, '{"context":{"plan":"free","country":"GB","user":{"b":1,"a":2}}}', ['If-None-Match' => $etag])->assertOk();
});

it('fails the whole request for a bad context, without a key', function (): void {
    ofrep('flags', $this->key, '{"context":"x"}')
        ->assertStatus(400)
        ->assertExactJson(['errorCode' => 'INVALID_CONTEXT', 'errorDetails' => __('ofrep.invalid_context')]);
});

it('answers an empty list for an environment with no flags', function (): void {
    ofrep('flags', $this->key)->assertOk()->assertExactJson(['flags' => []]);
});

it('is listed in the API catalog', function (): void {
    $this->getJson('/.well-known/api-catalog')
        ->assertOk()
        ->assertSee('/ofrep/v1', escape: false);
});
