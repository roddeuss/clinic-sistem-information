<?php

namespace App\Modules\Prescriptions\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrescriptionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('view prescription management');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'branch' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'status' => ['nullable', 'string', Rule::in(['none', 'draft', 'finalized', 'partial_dispensed', 'dispensed'])],
            'date' => ['nullable', 'date'],
            'visit_sort_by' => ['nullable', 'string', Rule::in(['visit_date', 'created_at'])],
            'visit_sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'visit_per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
            'item_sort_by' => ['nullable', 'string', Rule::in(['finalized_at', 'display_name', 'status', 'created_at'])],
            'item_sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'item_per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ];
    }

    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'branch' => isset($validated['branch']) ? (string) $validated['branch'] : '',
            'status' => (string) ($validated['status'] ?? ''),
            'date' => (string) ($validated['date'] ?? now()->toDateString()),
            'visit_sort_by' => (string) ($validated['visit_sort_by'] ?? 'visit_date'),
            'visit_sort_direction' => (string) ($validated['visit_sort_direction'] ?? 'desc'),
            'visit_per_page' => (int) ($validated['visit_per_page'] ?? 10),
            'item_sort_by' => (string) ($validated['item_sort_by'] ?? 'finalized_at'),
            'item_sort_direction' => (string) ($validated['item_sort_direction'] ?? 'desc'),
            'item_per_page' => (int) ($validated['item_per_page'] ?? 10),
        ];
    }

    private function canAccess(string $permission): bool
    {
        $user = $this->user();

        return $user?->hasRole('super-admin') || ($user?->can($permission) ?? false);
    }
}
