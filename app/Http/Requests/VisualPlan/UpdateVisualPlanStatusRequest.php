<?php

namespace App\Http\Requests\VisualPlan;

use App\Enums\VisualPlanStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateVisualPlanStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(VisualPlanStatus::class)],
        ];
    }
}
