<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Environments;

use App\Models\Environment;
use App\Models\Project;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Environments')]
#[Endpoint(
    title: 'Delete Environment',
    description: 'Delete an environment with its flag settings, their history and its SDK keys. Use it when a deployment is retired, because every application using one of its SDK keys stops getting flags at once. Returns 204 with no body.',
)]
#[Authenticated]
#[UrlParam('project', type: 'string', description: 'The project key.', required: true, example: 'checkout')]
#[UrlParam('environment', type: 'string', description: 'The environment key, unique within the project.', required: true, example: 'production')]
#[ScribeResponse(content: null, status: 204, description: 'Environment deleted.')]
#[ScribeResponse(
    content: [
        'type' => 'https://apiguide.dev/errors/resource-not-found',
        'title' => 'Resource Not Found',
        'status' => 404,
        'detail' => 'The resource was not found.',
    ],
    status: 404,
    description: 'No project with this key belongs to the authenticated user, or it has no environment with this key.',
)]
#[ScribeResponse(
    content: [
        'type' => 'https://apiguide.dev/errors/unauthorized',
        'title' => 'Unauthorized',
        'status' => 401,
        'detail' => 'Unauthenticated.',
    ],
    status: 401,
    description: 'Authentication failed.',
)]
#[ScribeResponse(
    content: [
        'type' => 'https://apiguide.dev/errors/forbidden',
        'title' => 'Forbidden',
        'status' => 403,
        'detail' => 'Forbidden.',
    ],
    status: 403,
    description: 'Token is missing the projects:write ability.',
)]
final class DeleteEnvironmentController
{
    public function __invoke(Project $project, Environment $environment, #[CurrentUser] User $user): Response
    {
        $environment->delete();

        SecurityAudit::log('environments.deleted', [
            'user_id' => $user->id,
            'project_id' => $project->id,
            'environment_id' => $environment->id,
        ]);

        return response()->noContent();
    }
}
