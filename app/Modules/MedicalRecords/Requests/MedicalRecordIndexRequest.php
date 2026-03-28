<?php

namespace App\Modules\MedicalRecords\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MedicalRecordIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('view medical record management');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date'],
            'branch' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'section' => ['nullable', 'integer', Rule::exists('sections', 'id')],
            'status' => ['nullable', 'string', Rule::in(['pending', 'draft', 'final', 'reopen_requested', 'reopened'])],
            'sort_by' => ['nullable', 'string', Rule::in(['visit_date', 'created_at', 'care_stage', 'vital_status'])],
            'sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ];
    }

    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'date' => (string) ($validated['date'] ?? now()->toDateString()),
            'branch' => isset($validated['branch']) ? (string) $validated['branch'] : '',
            'section' => isset($validated['section']) ? (string) $validated['section'] : '',
            'status' => (string) ($validated['status'] ?? ''),
            'sort_by' => (string) ($validated['sort_by'] ?? 'visit_date'),
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
