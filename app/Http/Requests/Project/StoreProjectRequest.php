<?php

namespace App\Http\Requests\Project;

use App\Enums\ContentProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:content_projects,slug'],
            'content_idea_id' => ['nullable', 'integer', 'exists:content_ideas,id'],
            'category_id' => ['nullable', 'integer', 'exists:content_categories,id'],
            'status' => ['nullable', new Enum(ContentProjectStatus::class)],
            'target_duration_seconds' => ['nullable', 'integer', 'min:1', 'max:300'],
            'language' => ['nullable', 'string', 'max:10'],
            'tone' => ['nullable', 'string', 'max:50'],
            'hook' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ];
    }
}
