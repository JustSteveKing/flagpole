<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SdkKeys;

use App\Http\Payloads\RouteParameter;
use App\Models\Environment;
use App\Models\Project;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'SDK keys')]
#[Endpoint(
    title: 'Delete SDK Key',
    description: 'Revoke an SDK key. Use it after its replacement is deployed, to rotate a key, or at once when a key has leaked; the next request that uses it gets 401. Returns 204 with no body.',
)]
#[Authenticated]
#[UrlParam('project', type: 'string', description: 'The project key.', required: true, example: 'checkout')]
#[UrlParam('environment', type: 'string', description: 'The environment key, unique within the project.', required: true, example: 'production')]
#[UrlParam('key_id', type: 'integer', description: 'The ID of the SDK key, from List SDK Keys.', required: true, example: 41)]
#[ScribeResponse(content: null, status: 204, description: 'SDK key revoked.')]
#[ScribeResponse(
    content: [
        'type' => 'https://apiguide.dev/errors/resource-not-found',
        'title' => 'Resource Not Found',
        'status' => 404,
        'detail' => 'The resource was not found.',
    ],
    status: 404,
    description: 'No project, environment, or SDK key of that environment matches.',
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
    description: 'Token is missing the sdk-keys:write ability.',
)]
final class DeleteSdkKeyController
{
    public function __invoke(Request $request, Project $project, Environment $environment, #[CurrentUser] User $user): Response
    {
        $token = $environment->tokens()->findOrFail(RouteParameter::integer($request, 'key_id'));
        $token->delete();

        SecurityAudit::log('sdk_keys.revoked', [
            'user_id' => $user->id,
            'project_id' => $project->id,
            'environment_id' => $environment->id,
            'token_id' => (string) $token->id,
        ]);

        return response()->noContent();
    }
}
