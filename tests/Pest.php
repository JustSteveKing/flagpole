<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', fn() => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something(): void
{
    // ..
}

/**
 * A bearer token for the owner, holding Flagpole's management abilities unless told otherwise.
 *
 * @param  list<string>  $abilities
 */
function ownerToken(User $user, array $abilities = ['projects:read', 'projects:write']): string
{
    return $user->createToken('test', $abilities)->plainTextToken;
}

/**
 * Sends a JSON Merge Patch (RFC 7396), which the JSON helpers cannot: they always send
 * application/json.
 *
 * @param  array<string, mixed>  $patch
 * @param  array<string, string>  $headers
 */
function mergePatch(string $uri, array $patch, string $token, array $headers = []): TestResponse
{
    $server = ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => "Bearer {$token}"];

    foreach ($headers as $name => $value) {
        $server['HTTP_' . mb_strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return test()->call('PATCH', $uri, [], [], [], $server, json_encode($patch, JSON_THROW_ON_ERROR));
}

function projectFor(User $user, string $key = 'checkout'): Project
{
    return Project::factory()->for($user)->create(['key' => $key]);
}
