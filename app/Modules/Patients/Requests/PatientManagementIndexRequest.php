<?php

namespace App\Modules\Patients\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PatientManagementIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('view patient management');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'branch' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'sort_by' => ['nullable', 'string', Rule::in(['full_name', 'created_at', 'date_of_birth', 'is_active'])],
            'sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ];
    }

    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'branch' => isset($validated['branch']) ? (string) $validated['branch'] : '',
            'status' => (string) ($validated['status'] ?? ''),
            'sort_by' => (string) ($validated['sort_by'] ?? 'full_name'),
            'sort_direction' => (string) ($validated['sort_direction'] ?? 'asc'),
            'per_page' => (int) ($validated['per_page'] ?? 10),
        ];
    }

    private function canAccess(string $permission): bool
    {
        $user = $this->user();

        return $user?->hasRole('super-admin') || ($user?->can($permission) ?? false);
    }
}
