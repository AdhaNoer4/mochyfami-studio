<?php

namespace App\Http\Requests\VisualPlan;

use Illuminate\Foundation\Http\FormRequest;

class AttachAssetToRequirementRequest extends FormRequest
{
    /**
     * Attachment is authorized by the visual plan policy through the controller,
     * not here. Returning true only means the body is well formed; it must not
     * be read as permission to attach anything.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * asset_id is checked for shape only. It is deliberately not validated
     * with exists:assets,id.
     *
     * An unscoped existence rule would answer a different question for two
     * different callers: a 422 for an id that does not exist anywhere, and a
     * 404 for an id that exists in somebody else's project. That difference is
     * enough to confirm which asset ids are real without owning the project
     * they live in. Leaving the lookup to the service, which resolves the asset
     * through the requested project's own relation, keeps every unreachable id
     * indistinguishable from every other one.
     */
    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
