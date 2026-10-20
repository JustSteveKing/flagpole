<?php

declare(strict_types=1);

use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('adds an environment to a project', function (): void {
    $user = User::factory()->create();
    $project = projectFor($user);

    $this->withToken(ownerToken($user))
        ->postJson('/v1/projects/checkout/environments', ['key' => 'staging', 'name' => 'Staging'])
        ->assertCreated()
        ->assertJsonPath('data.key', 'staging');

    expect($project->environments()->where('key', 'staging')->exists())->toBeTrue();
});

it('refuses a key the project already uses, and allows one another project uses', function (): void {
    $user = User::factory()->create();
    Environment::factory()->for(projectFor($user))->create(['key' => 'staging']);
    Environment::factory()->for(projectFor($user, 'other'))->create(['key' => 'qa']);

    $this->withToken(ownerToken($user))
        ->postJson('/v1/projects/checkout/environments', ['key' => 'staging', 'name' => 'Staging'])
        ->assertProblem(422)
        ->assertJsonPath('errors.key.0', __('api.environments.key_taken'));

    $this->withToken(ownerToken($user))
        ->postJson('/v1/projects/checkout/environments', ['key' => 'qa', 'name' => 'QA'])
        ->assertCreated();
});

it('refuses a malformed key', function (): void {
    $user = User::factory()->create();
    projectFor($user);

    $this->withToken(ownerToken($user))
        ->postJson('/v1/projects/checkout/environments', ['key' => 'Staging Two', 'name' => 'Staging'])
        ->assertProblem(422)
        ->assertJsonPath('errors.key.0', __('api.keys.format'));
});

it('requires authentication', function (): void {
    $this->postJson('/v1/projects/checkout/environments', [])->assertProblem(401);
});

it('requires the projects:write ability', function (): void {
    $user = User::factory()->create();
    projectFor($user);

    $this->withToken(ownerToken($user, ['projects:read']))
        ->postJson('/v1/projects/checkout/environments', ['key' => 'staging', 'name' => 'Staging'])
        ->assertProblem(403);
});

it('gives every flag in the project, archived ones included, settings in the new environment', function (): void {
    $user = User::factory()->create();
    $project = projectWithEnvironments($user);
    flagIn($project, 'live');
    flagIn($project, 'old')->forceFill(['archived_at' => now()])->save();

    $this->withToken(ownerToken($user))
        ->postJson('/v1/projects/checkout/environments', ['key' => 'staging', 'name' => 'Staging'])
        ->assertCreated();

    $staging = $project->environments()->where('key', 'staging')->sole();

    expect($staging->flagSettings()->count())->toBe(2)
        ->and($staging->flagSettings()->where('enabled', true)->exists())->toBeFalse();
});
