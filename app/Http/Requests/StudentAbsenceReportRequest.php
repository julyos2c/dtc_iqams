<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentAbsenceReportRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'integer', 'exists:departments,id'],
            'course' => ['nullable', 'integer', 'exists:courses,id'],
            'section' => ['nullable', 'integer', 'exists:sections,id'],
            'subject' => ['nullable', 'integer', 'exists:subjects,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'risk' => ['nullable', Rule::in(['normal', 'at-risk'])],
            'type' => ['nullable', Rule::in(['summary', 'detailed'])],
        ];
    }
}
