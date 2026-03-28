<?php

namespace App\Modules\DoctorLetters\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DoctorLetterIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('view doctor letter management');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'issued', 'voided'])],
            'letter_type' => ['nullable', 'string', Rule::in(['sick_note', 'fit_note', 'control_note', 'drug_free_note'])],
            'sort_by' => ['nullable', 'string', Rule::in(['letter_no', 'letter_type', 'status', 'issued_at', 'created_at'])],
            'sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ];
    }

    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'status' => (string) ($validated['status'] ?? ''),
            'letter_type' => (string) ($validated['letter_type'] ?? ''),
            'sort_by' => (string) ($validated['sort_by'] ?? 'issued_at'),
            'sort_direction' => (string) ($validated['sort_direction'] ?? 'desc'),
            'per_page' => (int) ($validated['per_page'] ?? 10),
        ];
    }

    private function canAccess(string $permission): bool
    {
        $user = $this->user();

        return $user?->hasRole('super-admin') || ($user?->can($permission) ?? false);
    }
}
