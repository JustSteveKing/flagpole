<?php

declare(strict_types=1);

namespace App\Http\Payloads\V1;

use App\Flags\FlagType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final readonly class CreateFlagPayload
{
    /**
     * @param  list<array{name: string, value: mixed}>  $variants
     */
    public function __construct(
        public string $key,
        public string $name,
        public ?string $description,
        public FlagType $type,
        public array $variants,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $data = self::validatedData($request);
        $type = FlagType::from(Arr::string($data, 'type'));

        return new self(
            key: Arr::string($data, 'key'),
            name: Arr::string($data, 'name'),
            description: isset($data['description']) ? Arr::string($data, 'description') : null,
            type: $type,
            variants: self::variants($type, $data['variants'] ?? []),
        );
    }

    /**
     * @return list<array{name: string, value: mixed}>
     */
    public static function variants(FlagType $type, mixed $variants): array
    {
        $list = [];

        foreach (is_array($variants) ? $variants : [] as $variant) {
            if (is_array($variant) && is_string($variant['name'] ?? null)) {
                $list[] = ['name' => $variant['name'], 'value' => FlagType::Float === $type && is_int($variant['value'] ?? null) ? (float) $variant['value'] : ($variant['value'] ?? null)];
            }
        }

        return $list;
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
