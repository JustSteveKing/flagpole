<?php

declare(strict_types=1);

namespace App\Http\Requests\Environments;

use App\Models\Environment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use JustSteveKing\Toolbox\MergePatch\MergePatch;

/**
 * A JSON Merge Patch (RFC 7396). The patch is applied to the current environment, and the merged
 * document is what gets validated: in a patch, null means "remove", which rules written for the
 * resource would misread.
 */
final class UpdateEnvironmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Keys never change. Sending the current one back is allowed, so a client can return
            // a whole document it fetched.
            'key' => ['required', 'string', Rule::in([$this->environment()?->key])],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.in' => __('api.keys.immutable'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        $environment = $this->environment();

        if ( ! $environment instanceof Environment) {
            return [];
        }

        $merged = app(MergePatch::class)->applyTo(['key' => $environment->key, 'name' => $environment->name]);

        /** @var array<string, mixed> $document */
        $document = is_array($merged) ? $merged : [];

        return $document;
    }

    /**
     * Null only when Scribe reads the rules, with no route, to document the body.
     */
    private function environment(): ?Environment
    {
        $environment = $this->route('environment');

        return $environment instanceof Environment ? $environment : null;
    }
}
