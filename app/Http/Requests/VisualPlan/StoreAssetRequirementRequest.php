<?php

namespace App\Http\Requests\VisualPlan;

use App\Enums\AssetRequirementAspectRatio;
use App\Enums\AssetRequirementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreAssetRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Status is intentionally absent: a new requirement always starts pending.
     */
    public function rules(): array
    {
        return [
            'requirement_type' => ['required', new Enum(AssetRequirementType::class)],
            'search_query' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'target_duration_seconds' => ['nullable', 'integer', 'min:1', 'max:600'],
            'aspect_ratio' => ['nullable', new Enum(AssetRequirementAspectRatio::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
