<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Projects;

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

#[Group(name: 'Projects')]
#[Endpoint(
    title: 'Delete Project',
    description: 'Delete a project and everything in it: environments, flags, settings, history and SDK keys. Use it only when the project is retired, because every application using one of its SDK keys stops getting flags at once. Returns 204 with no body.',
)]
#[Authenticated]
#[UrlParam('project', type: 'string', description: 'The project key.', required: true, example: 'checkout')]
#[ScribeResponse(content: null, status: 204, description: 'Project deleted.')]
#[ScribeResponse(
    content: [
        'type' => 'https://apiguide.dev/errors/resource-not-found',
        'title' => 'Resource Not Found',
        'status' => 404,
        'detail' => 'The resource was not found.',
    ],
    status: 404,
    description: 'No project with this key belongs to the authenticated user.',
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
final class DeleteProjectController
{
    public function __invoke(Project $project, #[CurrentUser] User $user): Response
    {
        $project->delete();

        SecurityAudit::log('projects.deleted', [
            'user_id' => $user->id,
            'project_id' => $project->id,
        ]);

        return response()->noContent();
    }
}
