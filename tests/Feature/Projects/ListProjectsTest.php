<?php

declare(strict_types=1);
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists the owner\'s projects by key, a page at a time', function (): void {
    $user = User::factory()->create();
    Project::factory()->for($user)->create(['key' => 'web']);
    Project::factory()->for($user)->create(['key' => 'api']);
    Project::factory()->create(['key' => 'someone-elses']);

    $this->withToken(ownerToken($user))
        ->getJson('/v1/projects')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.key', 'api')
        ->assertJsonPath('1.key', 'web')
        ->assertLinkHeader('first');
});

it('requires authentication', function (): void {
    $this->getJson('/v1/projects')->assertProblem(401);
});

it('requires the projects:read ability', function (): void {
    $this->withToken(ownerToken(User::factory()->create(), ['auth:me']))
        ->getJson('/v1/projects')
        ->assertProblem(403);
});
