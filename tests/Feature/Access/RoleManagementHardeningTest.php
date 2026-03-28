<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessControlSeeder::class);
    }

    public function test_role_index_supports_validated_filters_sorting_and_pagination(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        Role::query()->create(['name' => 'lab-admin', 'guard_name' => 'web']);
        Role::query()->create(['name' => 'inventory-admin', 'guard_name' => 'web']);

        $response = $this->actingAs($admin)->get(route('roles', [
            'search' => 'admin',
            'scope' => 'custom',
            'sort_by' => 'created_at',
            'sort_direction' => 'desc',
            'per_page' => 25,
        ]));

        $response->assertOk();

        $filters = $response->viewData('filters');
        $roles = $response->viewData('roles');

        $this->assertSame('created_at', $filters['sort_by']);
        $this->assertSame('desc', $filters['sort_direction']);
        $this->assertSame(25, $filters['per_page']);
        $this->assertTrue(collect($roles->items())->every(fn (Role $role) => ! in_array($role->name, array_keys(config('csi_access.roles')), true)));
    }

    public function test_super_admin_can_create_role_and_write_audit_log(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $this->actingAs($admin)
            ->post(route('roles.store'), [
                'name' => 'Scheduling Manager',
            ])
            ->assertRedirect(route('roles'));

        $role = Role::query()->where('name', 'scheduling-manager')->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'role_permission',
            'action' => 'create',
            'auditable_type' => $role->getMorphClass(),
            'auditable_id' => $role->getKey(),
            'user_id' => $admin->getKey(),
        ]);
    }

    public function test_super_admin_can_update_role_permissions_and_write_audit_log(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $role = Role::query()->create([
            'name' => 'reporting-admin',
            'guard_name' => 'web',
        ]);

        $this->actingAs($admin)
            ->post(route('roles.update', $role), [
                'permissions' => [
                    'view dashboard',
                    'view report management',
                ],
            ])
            ->assertRedirect(route('roles'));

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'role_permission',
            'action' => 'update',
            'auditable_type' => $role->getMorphClass(),
            'auditable_id' => $role->getKey(),
            'user_id' => $admin->getKey(),
        ]);
    }

    public function test_repeating_same_permission_update_is_idempotent(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $role = Role::query()->create([
            'name' => 'queue-admin',
            'guard_name' => 'web',
        ]);
        $role->syncPermissions(['view dashboard']);

        $this->actingAs($admin)
            ->postJson(route('roles.update', $role), [
                'permissions' => ['view dashboard'],
            ])
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertDatabaseMissing('audit_logs', [
            'module' => 'role_permission',
            'action' => 'update',
            'auditable_type' => $role->getMorphClass(),
            'auditable_id' => $role->getKey(),
        ]);
    }

    public function test_system_role_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $systemRole = Role::query()->where('name', 'front-office')->firstOrFail();

        $this->actingAs($admin)
            ->postJson(route('roles.delete', $systemRole), [
                'reason' => 'Cleanup',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.role_management.0', 'System role tidak bisa dihapus dari dashboard.');

        $this->assertDatabaseHas('roles', [
            'id' => $systemRole->getKey(),
        ]);
    }

    public function test_custom_role_with_assigned_users_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $role = Role::query()->create([
            'name' => 'ops-admin',
            'guard_name' => 'web',
        ]);

        $assignedUser = User::factory()->create();
        $assignedUser->assignRole('ops-admin');

        $this->actingAs($admin)
            ->postJson(route('roles.delete', $role), [
                'reason' => 'Cleanup',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.role_management.0', 'Role masih dipakai oleh user aktif, jadi tidak bisa dihapus.');

        $this->assertDatabaseHas('roles', [
            'id' => $role->getKey(),
        ]);
    }

    public function test_unused_custom_role_can_be_deleted_and_logged(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $role = Role::query()->create([
            'name' => 'temp-role',
            'guard_name' => 'web',
        ]);

        $this->actingAs($admin)
            ->post(route('roles.delete', $role), [
                'reason' => 'Tidak dipakai',
            ])
            ->assertRedirect(route('roles'));

        $this->assertDatabaseMissing('roles', [
            'id' => $role->getKey(),
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'role_permission',
            'action' => 'delete',
            'auditable_type' => $role->getMorphClass(),
            'auditable_id' => $role->getKey(),
            'user_id' => $admin->getKey(),
        ]);
    }
}
