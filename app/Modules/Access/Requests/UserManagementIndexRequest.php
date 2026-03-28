<?php

namespace App\Modules\Access\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserManagementIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('view user management');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', Rule::exists('roles', 'name')],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'sort_by' => ['nullable', 'string', Rule::in(['name', 'email', 'created_at', 'last_login_at', 'is_active'])],
            'sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ];
    }

    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'role' => (string) ($validated['role'] ?? ''),
            'status' => (string) ($validated['status'] ?? ''),
            'sort_by' => (string) ($validated['sort_by'] ?? 'name'),
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
