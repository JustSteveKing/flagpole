<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Environment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use UnexpectedValueException;

/**
 * The second lock on the OFREP routes (RFC-0005): the token must belong to an environment. A user's
 * token cannot hold ofrep:evaluate anyway, so this only matters if that ever changes.
 */
final readonly class EnsureSdkKey
{
    public function handle(Request $request, Closure $next): Response
    {
        if ( ! Environment::authenticating($request) instanceof Environment) {
            throw new AccessDeniedHttpException(__('api.errors.forbidden'));
        }

        $response = $next($request);

        if ( ! $response instanceof Response) {
            throw new UnexpectedValueException('Expected the next middleware to return a response.');
        }

        return $response;
    }
}
