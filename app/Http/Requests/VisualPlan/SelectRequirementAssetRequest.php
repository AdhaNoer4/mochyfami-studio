<?php

namespace App\Http\Requests\VisualPlan;

use Illuminate\Foundation\Http\FormRequest;

class SelectRequirementAssetRequest extends FormRequest
{
    /**
     * Selection is authorized by the visual plan policy through the controller,
     * not here. Returning true only means the body is well formed; it must not
     * be read as permission to select anything.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * asset_id is checked for shape only, for the same reason
     * AttachAssetToRequirementRequest does not use exists:assets,id.
     *
     * An unscoped existence rule would answer two different questions for two
     * different callers: a 422 for an id that does not exist anywhere, and a
     * 404 for an id that exists in somebody else's project. That difference is
     * enough to confirm which asset ids are real without owning the project
     * they live in, so the lookup stays with the service, which resolves the
     * asset through the requested project's own relation.
     */
    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
