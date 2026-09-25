<?php

namespace App\Http\Requests\Research;

use App\Enums\ResearchStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateResearchStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(ResearchStatus::class)],
        ];
    }
}
