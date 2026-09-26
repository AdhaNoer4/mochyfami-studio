<?php

namespace App\Http\Requests\Script;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCurrentScriptVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'hook' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'closing' => ['nullable', 'string', 'max:255'],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:600'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
