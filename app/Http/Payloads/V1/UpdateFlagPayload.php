<?php

declare(strict_types=1);

namespace App\Http\Payloads\V1;

use App\Models\Flag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final readonly class UpdateFlagPayload
{
    /**
     * @param  list<array{name: string, value: mixed}>  $variants
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public array $variants,
    ) {}

    public static function fromRequest(Request $request, Flag $flag): self
    {
        $data = self::validatedData($request);

        return new self(
            name: Arr::string($data, 'name'),
            description: isset($data['description']) ? Arr::string($data, 'description') : null,
            variants: CreateFlagPayload::variants($flag->type, $data['variants'] ?? []),
        );
    }

    /**
     * @return array{name: string, description: string|null, variants: list<array{name: string, value: mixed}>}
     */
    public function changes(): array
    {
        return ['name' => $this->name, 'description' => $this->description, 'variants' => $this->variants];
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
