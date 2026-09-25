<?php

namespace App\Http\Requests\Idea;

use App\Enums\ContentFormat;
use App\Enums\ContentIdeaStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateIdeaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ideaId = $this->route('idea')?->id ?? $this->route('idea');

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('content_ideas', 'slug')->ignore($ideaId),
            ],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:content_categories,id'],
            'hook' => ['nullable', 'string'],
            'concept' => ['nullable', 'string'],
            'format' => ['sometimes', 'required', new Enum(ContentFormat::class)],
            'status' => ['sometimes', 'required', new Enum(ContentIdeaStatus::class)],
            'priority' => ['nullable', 'integer', 'min:1', 'max:3'],
            'notes' => ['nullable', 'string'],
            'source_idea' => ['nullable', 'string', 'max:255'],
        ];
    }
}
