<?php

declare(strict_types=1);
use App\Models\FlagSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('archives and restores a flag, keeping its settings and bumping every environment each time', function (): void {
    $user = User::factory()->create();
    $flag = flagIn(projectWithEnvironments($user));
    $flag->settings()->update(['enabled' => true]);
    $before = environmentVersions($flag->project);

    $this->withToken(flagToken($user))
        ->postJson('/v1/projects/checkout/flags/new-checkout/archive', [], ['If-Match' => (string) $flag->fresh()?->entityTag()?->toString()])
        ->assertOk()
        ->assertJsonPath('data.archived_at', fn(?string $value): bool => null !== $value);

    $this->withToken(flagToken($user))
        ->postJson('/v1/projects/checkout/flags/new-checkout/restore', [], ['If-Match' => (string) $flag->fresh()?->entityTag()?->toString()])
        ->assertOk()
        ->assertJsonPath('data.archived_at', null);

    expect(FlagSetting::query()->where('enabled', true)->count())->toBe(2)
        ->and(environmentVersions($flag->project))->toBe(array_map(fn(int $version): int => $version + 2, $before));
});

it('requires If-Match to archive', function (): void {
    $user = User::factory()->create();
    flagIn(projectWithEnvironments($user));

    $this->withToken(flagToken($user))
        ->postJson('/v1/projects/checkout/flags/new-checkout/archive')
        ->assertProblem(428);
});

it('requires the flags:write ability', function (): void {
    $user = User::factory()->create();
    $flag = flagIn(projectWithEnvironments($user));

    $this->withToken(flagToken($user, ['flags:read']))
        ->postJson('/v1/projects/checkout/flags/new-checkout/archive', [], ['If-Match' => (string) $flag->entityTag()?->toString()])
        ->assertProblem(403);
});
