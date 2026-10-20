<?php

declare(strict_types=1);

namespace App\Http\Requests\SdkKeys;

use Illuminate\Foundation\Http\FormRequest;

final class CreateSdkKeyRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            // No maximum, unlike Kit's API keys (RFC-0006).
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
