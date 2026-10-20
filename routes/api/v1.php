<?php

declare(strict_types=1);

use App\Flags\Key;
use App\Http\Controllers\Api\V1\Auth\CreateTokenController;
use App\Http\Controllers\Api\V1\Auth\DeleteAllTokensController;
use App\Http\Controllers\Api\V1\Auth\DeleteTokenController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\ListTokensController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\SendEmailVerificationNotificationController;
use App\Http\Controllers\Api\V1\Auth\ShowResetPasswordTokenController;
use App\Http\Controllers\Api\V1\Auth\VerifyEmailController;
use App\Http\Controllers\Api\V1\Environments\CreateEnvironmentController;
use App\Http\Controllers\Api\V1\Environments\DeleteEnvironmentController;
use App\Http\Controllers\Api\V1\Environments\ListEnvironmentsController;
use App\Http\Controllers\Api\V1\Environments\ShowEnvironmentController;
use App\Http\Controllers\Api\V1\Environments\UpdateEnvironmentController;
use App\Http\Controllers\Api\V1\Flags\ArchiveFlagController;
use App\Http\Controllers\Api\V1\Flags\CreateFlagController;
use App\Http\Controllers\Api\V1\Flags\ListFlagsController;
use App\Http\Controllers\Api\V1\Flags\RestoreFlagController;
use App\Http\Controllers\Api\V1\Flags\ShowFlagController;
use App\Http\Controllers\Api\V1\Flags\UpdateFlagController;
use App\Http\Controllers\Api\V1\FlagSettings\ListFlagSettingsController;
use App\Http\Controllers\Api\V1\FlagSettings\ShowFlagSettingsController;
use App\Http\Controllers\Api\V1\FlagSettings\UpdateFlagSettingsController;
use App\Http\Controllers\Api\V1\Projects\CreateProjectController;
use App\Http\Controllers\Api\V1\Projects\DeleteProjectController;
use App\Http\Controllers\Api\V1\Projects\ListProjectsController;
use App\Http\Controllers\Api\V1\Projects\ShowProjectController;
use App\Http\Controllers\Api\V1\Projects\UpdateProjectController;
use App\Http\Controllers\Api\V1\SdkKeys\CreateSdkKeyController;
use App\Http\Controllers\Api\V1\SdkKeys\DeleteSdkKeyController;
use App\Http\Controllers\Api\V1\SdkKeys\ListSdkKeysController;
use App\Http\Controllers\Api\V1\Webhooks\CreateWebhookController;
use App\Http\Controllers\Api\V1\Webhooks\DeleteWebhookController;
use App\Http\Controllers\Api\V1\Webhooks\GrantWebhookConsentController;
use App\Http\Controllers\Api\V1\Webhooks\ListWebhookDeliveriesController;
use App\Http\Controllers\Api\V1\Webhooks\ListWebhooksController;
use App\Http\Controllers\Api\V1\Webhooks\OpenWebhookConsentLinkController;
use App\Http\Controllers\Api\V1\Webhooks\PingWebhookController;
use App\Http\Controllers\Api\V1\Webhooks\RedeliverWebhookController;
use App\Http\Controllers\Api\V1\Webhooks\RotateWebhookSecretController;
use App\Http\Controllers\Api\V1\Webhooks\ShowWebhookController;
use App\Http\Controllers\Api\V1\Webhooks\UpdateWebhookController;
use App\Http\Controllers\Api\V1\Webhooks\ValidateWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', RegisterController::class)
    ->middleware(['idempotent', 'throttle:auth-register'])
    ->name('v1.auth.register');
Route::post('/auth/login', LoginController::class)
    ->middleware('throttle:auth-login')
    ->name('v1.auth.login');
Route::post('/auth/password/forgot', ForgotPasswordController::class)
    ->middleware(['idempotent', 'throttle:auth-password'])
    ->name('v1.auth.password.forgot');
Route::post('/auth/password/reset', ResetPasswordController::class)
    ->middleware('throttle:auth-password')
    ->name('v1.auth.password.reset');
Route::get('/auth/password/reset/{token}', ShowResetPasswordTokenController::class)
    ->name('password.reset');
Route::get('/auth/email/verify/{id}/{hash}', VerifyEmailController::class)
    ->whereUlid('id')
    ->where('hash', '[0-9a-f]{40}')
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

// The URL is the credential: it is signed, expires, and is sent only to the endpoint asked.
// The CloudEvents webhook spec asks for both methods; each is its own documented operation.
Route::get('/webhooks/{webhook}/consent', OpenWebhookConsentLinkController::class)
    ->whereUlid('webhook')
    ->middleware('throttle:6,1')
    ->name('v1.webhooks.consent');
Route::post('/webhooks/{webhook}/consent', GrantWebhookConsentController::class)
    ->whereUlid('webhook')
    ->middleware('throttle:6,1')
    ->name('v1.webhooks.consent.grant');

Route::middleware(['auth:sanctum', 'throttle:auth-protected'])->group(function (): void {
    Route::get('/auth/me', MeController::class)
        ->middleware(['abilities:auth:me', 'conditional'])
        ->name('v1.auth.me');
    Route::post('/auth/logout', LogoutController::class)
        ->middleware('abilities:auth:logout')
        ->name('v1.auth.logout');
    Route::get('/auth/tokens', ListTokensController::class)
        ->middleware('abilities:auth:tokens:read')
        ->name('v1.auth.tokens.index');
    Route::post('/auth/tokens', CreateTokenController::class)
        ->middleware(['abilities:auth:tokens:create', 'idempotent'])
        ->name('v1.auth.tokens.store');
    Route::delete('/auth/tokens', DeleteAllTokensController::class)
        ->middleware('abilities:auth:tokens:delete')
        ->name('v1.auth.tokens.destroy-all');
    Route::delete('/auth/tokens/{token_id}', DeleteTokenController::class)
        ->whereNumber('token_id')
        ->middleware('abilities:auth:tokens:delete')
        ->name('v1.auth.tokens.destroy');
    Route::post('/auth/email/verification-notification', SendEmailVerificationNotificationController::class)
        ->middleware(['abilities:auth:verification:send', 'throttle:6,1'])
        ->name('v1.auth.email.verification-notification');

    Route::get('/webhooks', ListWebhooksController::class)
        ->middleware('abilities:webhooks:read')
        ->name('v1.webhooks.index');
    Route::post('/webhooks', CreateWebhookController::class)
        ->middleware(['abilities:webhooks:write', 'idempotent'])
        ->name('v1.webhooks.store');
    Route::get('/webhooks/{webhook}', ShowWebhookController::class)
        ->whereUlid('webhook')
        ->middleware('abilities:webhooks:read')
        ->name('v1.webhooks.show');
    Route::patch('/webhooks/{webhook}', UpdateWebhookController::class)
        ->whereUlid('webhook')
        ->middleware('abilities:webhooks:write')
        ->name('v1.webhooks.update');
    Route::delete('/webhooks/{webhook}', DeleteWebhookController::class)
        ->whereUlid('webhook')
        ->middleware('abilities:webhooks:write')
        ->name('v1.webhooks.destroy');
    Route::post('/webhooks/{webhook}/secret', RotateWebhookSecretController::class)
        ->whereUlid('webhook')
        ->middleware('abilities:webhooks:write')
        ->name('v1.webhooks.secret.rotate');
    Route::post('/webhooks/{webhook}/validate', ValidateWebhookController::class)
        ->whereUlid('webhook')
        ->middleware(['abilities:webhooks:write', 'throttle:6,1'])
        ->name('v1.webhooks.validate');
    Route::post('/webhooks/{webhook}/ping', PingWebhookController::class)
        ->whereUlid('webhook')
        ->middleware(['abilities:webhooks:write', 'throttle:6,1'])
        ->name('v1.webhooks.ping');
    Route::get('/webhooks/{webhook}/deliveries', ListWebhookDeliveriesController::class)
        ->whereUlid('webhook')
        ->middleware('abilities:webhooks:read')
        ->name('v1.webhooks.deliveries.index');
    Route::post('/webhooks/{webhook}/deliveries/{delivery}/redeliver', RedeliverWebhookController::class)
        ->whereUlid(['webhook', 'delivery'])
        ->middleware(['abilities:webhooks:write', 'throttle:6,1'])
        ->name('v1.webhooks.deliveries.redeliver');
});

Route::middleware(['auth:sanctum', 'throttle:auth-protected'])
    ->where(['project' => Key::ROUTE_PATTERN, 'environment' => Key::ROUTE_PATTERN, 'flag' => Key::ROUTE_PATTERN, 'settings' => Key::ROUTE_PATTERN])
    ->group(function (): void {
        Route::get('/projects', ListProjectsController::class)
            ->middleware('abilities:projects:read')
            ->name('v1.projects.index');
        Route::post('/projects', CreateProjectController::class)
            ->middleware(['abilities:projects:write', 'idempotent'])
            ->name('v1.projects.store');
        Route::get('/projects/{project}', ShowProjectController::class)
            ->middleware(['abilities:projects:read', 'conditional'])
            ->name('v1.projects.show');
        Route::patch('/projects/{project}', UpdateProjectController::class)
            ->middleware(['abilities:projects:write', 'consumes:application/merge-patch+json', 'merge-patch', 'conditional', 'preconditions.required'])
            ->name('v1.projects.update');
        Route::delete('/projects/{project}', DeleteProjectController::class)
            ->middleware('abilities:projects:write')
            ->name('v1.projects.destroy');

        Route::get('/projects/{project}/environments', ListEnvironmentsController::class)
            ->middleware('abilities:projects:read')
            ->name('v1.environments.index');
        Route::post('/projects/{project}/environments', CreateEnvironmentController::class)
            ->middleware(['abilities:projects:write', 'idempotent'])
            ->name('v1.environments.store');
        Route::get('/projects/{project}/environments/{environment}', ShowEnvironmentController::class)
            ->middleware(['abilities:projects:read', 'conditional'])
            ->name('v1.environments.show');
        Route::patch('/projects/{project}/environments/{environment}', UpdateEnvironmentController::class)
            ->middleware(['abilities:projects:write', 'consumes:application/merge-patch+json', 'merge-patch', 'conditional', 'preconditions.required'])
            ->name('v1.environments.update');
        Route::delete('/projects/{project}/environments/{environment}', DeleteEnvironmentController::class)
            ->middleware('abilities:projects:write')
            ->name('v1.environments.destroy');

        Route::get('/projects/{project}/environments/{environment}/sdk-keys', ListSdkKeysController::class)
            ->middleware('abilities:sdk-keys:read')
            ->name('v1.sdk-keys.index');
        Route::post('/projects/{project}/environments/{environment}/sdk-keys', CreateSdkKeyController::class)
            ->middleware(['abilities:sdk-keys:write', 'idempotent'])
            ->name('v1.sdk-keys.store');
        Route::delete('/projects/{project}/environments/{environment}/sdk-keys/{key_id}', DeleteSdkKeyController::class)
            ->whereNumber('key_id')
            ->middleware('abilities:sdk-keys:write')
            ->name('v1.sdk-keys.destroy');

        Route::get('/projects/{project}/flags', ListFlagsController::class)
            ->middleware('abilities:flags:read')
            ->name('v1.flags.index');
        Route::post('/projects/{project}/flags', CreateFlagController::class)
            ->middleware(['abilities:flags:write', 'idempotent'])
            ->name('v1.flags.store');
        Route::get('/projects/{project}/flags/{flag}', ShowFlagController::class)
            ->middleware(['abilities:flags:read', 'conditional'])
            ->name('v1.flags.show');
        Route::patch('/projects/{project}/flags/{flag}', UpdateFlagController::class)
            ->middleware(['abilities:flags:write', 'consumes:application/merge-patch+json', 'merge-patch', 'conditional', 'preconditions.required'])
            ->name('v1.flags.update');
        Route::post('/projects/{project}/flags/{flag}/archive', ArchiveFlagController::class)
            ->middleware(['abilities:flags:write', 'conditional', 'preconditions.required:POST'])
            ->name('v1.flags.archive');
        Route::post('/projects/{project}/flags/{flag}/restore', RestoreFlagController::class)
            ->middleware(['abilities:flags:write', 'conditional', 'preconditions.required:POST'])
            ->name('v1.flags.restore');

        Route::get('/projects/{project}/flags/{flag}/settings', ListFlagSettingsController::class)
            ->middleware('abilities:flags:read')
            ->name('v1.flags.settings.index');
        Route::get('/projects/{project}/flags/{flag}/settings/{settings}', ShowFlagSettingsController::class)
            ->middleware(['abilities:flags:read', 'conditional'])
            ->name('v1.flags.settings.show');
        Route::patch('/projects/{project}/flags/{flag}/settings/{settings}', UpdateFlagSettingsController::class)
            ->middleware(['abilities:flags:write', 'consumes:application/merge-patch+json', 'merge-patch', 'conditional', 'preconditions.required'])
            ->name('v1.flags.settings.update');
    });
