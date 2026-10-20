<?php

declare(strict_types=1);
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists a flag\'s settings in every environment, by environment key', function (): void {
    $user = User::factory()->create();
    flagIn(projectWithEnvironments($user));

    $this->withToken(flagToken($user))
        ->getJson('/v1/projects/checkout/flags/new-checkout/settings')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.environment', 'development')
        ->assertJsonPath('1.environment', 'production')
        ->assertJsonPath('0.enabled', false);
});

it('pages by environment key', function (): void {
    $user = User::factory()->create();
    $project = projectWithEnvironments($user);

    foreach (range(1, 26) as $number) {
        $project->environments()->create(['key' => sprintf('env-%02d', $number), 'name' => "Environment {$number}"]);
    }

    flagIn($project);

    $first = $this->withToken(flagToken($user))->getJson('/v1/projects/checkout/flags/new-checkout/settings')->assertOk()->assertJsonCount(25);
    preg_match('/<([^>]+)>; rel="next"/', (string) $first->headers->get('Link'), $next);

    $this->withToken(flagToken($user))
        ->getJson($next[1])
        ->assertOk()
        ->assertJsonCount(3)
        ->assertJsonPath('2.environment', 'production');
});

it('requires the flags:read ability', function (): void {
    $user = User::factory()->create();
    flagIn(projectWithEnvironments($user));

    $this->withToken(flagToken($user, ['projects:read']))
        ->getJson('/v1/projects/checkout/flags/new-checkout/settings')
        ->assertProblem(403);
});
