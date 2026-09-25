<?php

namespace App\Http\Requests\Research;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'claim' => ['sometimes', 'required', 'string', 'max:5000'],
            'status' => ['sometimes', new Enum(ResearchClaimStatus::class)],
            'importance' => ['sometimes', new Enum(ResearchClaimImportance::class)],
        ];
    }
}
