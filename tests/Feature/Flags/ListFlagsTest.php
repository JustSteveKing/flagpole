<?php

declare(strict_types=1);
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists flags by key, leaving archived ones out unless asked for them', function (): void {
    $user = User::factory()->create();
    $project = projectWithEnvironments($user);
    flagIn($project, 'zebra');
    flagIn($project, 'apple');
    $archived = flagIn($project, 'old');
    $archived->forceFill(['archived_at' => now()])->save();

    $this->withToken(flagToken($user))
        ->getJson('/v1/projects/checkout/flags')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.key', 'apple')
        ->assertJsonPath('1.key', 'zebra');

    $this->withToken(flagToken($user))
        ->getJson('/v1/projects/checkout/flags?archived=true')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.key', 'old');
});

it('requires authentication', function (): void {
    $this->getJson('/v1/projects/checkout/flags')->assertProblem(401);
});

it('requires the flags:read ability', function (): void {
    $user = User::factory()->create();
    projectWithEnvironments($user);

    $this->withToken(flagToken($user, ['projects:read']))
        ->getJson('/v1/projects/checkout/flags')
        ->assertProblem(403);
});
