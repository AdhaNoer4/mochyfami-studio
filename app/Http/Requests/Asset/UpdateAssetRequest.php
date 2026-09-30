<?php

namespace App\Http\Requests\Asset;

use App\Enums\AssetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Same rules as create, with every field optional. status is not listed
     * on purpose: an update must never move an asset along its lifecycle.
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', new Enum(AssetType::class)],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'file_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mime_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'file_size' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'width' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'height' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'source_url' => ['sometimes', 'nullable', 'string', 'url', 'max:2048'],
            'source_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'license_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'attribution' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
