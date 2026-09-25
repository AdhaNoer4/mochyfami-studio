<?php

namespace App\Http\Requests\Idea;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.data' => ['required', 'array'],
            'rows.*.status' => ['required', 'string'],
        ];
    }
}
