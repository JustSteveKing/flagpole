<?php

declare(strict_types=1);

use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renames an environment, checking If-Match against the environment and never its project', function (): void {
    $user = User::factory()->create();
    $project = projectFor($user);
    $environment = Environment::factory()->for($project)->create(['key' => 'production', 'name' => 'Production']);
    $uri = '/v1/projects/checkout/environments/production';

    mergePatch($uri, ['name' => 'Live'], ownerToken($user), ['If-Match' => (string) $project->entityTag()?->toString()])
        ->assertPreconditionFailed();

    mergePatch($uri, ['name' => 'Live'], ownerToken($user), ['If-Match' => (string) $environment->entityTag()?->toString()])
        ->assertOk()
        ->assertJsonPath('data.name', 'Live');
});

it('refuses a different key', function (): void {
    $user = User::factory()->create();
    $environment = Environment::factory()->for(projectFor($user))->create(['key' => 'production']);

    mergePatch('/v1/projects/checkout/environments/production', ['key' => 'live'], ownerToken($user), ['If-Match' => (string) $environment->entityTag()?->toString()])
        ->assertProblem(422)
        ->assertJsonPath('errors.key.0', __('api.keys.immutable'));
});

it('requires If-Match', function (): void {
    $user = User::factory()->create();
    Environment::factory()->for(projectFor($user))->create(['key' => 'production']);

    mergePatch('/v1/projects/checkout/environments/production', ['name' => 'Live'], ownerToken($user))
        ->assertProblem(428);
});

it('requires authentication', function (): void {
    $this->patchJson('/v1/projects/checkout/environments/production', [])->assertProblem(401);
});

it('requires the projects:write ability', function (): void {
    $user = User::factory()->create();
    $environment = Environment::factory()->for(projectFor($user))->create(['key' => 'production']);

    mergePatch('/v1/projects/checkout/environments/production', ['name' => 'Live'], ownerToken($user, ['projects:read']), ['If-Match' => (string) $environment->entityTag()?->toString()])
        ->assertProblem(403);
});
