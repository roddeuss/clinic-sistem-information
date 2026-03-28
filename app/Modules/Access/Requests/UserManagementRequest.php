<?php

namespace App\Modules\Access\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserManagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs('users.store') => $this->canAccess('create user management'),
            $this->routeIs('users.update') => $this->canAccess('edit user management'),
            $this->routeIs('users.archive') => $this->canAccess('delete user management'),
            default => false,
        };
    }

    public function rules(): array
    {
        return match (true) {
            $this->routeIs('users.store') => [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
                'role' => ['required', 'string', Rule::exists('roles', 'name')],
                'is_active' => ['required', 'boolean'],
            ],
            $this->routeIs('users.update') => [
                'name' => ['required', 'string', 'max:255'],
                'email' => [
                    'required',
                    'string',
                    'lowercase',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')->ignore($this->route('user')),
                ],
                'role' => ['required', 'string', Rule::exists('roles', 'name')],
                'is_active' => ['required', 'boolean'],
            ],
            $this->routeIs('users.archive') => [
                'reason' => ['nullable', 'string', 'max:255'],
            ],
            default => [],
        };
    }

    public function payload(): array
    {
        return $this->validated();
    }

    protected function prepareForValidation(): void
    {
        if ($this->routeIs('users.store', 'users.update')) {
            $this->merge([
                'name' => Str::squish((string) $this->input('name')),
                'email' => Str::lower(trim((string) $this->input('email'))),
                'is_active' => $this->boolean('is_active'),
            ]);
        }

        if ($this->routeIs('users.archive')) {
            $this->merge([
                'reason' => Str::squish((string) $this->input('reason')),
            ]);
        }
    }

    private function canAccess(string $permission): bool
    {
        $user = $this->user();

        return $user?->hasRole('super-admin') || ($user?->can($permission) ?? false);
    }
}
