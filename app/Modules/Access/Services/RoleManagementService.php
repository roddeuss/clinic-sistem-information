<?php

namespace App\Modules\Access\Services;

use App\Modules\Access\Exceptions\RoleManagementException;
use App\Services\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleManagementService
{
    private const ALLOWED_SORTS = [
        'name',
        'created_at',
        'permissions_count',
        'users_count',
    ];

    public function __construct(
        private readonly AccessCatalogService $accessCatalogService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function getIndexData(array $filters): array
    {
        $this->accessCatalogService->ensureAccessCatalogExists();

        return [
            'filters' => $filters,
            'roles' => $this->roleTable($filters),
            'modules' => $this->accessCatalogService->permissionModules(),
            'roleLabels' => $this->accessCatalogService->roleLabels(),
            'abilities' => $this->abilities(),
            'sortOptions' => $this->sortOptions(),
            'perPageOptions' => [10, 25, 50, 100],
            'systemRoles' => $this->systemRoleNames(),
        ];
    }

    public function createRole(array $payload): Role
    {
        $this->accessCatalogService->ensureAccessCatalogExists();

        return DB::transaction(function () use ($payload): Role {
            $role = Role::query()->create([
                'name' => $payload['name'],
                'guard_name' => 'web',
            ]);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->auditLogService->log(
                module: 'role_permission',
                action: 'create',
                auditable: $role,
                description: sprintf('Role %s dibuat.', $role->name),
                after: $this->auditSnapshot($role),
            );

            return $role;
        });
    }

    public function updateRole(Role $role, array $payload): bool
    {
        $this->accessCatalogService->ensureAccessCatalogExists();

        return DB::transaction(function () use ($role, $payload): bool {
            $lockedRole = Role::query()
                ->with('permissions:id,name')
                ->withCount(['permissions', 'users'])
                ->lockForUpdate()
                ->findOrFail($role->getKey());

            $targetPermissions = collect($payload['permissions'] ?? [])
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();

            $before = $this->auditSnapshot($lockedRole);
            $currentPermissions = collect($before['permissions'])
                ->sort()
                ->values()
                ->all();

            if ($currentPermissions === $targetPermissions) {
                return false;
            }

            $lockedRole->syncPermissions($targetPermissions);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $lockedRole->load('permissions:id,name');
            $lockedRole->loadCount(['permissions', 'users']);

            $this->auditLogService->log(
                module: 'role_permission',
                action: 'update',
                auditable: $lockedRole,
                description: sprintf('Hak akses role %s diperbarui.', $lockedRole->name),
                before: $before,
                after: $this->auditSnapshot($lockedRole),
            );

            return true;
        });
    }

    public function deleteRole(Role $role, ?string $reason = null): bool
    {
        $this->accessCatalogService->ensureAccessCatalogExists();

        return DB::transaction(function () use ($role, $reason): bool {
            $lockedRole = Role::query()
                ->with('permissions:id,name')
                ->withCount(['permissions', 'users'])
                ->lockForUpdate()
                ->findOrFail($role->getKey());

            $this->guardDeletableRole($lockedRole);

            $before = $this->auditSnapshot($lockedRole);

            $lockedRole->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->auditLogService->log(
                module: 'role_permission',
                action: 'delete',
                auditable: $lockedRole,
                description: sprintf('Role %s dihapus.', $before['name']),
                before: $before,
                meta: [
                    'reason' => $reason,
                ],
            );

            return true;
        });
    }

    public function rolePayload(Role $role): array
    {
        $role->loadMissing('permissions:id,name');
        $role->loadCount(['permissions', 'users']);

        return [
            'id' => $role->getKey(),
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
            'permissions_count' => $role->permissions_count,
            'users_count' => $role->users_count,
            'is_system' => in_array($role->name, $this->systemRoleNames(), true),
        ];
    }

    private function roleTable(array $filters): LengthAwarePaginator
    {
        $systemRoles = $this->systemRoleNames();
        $sortBy = in_array($filters['sort_by'], self::ALLOWED_SORTS, true) ? $filters['sort_by'] : 'name';
        $sortDirection = $filters['sort_direction'] === 'desc' ? 'desc' : 'asc';

        return Role::query()
            ->with('permissions:id,name')
            ->withCount(['permissions', 'users'])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where('name', 'like', '%' . $filters['search'] . '%');
            })
            ->when($filters['scope'] !== '', function (Builder $query) use ($filters, $systemRoles): void {
                match ($filters['scope']) {
                    'system' => $query->whereIn('name', $systemRoles),
                    'custom' => $query->whereNotIn('name', $systemRoles),
                    default => null,
                };
            })
            ->orderBy($sortBy, $sortDirection)
            ->when($sortBy !== 'name', fn (Builder $query) => $query->orderBy('name'))
            ->paginate($filters['per_page'])
            ->withQueryString();
    }

    private function abilities(): array
    {
        $user = auth()->user();

        return [
            'create' => $user?->can('create role permission') ?? false,
            'edit' => $user?->can('edit role permission') ?? false,
            'delete' => $user?->can('delete role permission') ?? false,
        ];
    }

    private function sortOptions(): array
    {
        return [
            'name' => 'Nama role',
            'created_at' => 'Tanggal dibuat',
            'permissions_count' => 'Jumlah permission',
            'users_count' => 'Jumlah user',
        ];
    }

    private function systemRoleNames(): array
    {
        return array_keys($this->accessCatalogService->roleLabels());
    }

    private function guardDeletableRole(Role $role): void
    {
        if (in_array($role->name, $this->systemRoleNames(), true)) {
            throw new RoleManagementException('System role tidak bisa dihapus dari dashboard.');
        }

        $assignmentCount = DB::table(config('permission.table_names.model_has_roles'))
            ->where(config('permission.column_names.role_pivot_key') ?? 'role_id', $role->getKey())
            ->lockForUpdate()
            ->count();

        if ($assignmentCount > 0 || $role->users_count > 0) {
            throw new RoleManagementException('Role masih dipakai oleh user aktif, jadi tidak bisa dihapus.');
        }
    }

    private function auditSnapshot(Role $role): array
    {
        return [
            'id' => $role->getKey(),
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
            'permissions_count' => $role->permissions_count ?? $role->permissions->count(),
            'users_count' => $role->users_count ?? 0,
            'is_system' => in_array($role->name, $this->systemRoleNames(), true),
        ];
    }
}
