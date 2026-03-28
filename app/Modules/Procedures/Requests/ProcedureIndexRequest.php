<?php

namespace App\Modules\Procedures\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcedureIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('view procedure management');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'branch' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'status' => ['nullable', 'string', Rule::in(['ordered', 'in_progress', 'completed', 'cancelled'])],
            'master_status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'date' => ['nullable', 'date'],
            'master_sort_by' => ['nullable', 'string', Rule::in(['code', 'name', 'created_at'])],
            'master_sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'master_per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
            'order_sort_by' => ['nullable', 'string', Rule::in(['ordered_at', 'created_at', 'status', 'subtotal'])],
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
            'master_status' => (string) ($validated['master_status'] ?? ''),
            'date' => (string) ($validated['date'] ?? now()->toDateString()),
            'master_sort_by' => (string) ($validated['master_sort_by'] ?? 'name'),
            'master_sort_direction' => (string) ($validated['master_sort_direction'] ?? 'asc'),
            'master_per_page' => (int) ($validated['master_per_page'] ?? 10),
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
