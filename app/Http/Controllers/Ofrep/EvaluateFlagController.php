<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ofrep;

use App\Flags\ErrorCode;
use App\Flags\Evaluator;
use App\Flags\Ofrep\EnvironmentFlags;
use App\Flags\Ofrep\EvaluationRequest;
use App\Flags\Ofrep\OfrepResponse;
use App\Http\Payloads\RouteParameter;
use App\Models\Environment;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;
use Throwable;

/**
 * OFREP single evaluation (ADR-0001), for server-side providers that evaluate with each request's
 * own context. Errors use OFREP's bodies, not problem details (ADR-0002).
 */
#[Group(name: 'OFREP')]
#[Endpoint(
    title: 'Evaluate Flag',
    description: 'Evaluate one flag for one context, in the environment the SDK key belongs to. Called by an OpenFeature OFREP provider on the server, once per evaluation; you rarely call it by hand. Returns the value, its variant and why, or an OFREP error body that the provider turns into the default in code.',
)]
#[Authenticated]
#[UrlParam('key', type: 'string', description: 'The flag key.', required: true, example: 'new-checkout')]
#[BodyParam('context', type: 'object', description: 'The evaluation context: any properties the rules of the flags look at, such as email or plan.', required: true, example: ['targetingKey' => 'user-123', 'email' => 'jane@example.com', 'plan' => 'pro'])]
#[BodyParam('context.targetingKey', type: 'string', description: 'Who the evaluation is for. Required by percentage rollouts, which put the same subject in the same variant every time.', required: false, example: 'user-123')]
#[Response(content: ['key' => 'new-checkout', 'value' => true, 'variant' => 'on', 'reason' => 'TARGETING_MATCH'], status: 200, description: 'The flag, evaluated.')]
#[Response(content: ['key' => 'new-checkout', 'errorCode' => 'TARGETING_KEY_MISSING', 'errorDetails' => 'The flag new-checkout serves a percentage rollout, which needs a targetingKey in the context.'], status: 400, description: 'A rollout was reached with no targetingKey (TARGETING_KEY_MISSING), the context is missing or not an object (INVALID_CONTEXT), or the body is not JSON (PARSE_ERROR).')]
#[Response(content: ['key' => 'old-banner', 'errorCode' => 'FLAG_NOT_FOUND', 'errorDetails' => 'There is no flag old-banner in this environment.'], status: 404, description: 'No flag with this key, or it is archived.')]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/unauthorized',
        'title' => 'Unauthorized',
        'status' => 401,
        'detail' => 'Unauthenticated.',
    ],
    status: 401,
    description: 'The SDK key is missing, revoked or expired.',
)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/forbidden',
        'title' => 'Forbidden',
        'status' => 403,
        'detail' => 'Forbidden.',
    ],
    status: 403,
    description: 'The token is not an SDK key.',
)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/too-many-requests',
        'title' => 'Too Many Requests',
        'status' => 429,
        'detail' => 'Too many requests. Please try again later.',
    ],
    status: 429,
    description: 'More than 600 requests in a minute with this SDK key. Retry-After says when to try again.',
)]
#[Response(content: ['errorDetails' => 'Flags could not be evaluated. Try again shortly.'], status: 500, description: 'An unexpected error. The provider falls back to the defaults in code.')]
final class EvaluateFlagController
{
    public function __invoke(Request $request, #[CurrentUser] Environment $environment, EnvironmentFlags $flags, Evaluator $evaluator): JsonResponse
    {
        $key = RouteParameter::string($request, 'key');

        try {
            $evaluation = EvaluationRequest::fromBody($request->getContent());

            if ($evaluation->error instanceof ErrorCode) {
                return OfrepResponse::failure($evaluation->error, $key);
            }

            $pair = $flags->one($environment, $key);

            if (null === $pair) {
                return OfrepResponse::failure(ErrorCode::FlagNotFound, $key, 404);
            }

            $resolution = $evaluator->evaluate($pair[0], $pair[1], $evaluation->context);

            return OfrepResponse::json(OfrepResponse::evaluation($resolution), $resolution->isFailure() ? 400 : 200);
        } catch (Throwable $exception) {
            report($exception);

            return OfrepResponse::serverError();
        }
    }
}
