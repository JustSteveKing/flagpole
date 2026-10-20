<?php

declare(strict_types=1);

namespace App\Http\Payloads\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final readonly class UpdateEnvironmentPayload
{
    public function __construct(
        public string $name,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $data = self::validatedData($request);

        return new self(
            name: Arr::string($data, 'name'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function validatedData(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $request instanceof FormRequest ? $request->validated() : $request->all();

        return $data;
    }
}
