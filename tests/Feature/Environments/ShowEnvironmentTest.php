<?php

declare(strict_types=1);

use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows an environment with its own ETag, not its project\'s', function (): void {
    $user = User::factory()->create();
    $environment = Environment::factory()->for(projectFor($user))->create(['key' => 'production']);

    $this->withToken(ownerToken($user))
        ->getJson('/v1/projects/checkout/environments/production')
        ->assertOk()
        ->assertJsonPath('data.key', 'production')
        ->assertETag($environment->entityTag()?->toString(), weak: false);
});

it('answers 404 for an environment of another project', function (): void {
    $user = User::factory()->create();
    projectFor($user);
    Environment::factory()->for(projectFor($user, 'other'))->create(['key' => 'production']);

    $this->withToken(ownerToken($user))
        ->getJson('/v1/projects/checkout/environments/production')
        ->assertProblem(404);
});

it('requires authentication', function (): void {
    $this->getJson('/v1/projects/checkout/environments/production')->assertProblem(401);
});

it('requires the projects:read ability', function (): void {
    $user = User::factory()->create();
    Environment::factory()->for(projectFor($user))->create(['key' => 'production']);

    $this->withToken(ownerToken($user, ['auth:me']))
        ->getJson('/v1/projects/checkout/environments/production')
        ->assertProblem(403);
});
