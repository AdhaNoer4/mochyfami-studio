<?php

namespace App\Http\Requests\Research;

use Illuminate\Foundation\Http\FormRequest;

class CreateResearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
