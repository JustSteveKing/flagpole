<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ofrep;

use App\Flags\ErrorCode;
use App\Flags\Evaluator;
use App\Flags\Ofrep\EnvironmentFlags;
use App\Flags\Ofrep\EvaluationRequest;
use App\Flags\Ofrep\OfrepResponse;
use App\Models\Environment;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use JustSteveKing\Toolbox\ConditionalRequests\EntityTag;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Header;
use Knuckles\Scribe\Attributes\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

/**
 * OFREP bulk evaluation (ADR-0001), for client-side providers that evaluate every flag once for a
 * fixed context and poll. It answers a matching If-None-Match with 304, as OFREP specifies and
 * RFC 9110 does not allow for a POST (ADR-0003), which is why api-toolbox's conditional middleware
 * is left off this route.
 */
#[Group(name: 'OFREP')]
#[Endpoint(
    title: 'Evaluate Flags',
    description: 'Evaluate every flag in the environment of the SDK key for one context. Called by an OpenFeature OFREP provider in the browser or on a device, which polls it with the last ETag in If-None-Match. Returns every flag with its value or its own error, or 304 with no body while nothing it would see has changed. The 304 to a POST departs from RFC 9110 on purpose, because OFREP specifies it.',
)]
#[Authenticated]
#[Header('If-None-Match', '"a1b2c3d4e5f6"')]
#[BodyParam('context', type: 'object', description: 'The evaluation context: any properties the rules of the flags look at, such as email or plan.', required: true, example: ['targetingKey' => 'user-123', 'email' => 'jane@example.com', 'plan' => 'pro'])]
#[BodyParam('context.targetingKey', type: 'string', description: 'Who the evaluation is for. Required by percentage rollouts, which put the same subject in the same variant every time.', required: false, example: 'user-123')]
#[Response(content: ['flags' => [
    ['key' => 'new-checkout', 'value' => true, 'variant' => 'on', 'reason' => 'TARGETING_MATCH'],
    ['key' => 'theme', 'value' => 'blue', 'variant' => 'blue', 'reason' => 'STATIC'],
    ['key' => 'rollout', 'errorCode' => 'TARGETING_KEY_MISSING', 'errorDetails' => 'The flag rollout serves a percentage rollout, which needs a targetingKey in the context.'],
]], status: 200, description: 'Every flag, evaluated, with an ETag for the next poll.')]
#[Response(content: null, status: 304, description: 'If-None-Match still matches: nothing this context would see has changed.')]
#[Response(content: ['errorCode' => 'INVALID_CONTEXT', 'errorDetails' => 'The context must be an object, and its targetingKey a string.'], status: 400, description: 'The context is missing or not an object (INVALID_CONTEXT), or the body is not JSON (PARSE_ERROR).')]
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
final class EvaluateFlagsController
{
    public function __invoke(Request $request, #[CurrentUser] Environment $environment, EnvironmentFlags $flags, Evaluator $evaluator): HttpResponse
    {
        try {
            $evaluation = EvaluationRequest::fromBody($request->getContent());

            if ($evaluation->error instanceof ErrorCode) {
                return OfrepResponse::failure($evaluation->error);
            }

            // What this client would see depends on the flags and on its own context, so both go
            // into the ETag (ADR-0003).
            $etag = EntityTag::strong(hash('xxh128', $environment->id . "\n" . $environment->version . "\n" . $evaluation->canonicalContext));

            if ($this->notModified($request, $etag)) {
                return response()->noContent(304, ['ETag' => $etag->toString()]);
            }

            $results = $flags->all($environment)->map(
                static fn(array $pair): array => OfrepResponse::evaluation($evaluator->evaluate($pair[0], $pair[1], $evaluation->context)),
            );

            return OfrepResponse::json(['flags' => $results->all()], 200, ['ETag' => $etag->toString()]);
        } catch (Throwable $exception) {
            report($exception);

            return OfrepResponse::serverError();
        }
    }

    /**
     * Compared weakly, as RFC 9110 says for If-None-Match. "*" never matches here: it would mean
     * only if no flags exist, which no provider asks.
     */
    private function notModified(Request $request, EntityTag $current): bool
    {
        $field = implode(', ', array_filter($request->headers->all('if-none-match'), is_string(...)));
        $tags = EntityTag::parseList($field);

        return is_array($tags) && array_any($tags, static fn(EntityTag $tag): bool => $tag->matchesWeakly($current));
    }
}
