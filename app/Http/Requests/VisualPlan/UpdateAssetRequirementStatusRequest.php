<?php

namespace App\Http\Requests\VisualPlan;

use App\Enums\AssetRequirementStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateAssetRequirementStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(AssetRequirementStatus::class)],
        ];
    }
}
