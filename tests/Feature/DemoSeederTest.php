<?php

declare(strict_types=1);

use App\Flags\SdkKeys;
use App\Models\Environment;
use App\Models\Flag;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

it('seeds a demo that evaluates', function (): void {
    $this->seed(DemoSeeder::class);

    expect(Flag::query()->count())->toBe(5)
        ->and(PersonalAccessToken::query()->where('tokenable_type', Environment::class)->count())->toBe(2);

    $key = app(SdkKeys::class)->issue(Environment::query()->where('key', 'development')->sole(), 'test', null)->plainTextToken;

    ofrep('flags/new-checkout', $key, '{"context":{"email":"jane@flagpole.test"}}')
        ->assertOk()
        ->assertJsonPath('reason', 'TARGETING_MATCH');
});

it('refuses to run in production', function (): void {
    app()->detectEnvironment(fn(): string => 'production');

    // Called directly: db:seed would stop to ask for confirmation in production first.
    app(DemoSeeder::class)->setContainer(app())->__invoke();
})->throws(LogicException::class, 'The demo seeder does not run in production.');
