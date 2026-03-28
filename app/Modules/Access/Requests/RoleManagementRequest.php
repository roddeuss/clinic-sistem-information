<?php

namespace App\Modules\Access\Requests;

use App\Modules\Access\Services\AccessCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleManagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs('roles.store') => $this->canAccess('create role permission'),
            $this->routeIs('roles.update') => $this->canAccess('edit role permission'),
            $this->routeIs('roles.delete') => $this->canAccess('delete role permission'),
            default => false,
        };
    }

    public function rules(): array
    {
        $permissionNames = app(AccessCatalogService::class)->permissionNames();

        return match (true) {
            $this->routeIs('roles.store') => [
                'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')],
            ],
            $this->routeIs('roles.update') => [
                'permissions' => ['nullable', 'array'],
                'permissions.*' => ['string', Rule::in($permissionNames)],
            ],
            $this->routeIs('roles.delete') => [
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
        if ($this->routeIs('roles.store')) {
            $this->merge([
                'name' => Str::slug((string) $this->input('name')),
            ]);
        }

        if ($this->routeIs('roles.delete')) {
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
