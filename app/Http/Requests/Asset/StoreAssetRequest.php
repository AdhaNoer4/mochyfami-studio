<?php

namespace App\Http\Requests\Asset;

use App\Enums\AssetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only metadata is accepted. There is no file, no upload, and no
     * file_path, so nothing here can point the server at a location on disk.
     */
    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(AssetType::class)],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file_name' => ['nullable', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', 'max:255'],
            'file_size' => ['nullable', 'integer', 'min:0'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'width' => ['nullable', 'integer', 'min:1'],
            'height' => ['nullable', 'integer', 'min:1'],
            'source_url' => ['nullable', 'string', 'url', 'max:2048'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'license_type' => ['nullable', 'string', 'max:100'],
            'attribution' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
