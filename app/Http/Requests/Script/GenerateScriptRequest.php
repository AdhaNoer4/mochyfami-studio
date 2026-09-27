<?php

namespace App\Http\Requests\Script;

use Illuminate\Foundation\Http\FormRequest;

class GenerateScriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => ['nullable', 'string', 'max:100'],
            'topic' => ['required', 'string', 'max:300'],
            'language' => ['nullable', 'string', 'max:20'],
            'tone' => ['nullable', 'string', 'max:30'],
            'format' => ['nullable', 'string', 'max:40'],
            'hook_style' => ['nullable', 'string', 'max:100'],
            'target_duration_seconds' => ['nullable', 'integer', 'min:1', 'max:600'],
            'instructions' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
