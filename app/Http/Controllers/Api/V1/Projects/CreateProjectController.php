<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Payloads\V1\CreateProjectPayload;
use App\Http\Requests\Projects\CreateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;

#[Group(name: 'Projects')]
#[Endpoint(
    title: 'Create Project',
    description: 'Create a project, with development and production environments to start with. Use it once per application whose flags you manage; send an Idempotency-Key so a retry does not create it twice. Returns the project; its key cannot be changed afterwards.',
)]
#[Authenticated]
#[BodyParam('key', type: 'string', description: 'Lowercase letters and digits in runs separated by single hyphens, at most 64 characters, unique among your projects. Used in every URL for the project.', required: true, example: 'checkout')]
#[BodyParam('name', type: 'string', description: 'A name for people to read, up to 255 characters.', required: true, example: 'Checkout')]
#[Response(content: ['data' => [
    'key' => 'checkout',
    'name' => 'Checkout',
    'created_at' => '2026-10-21T09:00:00+00:00',
    'updated_at' => '2026-10-21T09:00:00+00:00',
]], status: 201, description: 'Project created, with its two starting environments.')]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/validation-failed',
        'title' => 'Validation Failed',
        'status' => 422,
        'detail' => 'Keys are lowercase letters and digits, in runs separated by single hyphens.',
        'errors' => ['key' => ['Keys are lowercase letters and digits, in runs separated by single hyphens.']],
    ],
    status: 422,
    description: 'The key is malformed or already used by another of your projects, or the name is missing.',
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
    description: 'Token is missing the projects:write ability.',
)]
final class CreateProjectController
{
    public function __invoke(CreateProjectRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        $payload = CreateProjectPayload::fromRequest($request);

        $project = DB::transaction(function () use ($user, $payload): Project {
            $project = $user->projects()->create(['key' => $payload->key, 'name' => $payload->name]);

            foreach (Project::STARTING_ENVIRONMENTS as $key => $name) {
                $project->environments()->create(['key' => $key, 'name' => $name]);
            }

            return $project;
        });

        return ProjectResource::make($project)->response()->setStatusCode(201);
    }
}
