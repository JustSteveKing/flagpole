<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Flags;

use App\Http\Resources\FlagResource;
use App\Models\Flag;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Flags')]
#[Endpoint(
    title: 'Show Flag',
    description: 'Fetch one flag by its key, archived or not. Use it before changing a flag: the ETag of the response goes in If-Match on the update, archive or restore. Returns the flag without its settings, or 304 with no body when If-None-Match still matches.',
)]
#[UrlParam('project', type: 'string', description: 'The project key.', required: true, example: 'checkout')]
#[UrlParam('flag', type: 'string', description: 'The flag key, unique within the project.', required: true, example: 'new-checkout')]
#[Authenticated]
#[Response(content: ['data' => [
    'key' => 'new-checkout',
    'name' => 'New checkout',
    'description' => 'The redesigned checkout flow.',
    'type' => 'boolean',
    'variants' => [['name' => 'on', 'value' => true], ['name' => 'off', 'value' => false]],
    'archived_at' => null,
    'created_at' => '2026-10-21T09:00:00+00:00',
    'updated_at' => '2026-10-21T09:00:00+00:00',
]], status: 200, description: 'The flag, with its ETag.')]
#[ResponseField('data.description', 'string', 'What the flag is for, or null.', nullable: true)]
#[ResponseField('data.archived_at', 'string', 'When the flag was archived, or null while it is in use.', nullable: true)]
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
final class ShowFlagController
{
    public function __invoke(Project $project, Flag $flag): JsonResponse
    {
        return FlagResource::make($flag)->response();
    }
}
