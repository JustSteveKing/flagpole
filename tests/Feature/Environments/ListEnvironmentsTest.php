<?php

declare(strict_types=1);

use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists a project\'s environments by key', function (): void {
    $user = User::factory()->create();
    $project = projectFor($user);
    Environment::factory()->for($project)->create(['key' => 'staging']);
    Environment::factory()->for($project)->create(['key' => 'dev']);
    Environment::factory()->create(['key' => 'elsewhere']);

    $this->withToken(ownerToken($user))
        ->getJson('/v1/projects/checkout/environments')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.key', 'dev')
        ->assertJsonPath('1.key', 'staging');
});

it('answers 404 for another owner\'s project', function (): void {
    projectFor(User::factory()->create());

    $this->withToken(ownerToken(User::factory()->create()))
        ->getJson('/v1/projects/checkout/environments')
        ->assertProblem(404);
});

it('requires authentication', function (): void {
    $this->getJson('/v1/projects/checkout/environments')->assertProblem(401);
});

it('requires the projects:read ability', function (): void {
    $user = User::factory()->create();
    projectFor($user);

    $this->withToken(ownerToken($user, ['auth:me']))
        ->getJson('/v1/projects/checkout/environments')
        ->assertProblem(403);
});
