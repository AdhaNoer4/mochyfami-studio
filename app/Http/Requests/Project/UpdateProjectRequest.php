<?php

namespace App\Http\Requests\Project;

use App\Enums\ContentProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $projectId = $this->route('project')?->id ?? $this->route('project');

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('content_projects', 'slug')->ignore($projectId),
            ],
            'content_idea_id' => ['nullable', 'integer', 'exists:content_ideas,id'],
            'category_id' => ['nullable', 'integer', 'exists:content_categories,id'],
            'status' => ['sometimes', 'required', new Enum(ContentProjectStatus::class)],
            'target_duration_seconds' => ['nullable', 'integer', 'min:1', 'max:300'],
            'language' => ['nullable', 'string', 'max:10'],
            'tone' => ['nullable', 'string', 'max:50'],
            'hook' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
