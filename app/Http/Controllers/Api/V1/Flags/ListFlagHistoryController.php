<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Flags;

use App\Http\Resources\FlagChangeResource;
use App\Models\Flag;
use App\Models\FlagChange;
use App\Models\Project;
use Illuminate\Contracts\Database\Query\Builder;
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
    title: 'List Flag History',
    description: 'List every change to a flag and its settings, newest first, 25 to a page: who changed what, through which token, and the value before and after. Use it to find out what changed when a flag misbehaves; pass environment to see one environment only. Returns an array of changes; follow the Link header\'s rel="next" for the next page.',
)]
#[UrlParam('project', type: 'string', description: 'The project key.', required: true, example: 'checkout')]
#[UrlParam('flag', type: 'string', description: 'The flag key, unique within the project.', required: true, example: 'new-checkout')]
#[Authenticated]
#[QueryParam('environment', type: 'string', description: 'An environment key: only changes to the settings in that environment.', required: false, example: 'production')]
#[QueryParam('cursor', type: 'string', description: 'Opaque cursor from a Link header. Omit it for the first page.', required: false, example: null)]
#[Response(content: [[
    'id' => '01jb8z6v4t9n3q2w5e7r8y0u1i',
    'action' => 'flag.settings.updated',
    'environment' => 'production',
    'token' => ['id' => '12', 'name' => 'ci-pipeline'],
    'before' => ['enabled' => true, 'off_variant' => 'off', 'default' => ['variant' => 'off'], 'rules' => []],
    'after' => ['enabled' => false, 'off_variant' => 'off', 'default' => ['variant' => 'off'], 'rules' => []],
    'created_at' => '2026-10-21T09:30:00+00:00',
]], status: 200, description: 'One page of changes.')]
#[ResponseField('[].environment', 'string', 'The environment whose settings changed, or null for a change to the flag itself.', nullable: true)]
#[ResponseField('[].token', 'object', 'The token that made the change, or null when it was not made through the API.', nullable: true)]
#[ResponseField('[].token.id', 'string', 'The ID of the token, or null once it has been deleted.', nullable: true)]
#[ResponseField('[].before', 'object', 'The flag or settings before the change, or null when the flag was created.', nullable: true)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/resource-not-found',
        'title' => 'Resource Not Found',
        'status' => 404,
        'detail' => 'The resource was not found.',
    ],
    status: 404,
    description: 'No project with this key belongs to the authenticated user, or it has no flag with this key.',
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
final class ListFlagHistoryController
{
    /**
     * @return CursorPage<int, FlagChange>
     */
    public function __invoke(Request $request, Project $project, Flag $flag): CursorPage
    {
        $environment = $request->string('environment')->value();

        return CursorPage::of(
            $flag->changes()
                ->when('' !== $environment, fn($query) => $query->whereHas('environment', fn(Builder $query) => $query->where('key', $environment)))
                ->with(['environment', 'token'])
                ->orderByDesc('id')
                ->cursorPaginate(25),
            FlagChangeResource::class,
        );
    }
}
