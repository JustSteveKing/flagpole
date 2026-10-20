<?php

declare(strict_types=1);

use App\Models\Environment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a project with development and production environments', function (): void {
    $user = User::factory()->create();

    $this->withToken(ownerToken($user))
        ->postJson('/v1/projects', ['key' => 'checkout', 'name' => 'Checkout'])
        ->assertCreated()
        ->assertJsonPath('data.key', 'checkout')
        ->assertJsonPath('data.name', 'Checkout')
        ->assertJsonMissingPath('data.id');

    $project = $user->projects()->sole();

    expect($project->environments()->orderBy('key')->pluck('key')->all())->toBe(['development', 'production']);
});

it('creates nothing twice when a request is retried with its Idempotency-Key', function (): void {
    $user = User::factory()->create();
    $headers = ['Idempotency-Key' => '"8e03978e-40d5-43e8-bc93-6894a57f9324"'];

    $first = $this->withToken(ownerToken($user))->postJson('/v1/projects', ['key' => 'checkout', 'name' => 'Checkout'], $headers);
    $retry = $this->withToken(ownerToken($user))->postJson('/v1/projects', ['key' => 'checkout', 'name' => 'Checkout'], $headers);

    $retry->assertIdempotentReplay($first);
    expect(Project::query()->count())->toBe(1)
        ->and(Environment::query()->count())->toBe(2);
});

it('refuses a malformed key', function (string $key): void {
    $this->withToken(ownerToken(User::factory()->create()))
        ->postJson('/v1/projects', ['key' => $key, 'name' => 'Checkout'])
        ->assertProblem(422)
        ->assertJsonPath('errors.key.0', __('api.keys.format'));
})->with(['Checkout', 'check_out', 'check--out', '-checkout', 'checkout-']);

it('refuses a key the owner already uses, and allows one another owner uses', function (): void {
    $user = User::factory()->create();
    Project::factory()->for($user)->create(['key' => 'checkout']);
    Project::factory()->create(['key' => 'shared']);

    $this->withToken(ownerToken($user))
        ->postJson('/v1/projects', ['key' => 'checkout', 'name' => 'Checkout'])
        ->assertProblem(422)
        ->assertJsonPath('errors.key.0', __('api.projects.key_taken'));

    $this->withToken(ownerToken($user))
        ->postJson('/v1/projects', ['key' => 'shared', 'name' => 'Shared'])
        ->assertCreated();
});

it('requires authentication', function (): void {
    $this->postJson('/v1/projects', [])->assertProblem(401);
});

it('requires the projects:write ability', function (): void {
    $this->withToken(ownerToken(User::factory()->create(), ['projects:read']))
        ->postJson('/v1/projects', ['key' => 'checkout', 'name' => 'Checkout'])
        ->assertProblem(403);
});
