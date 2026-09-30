<?php

namespace App\Http\Requests\Asset;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class GetAssetsRequest extends FormRequest
{
    /**
     * Listing is authorized by AssetPolicy::viewAny through the project route
     * model binding, not here. Returning true only means the query string is
     * well formed; it must not be read as permission to read the assets.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Every value that reaches an ORDER BY or a WHERE clause is constrained
     * here rather than trusted from the query string.
     *
     * The enum rules reject an unknown type or status with a 422 before the
     * service is reached, and the sort rule is the allowlist the service
     * mirrors. An invalid sort field must never fall through to the database
     * as an arbitrary column name.
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', new Enum(AssetType::class)],
            'status' => ['nullable', new Enum(AssetStatus::class)],
            'sort' => ['nullable', 'string', Rule::in(['created_at', 'updated_at', 'title', 'file_size', 'duration_seconds'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }
}
