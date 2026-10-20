<?php

declare(strict_types=1);
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows a project with an ETag, and answers 304 while it is unchanged', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['key' => 'checkout', 'name' => 'Checkout']);

    $response = $this->withToken(ownerToken($user))
        ->getJson('/v1/projects/checkout')
        ->assertOk()
        ->assertJsonPath('data.key', 'checkout')
        ->assertETag($project->entityTag()?->toString(), weak: false);

    $this->withToken(ownerToken($user))
        ->getJson('/v1/projects/checkout', ['If-None-Match' => (string) $response->headers->get('ETag')])
        ->assertNotModified();
});

it('answers 404 for another owner\'s project, the same as a missing one', function (): void {
    Project::factory()->create(['key' => 'theirs']);
    $token = ownerToken(User::factory()->create());

    $this->withToken($token)->getJson('/v1/projects/theirs')->assertProblem(404);
    $this->withToken($token)->getJson('/v1/projects/missing')->assertProblem(404);
});

it('requires authentication', function (): void {
    $this->getJson('/v1/projects/checkout')->assertProblem(401);
});

it('requires the projects:read ability', function (): void {
    $user = User::factory()->create();
    Project::factory()->for($user)->create(['key' => 'checkout']);

    $this->withToken(ownerToken($user, ['auth:me']))
        ->getJson('/v1/projects/checkout')
        ->assertProblem(403);
});
