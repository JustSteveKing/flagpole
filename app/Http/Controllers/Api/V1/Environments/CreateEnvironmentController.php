<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Environments;

use App\Http\Payloads\V1\CreateEnvironmentPayload;
use App\Http\Requests\Environments\CreateEnvironmentRequest;
use App\Http\Resources\EnvironmentResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Environments')]
#[Endpoint(
    title: 'Create Environment',
    description: 'Add an environment to a project, such as staging. Use it when an application gains a deployment that needs its own flag settings; send an Idempotency-Key so a retry does not create it twice. Returns the environment; every flag in the project starts disabled in it.',
)]
#[UrlParam('project', type: 'string', description: 'The project key.', required: true, example: 'checkout')]
#[Authenticated]
#[BodyParam('key', type: 'string', description: 'Lowercase letters and digits in runs separated by single hyphens, at most 64 characters, unique within the project.', required: true, example: 'staging')]
#[BodyParam('name', type: 'string', description: 'A name for people to read, up to 255 characters.', required: true, example: 'Staging')]
#[Response(content: ['data' => [
    'key' => 'production',
    'name' => 'Production',
    'created_at' => '2026-10-21T09:00:00+00:00',
    'updated_at' => '2026-10-21T09:00:00+00:00',
]], status: 201, description: 'Environment created.')]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/validation-failed',
        'title' => 'Validation Failed',
        'status' => 422,
        'detail' => 'Keys are lowercase letters and digits, in runs separated by single hyphens.',
        'errors' => ['key' => ['Keys are lowercase letters and digits, in runs separated by single hyphens.']],
    ],
    status: 422,
    description: 'The key is malformed or already used in this project, or the name is missing.',
)]
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
    description: 'Token is missing the projects:write ability.',
)]
final class CreateEnvironmentController
{
    public function __invoke(CreateEnvironmentRequest $request, Project $project): JsonResponse
    {
        $payload = CreateEnvironmentPayload::fromRequest($request);

        $environment = $project->environments()->create(['key' => $payload->key, 'name' => $payload->name]);

        return EnvironmentResource::make($environment)->response()->setStatusCode(201);
    }
}
