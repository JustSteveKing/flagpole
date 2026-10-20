<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Environments;

use App\Http\Resources\EnvironmentResource;
use App\Models\Environment;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Environments')]
#[Endpoint(
    title: 'Show Environment',
    description: 'Fetch one environment by its key. Use it before renaming an environment: the response\'s ETag goes in If-Match on the update. Returns the environment, or 304 with no body when If-None-Match still matches.',
)]
#[UrlParam('project', type: 'string', description: 'The project key.', required: true, example: 'checkout')]
#[UrlParam('environment', type: 'string', description: 'The environment key, unique within the project.', required: true, example: 'production')]
#[Authenticated]
#[Response(content: ['data' => [
    'key' => 'production',
    'name' => 'Production',
    'created_at' => '2026-10-21T09:00:00+00:00',
    'updated_at' => '2026-10-21T09:00:00+00:00',
]], status: 200, description: 'The environment, with its ETag.')]
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
    description: 'Token is missing the projects:read ability.',
)]
final class ShowEnvironmentController
{
    public function __invoke(Project $project, Environment $environment): JsonResponse
    {
        return EnvironmentResource::make($environment)->response();
    }
}
