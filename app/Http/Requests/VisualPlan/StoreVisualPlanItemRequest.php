<?php

namespace App\Http\Requests\VisualPlan;

use App\Enums\VisualPlanItemType;
use App\Enums\VisualPlanSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreVisualPlanItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order' => ['nullable', 'integer', 'min:1'],
            'section' => ['required', new Enum(VisualPlanSection::class)],
            'narration_text' => ['required', 'string', 'max:5000'],
            'visual_type' => ['required', new Enum(VisualPlanItemType::class)],
            'visual_prompt' => ['required', 'string', 'max:2000'],
            'duration_seconds' => ['required', 'integer', 'min:1', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
