<?php

namespace App\Modules\Access\Services;

use App\Models\User;
use App\Modules\Access\Exceptions\UserManagementException;
use App\Services\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UserManagementService
{
    private const ALLOWED_SORTS = [
        'name',
        'email',
        'created_at',
        'last_login_at',
        'is_active',
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
            'users' => $this->userTable($filters),
            'roles' => Role::query()->orderBy('name')->get(),
            'roleLabels' => $this->accessCatalogService->roleLabels(),
            'abilities' => $this->abilities(),
            'sortOptions' => $this->sortOptions(),
            'perPageOptions' => [10, 25, 50, 100],
        ];
    }

    public function createUser(array $payload, User $actor): User
    {
        $this->accessCatalogService->ensureAccessCatalogExists();

        return DB::transaction(function () use ($payload, $actor): User {
            $user = User::query()->create([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'password' => $payload['password'],
                'is_active' => $payload['is_active'],
            ]);

            $user->syncRoles([$payload['role']]);
            $user->loadMissing(['employee', 'roles:id,name']);

            $this->auditLogService->log(
                module: 'user_management',
                action: 'create',
                auditable: $user,
                description: sprintf('User %s dibuat oleh %s.', $user->email, $actor->email),
                after: $this->auditSnapshot($user),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                ],
            );

            return $user;
        });
    }

    public function updateUser(User $user, array $payload, User $actor): bool
    {
        $this->accessCatalogService->ensureAccessCatalogExists();

        return DB::transaction(function () use ($user, $payload, $actor): bool {
            $lockedUser = User::query()
                ->with('roles:id,name')
                ->lockForUpdate()
                ->findOrFail($user->getKey());

            $currentRole = (string) ($lockedUser->getRoleNames()->first() ?? '');
            $targetRole = $payload['role'];
            $targetStatus = (bool) $payload['is_active'];

            $this->guardOwnAccessMutation($lockedUser, $payload, $actor, $currentRole);
            $this->guardLastActiveSuperAdmin($lockedUser, $targetRole, $targetStatus);

            $before = $this->auditSnapshot($lockedUser);
            $dirty = $lockedUser->name !== $payload['name']
                || $lockedUser->email !== $payload['email']
                || $lockedUser->is_active !== $targetStatus
                || $currentRole !== $targetRole;

            if (! $dirty) {
                return false;
            }

            $lockedUser->fill([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'is_active' => $targetStatus,
            ]);

            $lockedUser->save();
            $lockedUser->syncRoles([$targetRole]);

            if (! $targetStatus) {
                $this->revokeSessions($lockedUser);
            }

            $lockedUser->loadMissing(['employee', 'roles:id,name']);

            $this->auditLogService->log(
                module: 'user_management',
                action: 'update',
                auditable: $lockedUser,
                description: sprintf('User %s diperbarui oleh %s.', $lockedUser->email, $actor->email),
                before: $before,
                after: $this->auditSnapshot($lockedUser),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                ],
            );

            return true;
        });
    }

    public function archiveUser(User $user, User $actor, ?string $reason = null): bool
    {
        $this->accessCatalogService->ensureAccessCatalogExists();

        return DB::transaction(function () use ($user, $actor, $reason): bool {
            $lockedUser = User::query()
                ->with('roles:id,name')
                ->lockForUpdate()
                ->findOrFail($user->getKey());

            $currentRole = (string) ($lockedUser->getRoleNames()->first() ?? '');

            if (! $lockedUser->is_active) {
                return false;
            }

            $this->guardOwnArchive($lockedUser, $actor);
            $this->guardLastActiveSuperAdmin($lockedUser, $currentRole, false);

            $before = $this->auditSnapshot($lockedUser);

            $lockedUser->forceFill([
                'is_active' => false,
                'remember_token' => null,
            ])->save();

            $this->revokeSessions($lockedUser);

            $lockedUser->loadMissing(['employee', 'roles:id,name']);

            $this->auditLogService->log(
                module: 'user_management',
                action: 'archive',
                auditable: $lockedUser,
                description: sprintf('User %s dinonaktifkan oleh %s.', $lockedUser->email, $actor->email),
                before: $before,
                after: $this->auditSnapshot($lockedUser),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'reason' => $reason,
                ],
            );

            return true;
        });
    }

    public function userPayload(User $user): array
    {
        $user->loadMissing(['employee', 'roles:id,name']);

        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->primaryRoleName(),
            'is_active' => $user->is_active,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'last_login_ip' => $user->last_login_ip,
            'employee_number' => $user->employee?->employee_number,
        ];
    }

    private function userTable(array $filters): LengthAwarePaginator
    {
        return User::query()
            ->with(['employee', 'roles:id,name'])
            ->searchForManagement($filters['search'])
            ->filterRole($filters['role'])
            ->filterStatus($filters['status'])
            ->orderByAllowed($filters['sort_by'], $filters['sort_direction'], self::ALLOWED_SORTS)
            ->paginate($filters['per_page'])
            ->withQueryString();
    }

    private function abilities(): array
    {
        $user = auth()->user();

        return [
            'create' => $user?->can('create user management') ?? false,
            'edit' => $user?->can('edit user management') ?? false,
            'delete' => $user?->can('delete user management') ?? false,
        ];
    }

    private function sortOptions(): array
    {
        return [
            'name' => 'Nama',
            'email' => 'Email',
            'created_at' => 'Tanggal dibuat',
            'last_login_at' => 'Login terakhir',
            'is_active' => 'Status',
        ];
    }

    private function guardOwnAccessMutation(User $targetUser, array $payload, User $actor, string $currentRole): void
    {
        if ($targetUser->getKey() !== $actor->getKey()) {
            return;
        }

        if ((bool) $payload['is_active'] === false) {
            throw new UserManagementException('Akun aktif yang sedang kamu gunakan tidak bisa dinonaktifkan dari modul user management.');
        }

        if ($payload['role'] !== $currentRole) {
            throw new UserManagementException('Role akun yang sedang kamu gunakan tidak bisa diubah dari modul user management.');
        }
    }

    private function guardOwnArchive(User $targetUser, User $actor): void
    {
        if ($targetUser->getKey() !== $actor->getKey()) {
            return;
        }

        throw new UserManagementException('Akun aktif yang sedang kamu gunakan tidak bisa diarsipkan dari modul user management.');
    }

    private function guardLastActiveSuperAdmin(User $targetUser, string $targetRole, bool $targetIsActive): void
    {
        $currentRole = (string) ($targetUser->getRoleNames()->first() ?? '');

        if (! $targetUser->is_active || $currentRole !== 'super-admin') {
            return;
        }

        if ($targetRole === 'super-admin' && $targetIsActive) {
            return;
        }

        $otherActiveSuperAdmins = User::query()
            ->select('users.id')
            ->role('super-admin')
            ->where('users.is_active', true)
            ->whereKeyNot($targetUser->getKey())
            ->lockForUpdate()
            ->exists();

        if (! $otherActiveSuperAdmins) {
            throw new UserManagementException('Sistem harus selalu memiliki minimal satu super-admin aktif.');
        }
    }

    private function revokeSessions(User $user): void
    {
        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->delete();
    }

    private function auditSnapshot(User $user): array
    {
        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'role' => $user->primaryRoleName(),
            'employee_number' => $user->employee?->employee_number,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
        ];
    }
}
