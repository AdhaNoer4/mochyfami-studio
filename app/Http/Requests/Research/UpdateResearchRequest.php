<?php

namespace App\Http\Requests\Research;

use Illuminate\Foundation\Http\FormRequest;

class UpdateResearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'summary' => ['nullable', 'string', 'max:5000'],
            'researched_at' => ['nullable', 'date'],
        ];
    }
}
