<?php

namespace App\Http\Requests\Research;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResearchDiscoveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'query' => trim((string) $this->input('query')),
            'provider' => $this->input('provider') ? trim((string) $this->input('provider')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'max:500'],
            'max_results' => ['nullable', 'integer', 'min:1', 'max:20'],
            'provider' => ['nullable', 'string', Rule::in(array_keys(config('research.search.providers', [])))],
        ];
    }
}
