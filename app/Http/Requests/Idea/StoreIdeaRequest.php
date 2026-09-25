<?php

namespace App\Http\Requests\Idea;

use App\Enums\ContentFormat;
use App\Enums\ContentIdeaStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreIdeaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:content_ideas,slug'],
            'category_id' => ['required', 'integer', 'exists:content_categories,id'],
            'hook' => ['nullable', 'string'],
            'concept' => ['nullable', 'string'],
            'format' => ['required', new Enum(ContentFormat::class)],
            'status' => ['required', new Enum(ContentIdeaStatus::class)],
            'priority' => ['nullable', 'integer', 'min:1', 'max:3'],
            'notes' => ['nullable', 'string'],
            'source_idea' => ['nullable', 'string', 'max:255'],
        ];
    }
}
