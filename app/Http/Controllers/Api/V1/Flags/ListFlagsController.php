<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Flags;

use App\Http\Resources\FlagResource;
use App\Models\Flag;
use App\Models\Project;
use Illuminate\Http\Request;
use JustSteveKing\Toolbox\Pagination\CursorPage;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Flags')]
#[Endpoint(
    title: 'List Flags',
    description: 'List a project\'s flags, ordered by key, 25 to a page. Use it to find the keys applications evaluate, or pass archived=true to list only archived flags. Returns an array of flags without their settings; follow the Link header\'s rel="next" for the next page.',
)]
#[UrlParam('project', type: 'string', description: 'The project key.', required: true, example: 'checkout')]
#[Authenticated]
#[QueryParam('archived', type: 'boolean', description: 'true lists only archived flags. Without it, archived flags are left out.', required: false, example: false)]
#[QueryParam('cursor', type: 'string', description: 'Opaque cursor from a Link header. Omit it for the first page.', required: false, example: null)]
#[Response(content: [[
    'key' => 'new-checkout',
    'name' => 'New checkout',
    'description' => 'The redesigned checkout flow.',
    'type' => 'boolean',
    'variants' => [['name' => 'on', 'value' => true], ['name' => 'off', 'value' => false]],
    'archived_at' => null,
    'created_at' => '2026-10-21T09:00:00+00:00',
    'updated_at' => '2026-10-21T09:00:00+00:00',
]], status: 200, description: 'One page of flags.')]
#[ResponseField('[].description', 'string', 'What the flag is for, or null.', nullable: true)]
#[ResponseField('[].archived_at', 'string', 'When the flag was archived, or null while it is in use.', nullable: true)]
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
    description: 'Token is missing the flags:read ability.',
)]
final class ListFlagsController
{
    /**
     * @return CursorPage<int, Flag>
     */
    public function __invoke(Request $request, Project $project): CursorPage
    {
        $archived = $request->boolean('archived');

        return CursorPage::of(
            $project->flags()
                ->when($archived, fn($query) => $query->whereNotNull('archived_at'), fn($query) => $query->whereNull('archived_at'))
                ->orderBy('key')
                ->cursorPaginate(25),
            FlagResource::class,
        );
    }
}
