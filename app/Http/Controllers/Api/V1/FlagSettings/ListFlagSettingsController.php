<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\FlagSettings;

use App\Http\Resources\FlagSettingResource;
use App\Models\Flag;
use App\Models\FlagSetting;
use App\Models\Project;
use JustSteveKing\Toolbox\Pagination\CursorPage;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Flag settings')]
#[Endpoint(
    title: 'List Flag Settings',
    description: 'List the settings of a flag in every environment, ordered by environment key, 25 to a page. Use it to compare how a flag is set up across environments. Returns an array of settings documents; follow the Link header\'s rel="next" for the next page.',
)]
#[UrlParam('project', type: 'string', description: 'The project key.', required: true, example: 'checkout')]
#[UrlParam('flag', type: 'string', description: 'The flag key, unique within the project.', required: true, example: 'new-checkout')]
#[Authenticated]
#[QueryParam('cursor', type: 'string', description: 'Opaque cursor from a Link header. Omit it for the first page.', required: false, example: null)]
#[Response(content: [[
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
]], status: 200, description: 'One page of settings.')]
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
final class ListFlagSettingsController
{
    /**
     * @return CursorPage<int, FlagSetting>
     */
    public function __invoke(Project $project, Flag $flag): CursorPage
    {
        return CursorPage::of(
            $flag->settings()
                ->join('environments', 'environments.id', '=', 'flag_settings.environment_id')
                ->select('flag_settings.*', 'environments.key')
                ->orderBy('environments.key')
                ->with('environment')
                ->cursorPaginate(25),
            FlagSettingResource::class,
        );
    }
}
