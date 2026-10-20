<?php

declare(strict_types=1);
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function currentProjectTag(): string
{
    return (string) Project::query()->sole()->entityTag()?->toString();
}

it('renames a project with a merge patch and a current If-Match', function (): void {
    $user = User::factory()->create();
    Project::factory()->for($user)->create(['key' => 'checkout', 'name' => 'Checkout']);
    $before = currentProjectTag();

    mergePatch('/v1/projects/checkout', ['name' => 'Checkout flow'], ownerToken($user), ['If-Match' => $before])
        ->assertOk()
        ->assertJsonPath('data.name', 'Checkout flow')
        ->assertAcceptsPatch();

    expect(currentProjectTag())->not->toBe($before);
});

it('accepts the current key sent back, and refuses a different one', function (): void {
    $user = User::factory()->create();
    Project::factory()->for($user)->create(['key' => 'checkout']);

    mergePatch('/v1/projects/checkout', ['key' => 'checkout', 'name' => 'Same key'], ownerToken($user), ['If-Match' => currentProjectTag()])
        ->assertOk();

    mergePatch('/v1/projects/checkout', ['key' => 'basket'], ownerToken($user), ['If-Match' => currentProjectTag()])
        ->assertProblem(422)
        ->assertJsonPath('errors.key.0', __('api.keys.immutable'));
});

it('refuses removing the name', function (): void {
    $user = User::factory()->create();
    Project::factory()->for($user)->create(['key' => 'checkout']);

    mergePatch('/v1/projects/checkout', ['name' => null], ownerToken($user), ['If-Match' => currentProjectTag()])
        ->assertProblem(422);
});

it('requires If-Match, and refuses a stale one', function (): void {
    $user = User::factory()->create();
    Project::factory()->for($user)->create(['key' => 'checkout', 'name' => 'Checkout']);
    $stale = currentProjectTag();

    mergePatch('/v1/projects/checkout', ['name' => 'First'], ownerToken($user))
        ->assertProblemType('https://apiguide.dev/errors/precondition-required', 428);

    mergePatch('/v1/projects/checkout', ['name' => 'First'], ownerToken($user), ['If-Match' => $stale])->assertOk();
    mergePatch('/v1/projects/checkout', ['name' => 'Second'], ownerToken($user), ['If-Match' => $stale])
        ->assertPreconditionFailed();

    expect(Project::query()->sole()->name)->toBe('First');
});

it('refuses plain JSON', function (): void {
    $user = User::factory()->create();
    Project::factory()->for($user)->create(['key' => 'checkout']);

    $this->withToken(ownerToken($user))
        ->patchJson('/v1/projects/checkout', ['name' => 'Checkout'], ['If-Match' => currentProjectTag()])
        ->assertProblem(415);
});

it('requires authentication', function (): void {
    $this->patchJson('/v1/projects/checkout', [])->assertProblem(401);
});

it('requires the projects:write ability', function (): void {
    $user = User::factory()->create();
    Project::factory()->for($user)->create(['key' => 'checkout']);

    mergePatch('/v1/projects/checkout', ['name' => 'Checkout'], ownerToken($user, ['projects:read']), ['If-Match' => currentProjectTag()])
        ->assertProblem(403);
});
