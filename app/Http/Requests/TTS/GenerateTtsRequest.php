<?php

namespace App\Http\Requests\TTS;

use App\Services\TTS\DTO\TtsRequest;
use Illuminate\Foundation\Http\FormRequest;

class GenerateTtsRequest extends FormRequest
{
    /**
     * Synthesis is authorized through the visual plan policy in the controller,
     * not here. Returning true only means the body is well formed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Shape-only validation, mirroring the hard limits the provider-side DTO
     * enforces.
     *
     * provider is deliberately not checked against config('tts.providers'),
     * because the provider registry is the authority on which names exist and
     * it has to answer for runtime availability anyway: an unknown name is a
     * provider failure (502) rather than a validation failure (422), so that
     * a registry that grows later does not invalidate a deployed client.
     *
     * speed has no default in the rules; the pipeline applies
     * TtsRequest::DEFAULT_SPEED when the field is absent, because the default
     * belongs to synthesis, not to the HTTP contract.
     */
    public function rules(): array
    {
        return [
            'voice' => ['required', 'string', 'max:'.TtsRequest::MAX_VOICE_LENGTH],
            'provider' => ['nullable', 'string', 'max:50'],
            'model' => ['nullable', 'string', 'max:'.TtsRequest::MAX_MODEL_LENGTH],
            'language' => ['nullable', 'string', 'max:'.TtsRequest::MAX_LANGUAGE_LENGTH],
            'speed' => ['nullable', 'numeric', 'min:'.TtsRequest::MIN_SPEED, 'max:'.TtsRequest::MAX_SPEED],
        ];
    }
}
