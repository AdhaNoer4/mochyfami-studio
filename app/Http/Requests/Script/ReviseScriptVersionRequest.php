<?php

namespace App\Http\Requests\Script;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReviseScriptVersionRequest extends FormRequest
{
    /**
     * Editable fields that the revision flow may replace. All other fields are
     * rejected as unknown and every omitted editable field is inherited from
     * the source version.
     */
    private const EDITABLE_FIELDS = ['hook', 'body', 'closing'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'hook' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'closing' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $unknown = array_diff(array_keys($this->all()), self::EDITABLE_FIELDS);

        $provided = collect($this->only(self::EDITABLE_FIELDS))
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->isNotEmpty();

        $validator->after(function (Validator $validator) use ($unknown, $provided): void {
            foreach ($unknown as $field) {
                $validator->errors()->add($field, 'Unknown revision field.');
            }

            if (! $provided) {
                $validator->errors()->add('hook', 'At least one editable field (hook, body, or closing) is required.');
            }
        });
    }
}
