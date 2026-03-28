<?php

namespace App\Modules\VisitRegistrations\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VisitRegistrationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('view visit registration');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in(['booked', 'queued', 'called', 'in_service', 'completed', 'skipped', 'cancelled'])],
            'type' => ['nullable', 'string', Rule::in(['same_day', 'booking', 'emergency'])],
            'section' => ['nullable', 'integer', Rule::exists('sections', 'id')],
            'sort_by' => ['nullable', 'string', Rule::in(['visit_date', 'created_at', 'registration_status', 'visit_type', 'checked_in_at'])],
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
            'type' => (string) ($validated['type'] ?? ''),
            'section' => isset($validated['section']) ? (string) $validated['section'] : '',
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
