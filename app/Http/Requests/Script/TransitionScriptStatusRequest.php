<?php

namespace App\Http\Requests\Script;

use App\Enums\ScriptStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class TransitionScriptStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(ScriptStatus::class)],
        ];
    }
}
