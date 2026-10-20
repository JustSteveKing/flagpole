<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SdkKeys;

use App\Http\Resources\SdkKeyResource;
use App\Models\Environment;
use App\Models\Project;
use JustSteveKing\Toolbox\Pagination\CursorPage;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\UrlParam;
use Laravel\Sanctum\PersonalAccessToken;

#[Group(name: 'SDK keys')]
#[Endpoint(
    title: 'List SDK Keys',
    description: 'List the SDK keys of an environment, newest first, 25 to a page. Use it to see which keys exist and when each was last used, before revoking one. Returns an array of keys without their secrets; follow the Link header\'s rel="next" for the next page.',
)]
#[UrlParam('project', type: 'string', description: 'The project key.', required: true, example: 'checkout')]
#[UrlParam('environment', type: 'string', description: 'The environment key, unique within the project.', required: true, example: 'production')]
#[Authenticated]
#[QueryParam('cursor', type: 'string', description: 'Opaque cursor from a Link header. Omit it for the first page.', required: false, example: null)]
#[Response(content: [[
    'id' => '41',
    'name' => 'checkout-web production',
    'last_used_at' => '2026-10-21T09:12:00+00:00',
    'expires_at' => null,
    'created_at' => '2026-10-21T09:00:00+00:00',
]], status: 200, description: 'One page of SDK keys.')]
#[ResponseField('[].last_used_at', 'string', 'When an application last used the key, or null if none has.', nullable: true)]
#[ResponseField('[].expires_at', 'string', 'When the key stops working, or null if it never does.', nullable: true)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/resource-not-found',
        'title' => 'Resource Not Found',
        'status' => 404,
        'detail' => 'The resource was not found.',
    ],
    status: 404,
    description: 'No project with this key belongs to the authenticated user, or it has no environment with this key.',
)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/unauthorized',
        'title' => 'Unauthorized',
        'status' => 401,
        'detail' => 'Unauthenticated.',
    ],
    status: 401,
    description: 'Authentication failed.',
)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/forbidden',
        'title' => 'Forbidden',
        'status' => 403,
        'detail' => 'Forbidden.',
    ],
    status: 403,
    description: 'Token is missing the sdk-keys:read ability.',
)]
final class ListSdkKeysController
{
    /**
     * @return CursorPage<int, PersonalAccessToken>
     */
    public function __invoke(Project $project, Environment $environment): CursorPage
    {
        return CursorPage::of(
            $environment->tokens()->orderByDesc('id')->cursorPaginate(25),
            SdkKeyResource::class,
        );
    }
}
