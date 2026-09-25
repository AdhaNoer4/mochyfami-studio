<?php

namespace App\Http\Requests\Research;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'claim' => ['required', 'string', 'max:5000'],
            'status' => ['nullable', new Enum(ResearchClaimStatus::class)],
            'importance' => ['nullable', new Enum(ResearchClaimImportance::class)],
        ];
    }
}
