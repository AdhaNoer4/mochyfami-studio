<?php

namespace App\Http\Requests\Idea;

use Illuminate\Foundation\Http\FormRequest;

class ConvertIdeaToProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:3'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
