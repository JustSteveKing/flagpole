<?php

declare(strict_types=1);

namespace App\Http\Requests\Projects;

use App\Flags\Key;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateProjectRequest extends FormRequest
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
        // Scribe reads these rules with no user, to document the body.
        $user = $this->user();

        return [
            'key' => ['required', ...Key::rules(), Rule::unique('projects', 'key')->where('user_id', $user instanceof User ? $user->id : null)],
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
            'key.unique' => __('api.projects.key_taken'),
        ];
    }
}
