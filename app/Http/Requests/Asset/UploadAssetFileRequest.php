<?php

namespace App\Http\Requests\Asset;

use App\Services\AssetFileStorageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UploadAssetFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only the binary is accepted. There is no file_path, no mime_type, and no
     * file_size, so nothing in this request can tell the server where to write
     * or what to believe about the file it received.
     *
     * Two checks happen here and one happens later, and the split is not
     * arbitrary. What the file is in general terms, and how big it is, are
     * known now. Whether it suits this particular asset is not, because the
     * asset has not been resolved yet and these rules run before the
     * controller does. That last check belongs to the service, which is also
     * where an incompatible file is refused without any of it being stored.
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
                File::types(['image/*', 'video/*', 'audio/*'])
                    ->max(app(AssetFileStorageService::class)->maxSizeKb()),
            ],
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
            'file.required' => 'A file is required.',
            'file.mimetypes' => 'The file must be an image, a video, or an audio file.',
            'file.max' => 'The file is larger than this application accepts.',
        ];
    }
}
