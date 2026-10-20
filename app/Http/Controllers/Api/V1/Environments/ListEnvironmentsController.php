<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Environments;

use App\Http\Resources\EnvironmentResource;
use App\Models\Environment;
use App\Models\Project;
use JustSteveKing\Toolbox\Pagination\CursorPage;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Environments')]
#[Endpoint(
    title: 'List Environments',
    description: 'List a project\'s environments, ordered by key, 25 to a page. Use it to find the environment keys that flag settings and SDK keys are addressed by. Returns an array of environments; follow the Link header\'s rel="next" for the next page.',
)]
#[UrlParam('project', type: 'string', description: 'The project key.', required: true, example: 'checkout')]
#[Authenticated]
#[QueryParam('cursor', type: 'string', description: 'Opaque cursor from a Link header. Omit it for the first page.', required: false, example: null)]
#[Response(content: [[
    'key' => 'production',
    'name' => 'Production',
    'created_at' => '2026-10-21T09:00:00+00:00',
    'updated_at' => '2026-10-21T09:00:00+00:00',
]], status: 200, description: 'One page of environments.')]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/resource-not-found',
        'title' => 'Resource Not Found',
        'status' => 404,
        'detail' => 'The resource was not found.',
    ],
    status: 404,
    description: 'No project with this key belongs to the authenticated user.',
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
    description: 'Token is missing the projects:read ability.',
)]
final class ListEnvironmentsController
{
    /**
     * @return CursorPage<int, Environment>
     */
    public function __invoke(Project $project): CursorPage
    {
        return CursorPage::of(
            $project->environments()->orderBy('key')->cursorPaginate(25),
            EnvironmentResource::class,
        );
    }
}
