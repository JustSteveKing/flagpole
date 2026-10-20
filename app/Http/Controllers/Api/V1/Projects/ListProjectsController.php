<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use JustSteveKing\Toolbox\Pagination\CursorPage;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;

#[Group(name: 'Projects')]
#[Endpoint(
    title: 'List Projects',
    description: 'List the authenticated user\'s projects, ordered by key, 25 to a page. Use it to find a project\'s key, which every other project URL is built from. Returns an array of projects; follow the Link header\'s rel="next" for the next page.',
)]
#[Authenticated]
#[QueryParam('cursor', type: 'string', description: 'Opaque cursor from a Link header. Omit it for the first page.', required: false, example: null)]
#[Response(content: [[
    'key' => 'checkout',
    'name' => 'Checkout',
    'created_at' => '2026-10-21T09:00:00+00:00',
    'updated_at' => '2026-10-21T09:00:00+00:00',
]], status: 200, description: 'One page of projects.')]
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
final class ListProjectsController
{
    /**
     * @return CursorPage<int, Project>
     */
    public function __invoke(#[CurrentUser] User $user): CursorPage
    {
        return CursorPage::of(
            $user->projects()->orderBy('key')->cursorPaginate(25),
            ProjectResource::class,
        );
    }
}
