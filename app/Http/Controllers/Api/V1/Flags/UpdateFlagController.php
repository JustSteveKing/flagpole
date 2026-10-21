<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Flags;

use App\Flags\Actor;
use App\Flags\FlagWriter;
use App\Http\Payloads\V1\UpdateFlagPayload;
use App\Http\Requests\Flags\UpdateFlagRequest;
use App\Http\Resources\FlagResource;
use App\Models\Flag;
use App\Models\Project;
use App\Models\User;
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

#[Group(name: 'Flags')]
#[Endpoint(
    title: 'Update Flag',
    description: 'Change the name, description or variants of a flag with a JSON Merge Patch (application/merge-patch+json). Use it to rename a flag or change what a variant serves; the key and type are permanent, and a variant any environment still uses cannot be removed. Send the ETag from Show Flag in If-Match. Returns the updated flag.',
)]
#[UrlParam('project', type: 'string', description: 'The project key.', required: true, example: 'checkout')]
#[UrlParam('flag', type: 'string', description: 'The flag key, unique within the project.', required: true, example: 'new-checkout')]
#[Authenticated]
#[Header('Content-Type', 'application/merge-patch+json')]
#[Header('If-Match', '"a1b2c3d4e5f6"')]
#[BodyParam('name', type: 'string', description: 'A new name, up to 255 characters.', required: false, example: 'Checkout redesign')]
#[BodyParam('description', type: 'string', description: 'A new description, or null to remove it.', required: false, example: 'The redesigned checkout flow, phase two.')]
#[BodyParam('key', type: 'string', description: 'May be sent only with its current value.', required: false, example: 'new-checkout')]
#[BodyParam('type', type: 'string', description: 'May be sent only with its current value.', required: false, example: 'boolean')]
#[BodyParam('variants', type: 'object[]', description: 'The complete list of variants: a merge patch replaces an array whole. The variants of a boolean flag cannot change.', required: false, example: null)]
#[BodyParam('variants[].name', type: 'string', description: 'The name of the variant, unique within the flag, in the same form as a key.', required: false, example: 'on')]
#[BodyParam('variants[].value', type: 'string', description: 'The value served, of the type of the flag.', required: false, example: 'true')]
#[Response(content: ['data' => [
    'key' => 'new-checkout',
    'name' => 'New checkout',
    'description' => 'The redesigned checkout flow.',
    'type' => 'boolean',
    'variants' => [['name' => 'on', 'value' => true], ['name' => 'off', 'value' => false]],
    'archived_at' => null,
    'created_at' => '2026-10-21T09:00:00+00:00',
    'updated_at' => '2026-10-21T09:00:00+00:00',
]], status: 200, description: 'The flag after the change.')]
#[ResponseField('data.description', 'string', 'What the flag is for, or null.', nullable: true)]
#[ResponseField('data.archived_at', 'string', 'When the flag was archived, or null while it is in use.', nullable: true)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/validation-failed',
        'title' => 'Validation Failed',
        'status' => 422,
        'detail' => 'The variant blue is still used in: production.',
        'errors' => ['variants' => ['The variant blue is still used in: production.']],
    ],
    status: 422,
    description: 'The key or type differs from the current one, a variant is malformed, or a variant still in use was removed.',
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
    description: 'No project with this key belongs to the authenticated user, or it has no flag with this key.',
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
final class UpdateFlagController
{
    public function __invoke(UpdateFlagRequest $request, Project $project, Flag $flag, FlagWriter $flags, #[CurrentUser] User $user): JsonResponse
    {
        abort_if($flag->isArchived(), 409, __('api.flags.archived'));

        $flag = $flags->update($flag, UpdateFlagPayload::fromRequest($request, $flag)->changes(), Actor::of($user));

        return FlagResource::make($flag)->response();
    }
}
