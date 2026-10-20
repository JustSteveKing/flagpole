<?php

declare(strict_types=1);

namespace App\Http\Requests\Environments;

use App\Flags\Key;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateEnvironmentRequest extends FormRequest
{
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

        return [
            'key' => ['required', ...Key::rules(), Rule::unique('environments', 'key')->where('project_id', $project instanceof Project ? $project->id : null)],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.regex' => __('api.keys.format'),
            'key.unique' => __('api.environments.key_taken'),
        ];
    }
}
