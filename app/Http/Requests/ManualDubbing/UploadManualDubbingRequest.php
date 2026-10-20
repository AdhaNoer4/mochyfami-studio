<?php

namespace App\Http\Requests\ManualDubbing;

use App\Services\AssetFileStorageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UploadManualDubbingRequest extends FormRequest
{
    /**
     * Authorization happens in the controller, through the visual plan
     * resolution and the project's asset policy. Returning true only means
     * the body is well formed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only the binary and an optional note are accepted. Everything else
     * about the recording — source, uploader, recorded_at, approval status,
     * approval provenance, provider, voice, duration — is server-side state
     * that no request may supply.
     *
     * The file rules mirror the asset library's upload shape but are narrowed
     * to audio: a manual dubbing is spoken audio, and a broader rule would
     * only fail later in the asset compatibility check after an asset row
     * already exists. The size limit is the same configured ceiling the asset
     * library uses, read through the same service so the two cannot drift.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                // Contents based, not filename based: this rejects a renamed
                // script or archive before a single byte is written anywhere.
                File::types(['audio/*'])
                    ->max(app(AssetFileStorageService::class)->maxSizeKb()),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * The keys match the rules the File object expands to, not the names of
     * its own builder methods: types() becomes a mimetypes rule and max()
     * becomes a max rule.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'An audio file is required.',
            'file.mimetypes' => 'The file must be an audio file.',
            'file.max' => 'The file is larger than this application accepts.',
            'notes.max' => 'The notes must not exceed 1000 characters.',
        ];
    }
}
