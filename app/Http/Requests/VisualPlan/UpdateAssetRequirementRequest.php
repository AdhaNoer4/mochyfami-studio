<?php

namespace App\Http\Requests\VisualPlan;

use App\Enums\AssetRequirementAspectRatio;
use App\Enums\AssetRequirementType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateAssetRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Status is intentionally absent: use the dedicated status endpoint.
     */
    public function rules(): array
    {
        return [
            'requirement_type' => ['nullable', new Enum(AssetRequirementType::class)],
            'search_query' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'target_duration_seconds' => ['nullable', 'integer', 'min:1', 'max:600'],
            'aspect_ratio' => ['nullable', new Enum(AssetRequirementAspectRatio::class)],
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
                    $validator->errors()->add(
                        'requirement_type',
                        'At least one field must be provided to update an asset requirement.'
                    );
                }
            },
        ];
    }
}
