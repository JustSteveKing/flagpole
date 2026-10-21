<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SdkKeys;

use App\Flags\SdkKeys;
use App\Http\Payloads\V1\CreateSdkKeyPayload;
use App\Http\Requests\SdkKeys\CreateSdkKeyRequest;
use App\Http\Resources\SdkKeyResource;
use App\Models\Environment;
use App\Models\Project;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Header;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'SDK keys')]
#[Endpoint(
    title: 'Create SDK Key',
    description: 'Create a key that an OpenFeature OFREP provider uses to evaluate the flags of this environment, and nothing else. Use it once per application or deployment; send an Idempotency-Key so a retry does not create a second key. Returns the key with its secret in meta.token, shown only this once.',
)]
#[UrlParam('project', type: 'string', description: 'The project key.', required: true, example: 'checkout')]
#[UrlParam('environment', type: 'string', description: 'The environment key, unique within the project.', required: true, example: 'production')]
#[Authenticated]
#[Header('Idempotency-Key', '"sdk-key-retry-1"')]
#[BodyParam('name', type: 'string', description: 'A label, so the key can be told apart from others when revoking one. Up to 255 characters.', required: true, example: 'checkout-web production')]
#[BodyParam('expires_at', type: 'string', description: 'When the key stops working, in the future. Leave it out for a key that never expires: one that expires on its own turns every flag back to its code default.', required: false, example: null)]
#[Response(content: ['data' => [
    'id' => '41',
    'name' => 'checkout-web production',
    'last_used_at' => '2026-10-21T09:12:00+00:00',
    'expires_at' => null,
    'created_at' => '2026-10-21T09:00:00+00:00',
], 'meta' => ['token' => '41|ofrep-example-secret', 'token_type' => 'Bearer']], status: 201, description: 'SDK key created. meta.token is the only time its secret is returned.')]
#[ResponseField('data.last_used_at', 'string', 'When an application last used the key, or null if none has.', nullable: true)]
#[ResponseField('data.expires_at', 'string', 'When the key stops working, or null if it never does.', nullable: true)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/validation-failed',
        'title' => 'Validation Failed',
        'status' => 422,
        'detail' => 'The name field is required.',
        'errors' => ['name' => ['The name field is required.']],
    ],
    status: 422,
    description: 'The name is missing, or expires_at is not a date in the future.',
)]
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
    description: 'Token is missing the sdk-keys:write ability.',
)]
final class CreateSdkKeyController
{
    public function __invoke(CreateSdkKeyRequest $request, Project $project, Environment $environment, #[CurrentUser] User $user, SdkKeys $keys): JsonResponse
    {
        $payload = CreateSdkKeyPayload::fromRequest($request);

        $token = $keys->issue($environment, $payload->name, $payload->expiresAt);

        SecurityAudit::log('sdk_keys.created', [
            'user_id' => $user->id,
            'project_id' => $project->id,
            'environment_id' => $environment->id,
            'token_id' => (string) $token->accessToken->id,
        ]);

        return SdkKeyResource::make($token->accessToken)->additional([
            'meta' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
            ],
        ])->response()->setStatusCode(201);
    }
}
