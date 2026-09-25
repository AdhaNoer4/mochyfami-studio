<?php

namespace App\Http\Requests\Research;

use App\Enums\SourceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:2048', 'url'],
            'domain' => ['nullable', 'string', 'max:255', function ($attribute, $value, $fail) {
                if ($value !== null && filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
                    $fail('The domain must be a valid hostname without a scheme.');
                }
            }],
            'source_type' => ['required', new Enum(SourceType::class)],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
