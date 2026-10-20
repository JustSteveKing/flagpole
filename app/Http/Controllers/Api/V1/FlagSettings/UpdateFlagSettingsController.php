<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\FlagSettings;

use App\Flags\FlagWriter;
use App\Http\Payloads\V1\UpdateFlagSettingsPayload;
use App\Http\Requests\FlagSettings\UpdateFlagSettingsRequest;
use App\Http\Resources\FlagSettingResource;
use App\Models\Flag;
use App\Models\FlagSetting;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Header;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Flag settings')]
#[Endpoint(
    title: 'Update Flag Settings',
    description: 'Change the settings of a flag in one environment with a JSON Merge Patch (application/merge-patch+json): turn it on or off, change its rules, or change what it serves. Send {"enabled": false} to switch a flag off. Send the ETag from Show Flag Settings in If-Match, so a change someone else made first is not overwritten. Returns the settings after the change.',
)]
#[UrlParam('project', type: 'string', description: 'The project key.', required: true, example: 'checkout')]
#[UrlParam('flag', type: 'string', description: 'The flag key, unique within the project.', required: true, example: 'new-checkout')]
#[UrlParam('settings', type: 'string', description: 'The key of the environment whose settings these are.', required: true, example: 'production')]
#[Authenticated]
#[Header('Content-Type', 'application/merge-patch+json')]
#[BodyParam('enabled', type: 'boolean', description: 'false serves the off variant to everyone, and nothing else is consulted.', required: false, example: false)]
#[BodyParam('off_variant', type: 'string', description: 'The variant served while the flag is disabled.', required: false, example: 'off')]
#[BodyParam('default', type: 'object', description: 'What is served when the flag is enabled and no rule matches: {"variant": name} or {"split": [{"variant": name, "weight": hundredths of a percent}]}, the weights adding up to 10000.', required: false, example: ['variant' => 'off'])]
#[BodyParam('rules', type: 'object[]', description: 'Up to 50 rules, tried in order; the first whose conditions all match decides. The whole list is replaced: a merge patch replaces an array whole.', required: false, example: [])]
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
]], status: 200, description: 'The settings after the change.')]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/validation-failed',
        'title' => 'Validation Failed',
        'status' => 422,
        'detail' => 'The selected variant does not exist on this flag.',
        'errors' => ['off_variant' => ['The selected variant does not exist on this flag.']],
    ],
    status: 422,
    description: 'A variant does not exist, a split does not add up to 10000, the values of a condition do not suit its operator, or a read-only field changed.',
)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/resource-conflict',
        'title' => 'Resource Conflict',
        'status' => 409,
        'detail' => 'The flag is archived. Restore it before changing it.',
    ],
    status: 409,
    description: 'The flag is archived.',
)]
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
    description: 'Token is missing the flags:write ability.',
)]
final class UpdateFlagSettingsController
{
    public function __invoke(UpdateFlagSettingsRequest $request, Project $project, Flag $flag, FlagSetting $settings, FlagWriter $flags): JsonResponse
    {
        abort_if($flag->isArchived(), 409, __('api.flags.archived'));

        $settings = $flags->updateSettings($settings, UpdateFlagSettingsPayload::fromRequest($request)->settings());

        return FlagSettingResource::make($settings)->response();
    }
}
