<?php

namespace App\Modules\Laboratory\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LaboratoryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('view laboratory management');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'branch' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'status' => ['nullable', 'string', Rule::in(['ordered', 'sample_collected', 'processing', 'sent_to_partner', 'resulted', 'reviewed', 'cancelled'])],
            'provider_type' => ['nullable', 'string', Rule::in(['internal', 'external'])],
            'date' => ['nullable', 'date'],
            'test_status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'test_sort_by' => ['nullable', 'string', Rule::in(['code', 'name', 'diagnostic_category', 'created_at'])],
            'test_sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'test_per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
            'order_sort_by' => ['nullable', 'string', Rule::in(['ordered_at', 'created_at', 'status', 'unit_price'])],
            'order_sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'order_per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ];
    }

    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'branch' => isset($validated['branch']) ? (string) $validated['branch'] : '',
            'status' => (string) ($validated['status'] ?? ''),
            'provider_type' => (string) ($validated['provider_type'] ?? ''),
            'date' => (string) ($validated['date'] ?? now()->toDateString()),
            'test_status' => (string) ($validated['test_status'] ?? ''),
            'test_sort_by' => (string) ($validated['test_sort_by'] ?? 'name'),
            'test_sort_direction' => (string) ($validated['test_sort_direction'] ?? 'asc'),
            'test_per_page' => (int) ($validated['test_per_page'] ?? 10),
            'order_sort_by' => (string) ($validated['order_sort_by'] ?? 'ordered_at'),
            'order_sort_direction' => (string) ($validated['order_sort_direction'] ?? 'desc'),
            'order_per_page' => (int) ($validated['order_per_page'] ?? 10),
        ];
    }

    private function canAccess(string $permission): bool
    {
        $user = $this->user();

        return $user?->hasRole('super-admin') || ($user?->can($permission) ?? false);
    }
}
