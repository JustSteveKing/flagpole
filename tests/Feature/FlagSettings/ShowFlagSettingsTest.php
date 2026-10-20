<?php

declare(strict_types=1);

use App\Models\FlagSetting;
use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the settings for one environment, with their own ETag', function (): void {
    $user = User::factory()->create();
    flagIn(projectWithEnvironments($user));
    $production = FlagSetting::query()->whereHas('environment', fn(Builder $query) => $query->where('key', 'production'))->sole();

    $this->withToken(flagToken($user))
        ->getJson('/v1/projects/checkout/flags/new-checkout/settings/production')
        ->assertOk()
        ->assertJsonPath('data.environment', 'production')
        ->assertJsonPath('data.default', ['variant' => 'off'])
        ->assertJsonPath('data.rules', [])
        ->assertETag($production->entityTag()?->toString(), weak: false);
});

it('answers 404 for an environment the project does not have', function (): void {
    $user = User::factory()->create();
    flagIn(projectWithEnvironments($user));

    $this->withToken(flagToken($user))
        ->getJson('/v1/projects/checkout/flags/new-checkout/settings/staging')
        ->assertProblem(404);
});

it('requires authentication', function (): void {
    $this->getJson('/v1/projects/checkout/flags/new-checkout/settings/production')->assertProblem(401);
});
