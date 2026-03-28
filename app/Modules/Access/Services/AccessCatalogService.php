<?php

namespace App\Modules\Access\Services;

use Illuminate\Support\Arr;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AccessCatalogService
{
    public function permissionModules(): array
    {
        return collect(config('csi_access.modules'))
            ->map(function (array $module): array {
                return [
                    'label' => $module['label'],
                    'subject' => $module['subject'],
                    'permissions' => collect($module['abilities'])
                        ->map(fn (string $ability): array => [
                            'action' => $ability,
                            'name' => $this->permissionName($ability, $module['subject']),
                        ])
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    public function permissionNames(): array
    {
        return collect($this->permissionModules())
            ->flatMap(fn (array $module): array => Arr::pluck($module['permissions'], 'name'))
            ->all();
    }

    public function roleLabels(): array
    {
        return config('csi_access.roles');
    }

    public function syncConfiguredAccess(): void
    {
        $permissionNames = $this->ensureAccessCatalogExists();

        foreach ($this->roleLabels() as $roleName => $roleDefinition) {
            $role = Role::findOrCreate($roleName, 'web');

            $permissions = $roleDefinition['permissions'] === '*'
                ? $permissionNames
                : $roleDefinition['permissions'];

            $role->syncPermissions($permissions);
        }
    }

    public function ensureAccessCatalogExists(): array
    {
        $permissionNames = $this->permissionNames();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($permissionNames as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        foreach (array_keys($this->roleLabels()) as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $permissionNames;
    }

    private function permissionName(string $ability, string $subject): string
    {
        return sprintf('%s %s', $ability, $subject);
    }
}
