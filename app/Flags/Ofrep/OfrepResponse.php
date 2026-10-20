<?php

declare(strict_types=1);

namespace App\Flags\Ofrep;

use App\Flags\ErrorCode;
use App\Flags\Resolution;
use Illuminate\Http\JsonResponse;

/**
 * OFREP's response bodies (REF-0001). A float flag's whole-number value keeps its decimal point, so
 * a typed client does not read 2.0 as an integer.
 */
final class OfrepResponse
{
    /**
     * @param  array<string, string>  $headers
     */
    public static function json(mixed $body, int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($body, $status, $headers, JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public static function evaluation(Resolution $resolution): array
    {
        if ($resolution->error instanceof ErrorCode) {
            return ['key' => $resolution->key, 'errorCode' => $resolution->error->value, 'errorDetails' => $resolution->error->details($resolution->key)];
        }

        return ['key' => $resolution->key, 'value' => $resolution->value, 'variant' => $resolution->variant, 'reason' => $resolution->reason?->value];
    }

    /**
     * A failure of the whole request: with the flag's key for single evaluation, without it for bulk.
     */
    public static function failure(ErrorCode $error, ?string $key = null, int $status = 400): JsonResponse
    {
        $body = null === $key ? [] : ['key' => $key];

        return self::json([...$body, 'errorCode' => $error->value, 'errorDetails' => $error->details($key ?? '')], $status);
    }

    /**
     * OFREP's 500 carries only errorDetails.
     */
    public static function serverError(): JsonResponse
    {
        return self::json(['errorDetails' => __('ofrep.server_error')], 500);
    }
}
