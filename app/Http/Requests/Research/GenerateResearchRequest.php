<?php

namespace App\Http\Requests\Research;

use App\Services\AI\DTO\ResearchGenerationRequest;
use Illuminate\Foundation\Http\FormRequest;

class GenerateResearchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'topic' => ['required', 'string', 'max:'.ResearchGenerationRequest::MAX_TOPIC_LENGTH],
            'question' => ['required', 'string', 'max:'.ResearchGenerationRequest::MAX_QUESTION_LENGTH],
            'context' => ['nullable', 'string', 'max:'.ResearchGenerationRequest::MAX_CONTEXT_LENGTH],
            'max_claims' => ['nullable', 'integer', 'between:'.ResearchGenerationRequest::MIN_MAX_CLAIMS.','.ResearchGenerationRequest::MAX_MAX_CLAIMS],
            'provider' => ['nullable', 'string', 'max:100'],
        ];
    }
}
