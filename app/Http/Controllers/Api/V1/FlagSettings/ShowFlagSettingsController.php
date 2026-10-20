<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\FlagSettings;

use App\Http\Resources\FlagSettingResource;
use App\Models\Flag;
use App\Models\FlagSetting;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Flag settings')]
#[Endpoint(
    title: 'Show Flag Settings',
    description: 'Fetch the settings of a flag in one environment: whether it is on, its rules and what it serves by default. Use it before changing them: the ETag of the response goes in If-Match on the update. Returns the settings, or 304 with no body when If-None-Match still matches.',
)]
#[UrlParam('project', type: 'string', description: 'The project key.', required: true, example: 'checkout')]
#[UrlParam('flag', type: 'string', description: 'The flag key, unique within the project.', required: true, example: 'new-checkout')]
#[UrlParam('settings', type: 'string', description: 'The key of the environment whose settings these are.', required: true, example: 'production')]
#[Authenticated]
#[Response(content: ['data' => [
    'environment' => 'production',
    'enabled' => true,
    'off_variant' => 'off',
    'default' => ['variant' => 'off'],
    'rules' => [[
        'description' => 'Staff see it first',
        'conditions' => [['attribute' => 'email', 'operator' => 'ends_with', 'values' => ['@example.com']]],
        'serve' => ['variant' => 'on'],
    ]],
    'updated_at' => '2026-10-21T09:00:00+00:00',
]], status: 200, description: 'The settings, with their ETag.')]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/resource-not-found',
        'title' => 'Resource Not Found',
        'status' => 404,
        'detail' => 'The resource was not found.',
    ],
    status: 404,
    description: 'The project, the flag, or an environment with this key in the project does not exist.',
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
    description: 'Token is missing the flags:read ability.',
)]
final class ShowFlagSettingsController
{
    public function __invoke(Project $project, Flag $flag, FlagSetting $settings): JsonResponse
    {
        return FlagSettingResource::make($settings)->response();
    }
}
