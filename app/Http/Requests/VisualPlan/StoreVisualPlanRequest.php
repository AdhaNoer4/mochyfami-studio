<?php

namespace App\Http\Requests\VisualPlan;

use Illuminate\Foundation\Http\FormRequest;

class StoreVisualPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'create_from_script' => ['nullable', 'boolean'],
        ];
    }
}
