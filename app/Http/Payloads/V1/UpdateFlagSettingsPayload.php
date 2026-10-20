<?php

declare(strict_types=1);

namespace App\Http\Payloads\V1;

use App\Flags\Serve;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final readonly class UpdateFlagSettingsPayload
{
    /**
     * @param  array<string, mixed>  $default
     * @param  list<array<string, mixed>>  $rules
     */
    public function __construct(
        public bool $enabled,
        public string $offVariant,
        public array $default,
        public array $rules,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $data = self::validatedData($request);

        return new self(
            enabled: Arr::boolean($data, 'enabled'),
            offVariant: Arr::string($data, 'off_variant'),
            default: Serve::from($data['default'] ?? null),
            rules: self::rules($data['rules'] ?? []),
        );
    }

    /**
     * @return array{enabled: bool, off_variant: string, default: array<string, mixed>, rules: list<array<string, mixed>>}
     */
    public function settings(): array
    {
        return ['enabled' => $this->enabled, 'off_variant' => $this->offVariant, 'default' => $this->default, 'rules' => $this->rules];
    }

    /**
     * Keeps only the members a rule has, in the order a rule is written.
     *
     * @return list<array<string, mixed>>
     */
    private static function rules(mixed $rules): array
    {
        $list = [];

        foreach (is_array($rules) ? $rules : [] as $rule) {
            if ( ! is_array($rule)) {
                continue;
            }

            $conditions = [];

            foreach (is_array($rule['conditions'] ?? null) ? $rule['conditions'] : [] as $condition) {
                if (is_array($condition)) {
                    $conditions[] = [
                        'attribute' => $condition['attribute'] ?? null,
                        'operator' => $condition['operator'] ?? null,
                        'values' => array_values(is_array($condition['values'] ?? null) ? $condition['values'] : []),
                    ];
                }
            }

            $list[] = [
                'description' => is_string($rule['description'] ?? null) ? $rule['description'] : null,
                'conditions' => $conditions,
                'serve' => Serve::from($rule['serve'] ?? null),
            ];
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
