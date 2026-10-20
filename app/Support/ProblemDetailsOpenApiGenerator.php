<?php

declare(strict_types=1);

namespace App\Support;

use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator;

/**
 * Documents error responses as application/problem+json, the media type api-toolbox sends them
 * with (RFC 9457 section 3). Scribe labels every documented response application/json.
 */
final class ProblemDetailsOpenApiGenerator extends OpenApiGenerator
{
    public const string MEDIA_TYPE = 'application/problem+json';

    /**
     * @param  array<string, mixed>  $pathItem
     * @param  array<int, array{description: string, name: string, endpoints: OutputEndpointData[]}>  $groupedEndpoints
     * @return array<string, mixed>
     */
    public function pathItem(array $pathItem, array $groupedEndpoints, OutputEndpointData $endpoint): array
    {
        if ( ! is_array($pathItem['responses'] ?? null)) {
            return $pathItem;
        }

        foreach ($pathItem['responses'] as $status => $response) {
            if ((int) $status < 400 || ! is_array($response) || ! is_array($response['content'] ?? null)) {
                continue;
            }

            if (self::isOfrepBody($endpoint, (int) $status)) {
                continue;
            }

            $schema = $response['content']['application/json'] ?? null;

            if ( ! is_array($schema)) {
                continue;
            }

            $response['content'] = [self::MEDIA_TYPE => $schema];
            $pathItem['responses'][$status] = $response;
        }

        return $pathItem;
    }

    /**
     * OFREP defines its own bodies for 400, 404 and 500, sent as application/json (ADR-0002). Its
     * other errors are problem details like everywhere else.
     */
    private static function isOfrepBody(OutputEndpointData $endpoint, int $status): bool
    {
        return str_starts_with($endpoint->uri, 'ofrep/') && in_array($status, [400, 404, 500], true);
    }
}
