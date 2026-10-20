<?php

declare(strict_types=1);

use App\Models\Environment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('deletes an environment and leaves the project', function (): void {
    $user = User::factory()->create();
    Environment::factory()->for(projectFor($user))->create(['key' => 'staging']);

    $this->withToken(ownerToken($user))
        ->deleteJson('/v1/projects/checkout/environments/staging')
        ->assertNoContent();

    expect(Environment::query()->count())->toBe(0)
        ->and(Project::query()->count())->toBe(1);
});

it('requires authentication', function (): void {
    $this->deleteJson('/v1/projects/checkout/environments/staging')->assertProblem(401);
});

it('requires the projects:write ability', function (): void {
    $user = User::factory()->create();
    Environment::factory()->for(projectFor($user))->create(['key' => 'staging']);

    $this->withToken(ownerToken($user, ['projects:read']))
        ->deleteJson('/v1/projects/checkout/environments/staging')
        ->assertProblem(403);
});
