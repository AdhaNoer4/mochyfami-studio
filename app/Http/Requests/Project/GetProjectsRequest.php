<?php

namespace App\Http\Requests\Project;

use App\Enums\ContentProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class GetProjectsRequest extends FormRequest
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
            'status' => ['nullable', new Enum(ContentProjectStatus::class)],
            'content_idea_id' => ['nullable', 'integer', 'exists:content_ideas,id'],
            'category_id' => ['nullable', 'integer', 'exists:content_categories,id'],
            'sort' => ['nullable', 'string', Rule::in(['created_at', 'updated_at', 'title', 'status', 'progress_percent'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }
}
