<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Flags;

use App\Flags\FlagWriter;
use App\Http\Payloads\V1\CreateFlagPayload;
use App\Http\Requests\Flags\CreateFlagRequest;
use App\Http\Resources\FlagResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Flags')]
#[Endpoint(
    title: 'Create Flag',
    description: 'Create a flag, disabled in every environment of the project. Use it from a deploy script or CI when code that reads a new flag ships; send an Idempotency-Key so a retry does not fail on the key it just took. Returns the flag; its key and type cannot change afterwards.',
)]
#[UrlParam('project', type: 'string', description: 'The project key.', required: true, example: 'checkout')]
#[Authenticated]
#[BodyParam('key', type: 'string', description: 'What applications ask for: lowercase letters and digits in runs separated by single hyphens, at most 64 characters, unique within the project.', required: true, example: 'new-checkout')]
#[BodyParam('name', type: 'string', description: 'A name for people to read, up to 255 characters.', required: true, example: 'New checkout')]
#[BodyParam('description', type: 'string', description: 'What the flag is for, up to 1,000 characters.', required: false, example: 'The redesigned checkout flow.')]
#[BodyParam('type', type: 'string', description: 'boolean, string, integer, float or object. Every variant has a value of this type.', required: true, example: 'boolean')]
#[BodyParam('variants', type: 'object[]', description: 'Between 2 and 20 variants, each a name and a value, for any type but boolean. A boolean flag always has on and off, and must not send variants. A new flag serves the first variant.', required: false, example: null)]
#[BodyParam('variants[].name', type: 'string', description: 'The name of the variant, unique within the flag, in the same form as a key.', required: false, example: 'blue')]
#[BodyParam('variants[].value', type: 'string', description: 'The value served, of the type of the flag.', required: false, example: '#0055ff')]
#[Response(content: ['data' => [
    'key' => 'new-checkout',
    'name' => 'New checkout',
    'description' => 'The redesigned checkout flow.',
    'type' => 'boolean',
    'variants' => [['name' => 'on', 'value' => true], ['name' => 'off', 'value' => false]],
    'archived_at' => null,
    'created_at' => '2026-10-21T09:00:00+00:00',
    'updated_at' => '2026-10-21T09:00:00+00:00',
]], status: 201, description: 'Flag created, disabled everywhere.')]
#[ResponseField('data.description', 'string', 'What the flag is for, or null.', nullable: true)]
#[ResponseField('data.archived_at', 'string', 'When the flag was archived, or null while it is in use.', nullable: true)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/validation-failed',
        'title' => 'Validation Failed',
        'status' => 422,
        'detail' => 'Keys are lowercase letters and digits, in runs separated by single hyphens.',
        'errors' => ['key' => ['Keys are lowercase letters and digits, in runs separated by single hyphens.']],
    ],
    status: 422,
    description: 'The key is malformed or taken, the type is unknown, or a variant is missing, repeated or of the wrong type.',
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
    description: 'Token is missing the flags:write ability.',
)]
final class CreateFlagController
{
    public function __invoke(CreateFlagRequest $request, Project $project, FlagWriter $flags): JsonResponse
    {
        $payload = CreateFlagPayload::fromRequest($request);

        $flag = $flags->create($project, $payload->key, $payload->name, $payload->description, $payload->type, $payload->variants);

        return FlagResource::make($flag)->response()->setStatusCode(201);
    }
}
