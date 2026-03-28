<?php

namespace App\Modules\VitalSigns\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VitalSignIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('view vital sign management');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date'],
            'branch' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'section' => ['nullable', 'integer', Rule::exists('sections', 'id')],
            'sort_by' => ['nullable', 'string', Rule::in(['recorded_at', 'created_at', 'systolic_bp', 'temperature_celsius', 'spo2_percent'])],
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
            'sort_by' => (string) ($validated['sort_by'] ?? 'recorded_at'),
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
