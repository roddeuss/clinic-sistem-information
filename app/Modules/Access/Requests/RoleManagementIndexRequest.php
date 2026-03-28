<?php

namespace App\Modules\Access\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleManagementIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('view role permission');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'scope' => ['nullable', 'string', Rule::in(['system', 'custom'])],
            'sort_by' => ['nullable', 'string', Rule::in(['name', 'created_at', 'permissions_count', 'users_count'])],
            'sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ];
    }

    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'scope' => (string) ($validated['scope'] ?? ''),
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
