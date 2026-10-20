<?php

declare(strict_types=1);

namespace App\Http\Requests\Flags;

use App\Flags\FlagType;
use App\Flags\Key;
use App\Http\Requests\Flags\Concerns\ValidatesVariants;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateFlagRequest extends FormRequest
{
    use ValidatesVariants;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Body or query input only: path parameters are validated by the route's constraints.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Scribe reads these rules with no route, to document the body.
        $project = $this->route('project');
        $type = FlagType::tryFrom($this->string('type')->value());

        return [
            'key' => ['required', ...Key::rules(), Rule::unique('flags', 'key')->where('project_id', $project instanceof Project ? $project->id : null)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', 'string', Rule::enum(FlagType::class)],
            // A boolean flag's variants are always on and off, made for it.
            'variants' => ['prohibited_if:type,boolean', 'required_unless:type,boolean', 'array', 'list', 'min:2', 'max:20'],
            ...$this->variantRules($type),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.regex' => __('api.keys.format'),
            'key.unique' => __('api.flags.key_taken'),
            'variants.prohibited_if' => __('api.flags.boolean_variants'),
            ...$this->variantMessages(),
        ];
    }
}
