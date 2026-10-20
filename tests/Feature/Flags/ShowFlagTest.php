<?php

declare(strict_types=1);
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows a flag with its own ETag, archived or not', function (): void {
    $user = User::factory()->create();
    $flag = flagIn(projectWithEnvironments($user));

    $this->withToken(flagToken($user))
        ->getJson('/v1/projects/checkout/flags/new-checkout')
        ->assertOk()
        ->assertJsonPath('data.key', 'new-checkout')
        ->assertETag($flag->entityTag()?->toString(), weak: false);

    $flag->forceFill(['archived_at' => now()])->save();

    $this->withToken(flagToken($user))
        ->getJson('/v1/projects/checkout/flags/new-checkout')
        ->assertOk()
        ->assertJsonPath('data.archived_at', fn(?string $value): bool => null !== $value);
});

it('answers 404 for a flag in another project', function (): void {
    $user = User::factory()->create();
    projectWithEnvironments($user);
    flagIn(projectWithEnvironments($user, 'other'));

    $this->withToken(flagToken($user))
        ->getJson('/v1/projects/checkout/flags/new-checkout')
        ->assertProblem(404);
});

it('requires authentication', function (): void {
    $this->getJson('/v1/projects/checkout/flags/new-checkout')->assertProblem(401);
});

it('requires the flags:read ability', function (): void {
    $user = User::factory()->create();
    flagIn(projectWithEnvironments($user));

    $this->withToken(flagToken($user, ['projects:read']))
        ->getJson('/v1/projects/checkout/flags/new-checkout')
        ->assertProblem(403);
});
