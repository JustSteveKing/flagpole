<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Flags;

use App\Flags\Actor;
use App\Flags\FlagWriter;
use App\Http\Resources\FlagResource;
use App\Models\Flag;
use App\Models\Project;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Flags')]
#[Endpoint(
    title: 'Restore Flag',
    description: 'Bring an archived flag back into evaluation with its settings exactly as they were. Use it when an archived flag is needed again. Send the ETag from Show Flag in If-Match. Returns the restored flag.',
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
]], status: 200, description: 'The flag after the change.')]
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
        'type' => 'https://apiguide.dev/errors/precondition-failed',
        'title' => 'Precondition Failed',
        'status' => 412,
        'detail' => 'The resource has changed since you last fetched it. Fetch it again and retry with its current ETag.',
    ],
    status: 412,
    description: 'If-Match does not match the current ETag: someone else changed it first.',
)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/precondition-required',
        'title' => 'Precondition Required',
        'status' => 428,
        'detail' => 'This request must be conditional. Fetch the resource, then retry with its ETag in If-Match.',
    ],
    status: 428,
    description: 'The request has no If-Match.',
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
    description: 'Token is missing the flags:write ability.',
)]
final class RestoreFlagController
{
    public function __invoke(Project $project, Flag $flag, FlagWriter $flags, #[CurrentUser] User $user): JsonResponse
    {
        return FlagResource::make($flags->restore($flag, Actor::of($user)))->response();
    }
}
