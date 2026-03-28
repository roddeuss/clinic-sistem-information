<?php

namespace App\Modules\Queues\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QueueIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('view queue management');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in(['waiting', 'called', 'in_service', 'completed', 'skipped', 'cancelled'])],
            'section' => ['nullable', 'integer', Rule::exists('sections', 'id')],
            'sort_by' => ['nullable', 'string', Rule::in(['queue_date', 'queue_number', 'status', 'called_at', 'completed_at'])],
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
            'status' => (string) ($validated['status'] ?? ''),
            'section' => isset($validated['section']) ? (string) $validated['section'] : '',
            'sort_by' => (string) ($validated['sort_by'] ?? 'queue_date'),
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
