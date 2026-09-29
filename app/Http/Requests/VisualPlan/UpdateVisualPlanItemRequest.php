<?php

namespace App\Http\Requests\VisualPlan;

use App\Enums\VisualPlanItemType;
use App\Enums\VisualPlanSection;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateVisualPlanItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order' => ['nullable', 'integer', 'min:1'],
            'section' => ['nullable', new Enum(VisualPlanSection::class)],
            'narration_text' => ['nullable', 'string', 'max:5000'],
            'visual_type' => ['nullable', new Enum(VisualPlanItemType::class)],
            'visual_prompt' => ['nullable', 'string', 'max:2000'],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (empty(array_filter($this->validated(), fn ($value) => $value !== null))) {
                    $validator->errors()->add('section', 'At least one field must be provided to update an item.');
                }
            },
        ];
    }
}
