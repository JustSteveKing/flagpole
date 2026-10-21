<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Payloads\V1\UpdateProjectPayload;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Header;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Projects')]
#[Endpoint(
    title: 'Update Project',
    description: 'Rename a project with a JSON Merge Patch (application/merge-patch+json). Use it when only the name should change; the key is permanent. Send the ETag from Show Project in If-Match, so a change someone else made first is not overwritten. Returns the updated project.',
)]
#[UrlParam('project', type: 'string', description: 'The project key.', required: true, example: 'checkout')]
#[Authenticated]
#[Header('Content-Type', 'application/merge-patch+json')]
#[Header('If-Match', '"a1b2c3d4e5f6"')]
#[BodyParam('name', type: 'string', description: 'The new name, up to 255 characters.', required: false, example: 'Checkout flow')]
#[BodyParam('key', type: 'string', description: 'May be sent only with its current value.', required: false, example: 'checkout')]
#[Response(content: ['data' => [
    'key' => 'checkout',
    'name' => 'Checkout',
    'created_at' => '2026-10-21T09:00:00+00:00',
    'updated_at' => '2026-10-21T09:00:00+00:00',
]], status: 200, description: 'The project after the change.')]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/validation-failed',
        'title' => 'Validation Failed',
        'status' => 422,
        'detail' => 'Keys cannot be changed.',
        'errors' => ['key' => ['Keys cannot be changed.']],
    ],
    status: 422,
    description: 'The key differs from the current one, or the name was removed.',
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
        'type' => 'https://apiguide.dev/errors/unsupported-media-type',
        'title' => 'Unsupported Media Type',
        'status' => 415,
        'detail' => 'The Content-Type \'application/json\' is not supported. Supported content types: application/merge-patch+json.',
    ],
    status: 415,
    description: 'The body is not application/merge-patch+json.',
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
final class UpdateProjectController
{
    public function __invoke(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $payload = UpdateProjectPayload::fromRequest($request);

        $project->update(['name' => $payload->name]);

        return ProjectResource::make($project)->response();
    }
}
