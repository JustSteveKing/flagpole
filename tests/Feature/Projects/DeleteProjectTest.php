<?php

declare(strict_types=1);

use App\Models\Environment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('deletes a project and its environments', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['key' => 'checkout']);
    Environment::factory()->for($project)->create();

    $this->withToken(ownerToken($user))
        ->deleteJson('/v1/projects/checkout')
        ->assertNoContent();

    expect(Project::query()->count())->toBe(0)
        ->and(Environment::query()->count())->toBe(0);
});

it('leaves another owner\'s project alone', function (): void {
    Project::factory()->create(['key' => 'theirs']);

    $this->withToken(ownerToken(User::factory()->create()))
        ->deleteJson('/v1/projects/theirs')
        ->assertProblem(404);

    expect(Project::query()->count())->toBe(1);
});

it('requires authentication', function (): void {
    $this->deleteJson('/v1/projects/checkout')->assertProblem(401);
});

it('requires the projects:write ability', function (): void {
    $user = User::factory()->create();
    Project::factory()->for($user)->create(['key' => 'checkout']);

    $this->withToken(ownerToken($user, ['projects:read']))
        ->deleteJson('/v1/projects/checkout')
        ->assertProblem(403);
});
