<?php

namespace App\Http\Requests\Idea;

use App\Enums\ContentFormat;
use App\Enums\ContentIdeaStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class GetIdeasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'search' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:content_categories,id'],
            'format' => ['nullable', new Enum(ContentFormat::class)],
            'status' => ['nullable', new Enum(ContentIdeaStatus::class)],
            'priority' => ['nullable', 'integer', 'min:1', 'max:3'],
            'sort' => ['nullable', 'string', Rule::in(['created_at', 'updated_at', 'title', 'priority'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }
}
