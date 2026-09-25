<?php

namespace App\Http\Requests\Idea;

use Illuminate\Foundation\Http\FormRequest;

class PreviewImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'csv_file' => ['required', 'file', 'mimetypes:text/csv,text/plain,application/csv,text/comma-separated-values,application/excel,application/vnd.ms-excel', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'csv_file.required' => 'Please upload a CSV file to preview.',
            'csv_file.file' => 'Uploaded item must be a valid file.',
            'csv_file.mimetypes' => 'Uploaded file must be a valid CSV document.',
            'csv_file.max' => 'CSV file size must not exceed 5MB.',
        ];
    }
}
