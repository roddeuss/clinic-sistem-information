<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserManagementHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessControlSeeder::class);
    }

    public function test_user_index_supports_validated_filters_sorting_and_pagination(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $alpha = User::factory()->create([
            'name' => 'Alpha Cashier',
            'email' => 'alpha.cashier@example.com',
            'is_active' => true,
        ]);
        $alpha->assignRole('cashier');

        $zeta = User::factory()->create([
            'name' => 'Zeta Cashier',
            'email' => 'zeta.cashier@example.com',
            'is_active' => true,
        ]);
        $zeta->assignRole('cashier');

        $inactive = User::factory()->create([
            'name' => 'Inactive Cashier',
            'email' => 'inactive.cashier@example.com',
            'is_active' => false,
        ]);
        $inactive->assignRole('cashier');

        $response = $this->actingAs($admin)->get(route('users', [
            'search' => 'cashier',
            'role' => 'cashier',
            'status' => 'active',
            'sort_by' => 'email',
            'sort_direction' => 'desc',
            'per_page' => 25,
        ]));

        $response->assertOk();

        $filters = $response->viewData('filters');
        $users = $response->viewData('users');

        $this->assertSame('email', $filters['sort_by']);
        $this->assertSame('desc', $filters['sort_direction']);
        $this->assertSame(25, $filters['per_page']);
        $this->assertSame([
            'zeta.cashier@example.com',
            'alpha.cashier@example.com',
        ], collect($users->items())->pluck('email')->all());
    }

    public function test_super_admin_cannot_deactivate_own_account_from_user_management(): void
    {
        $admin = User::factory()->create([
            'is_active' => true,
        ]);
        $admin->assignRole('super-admin');

        $this->actingAs($admin)
            ->postJson(route('users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'super-admin',
                'is_active' => false,
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.user_management.0', 'Akun aktif yang sedang kamu gunakan tidak bisa dinonaktifkan dari modul user management.');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_super_admin_cannot_change_own_role_from_user_management(): void
    {
        $admin = User::factory()->create([
            'is_active' => true,
        ]);
        $admin->assignRole('super-admin');

        $this->actingAs($admin)
            ->postJson(route('users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'clinic-admin',
                'is_active' => true,
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.user_management.0', 'Role akun yang sedang kamu gunakan tidak bisa diubah dari modul user management.');

        $this->assertTrue($admin->fresh()->hasRole('super-admin'));
    }

    public function test_clinic_admin_cannot_deactivate_last_active_super_admin(): void
    {
        $superAdmin = User::factory()->create([
            'is_active' => true,
        ]);
        $superAdmin->assignRole('super-admin');

        $clinicAdmin = User::factory()->create([
            'is_active' => true,
        ]);
        $clinicAdmin->assignRole('clinic-admin');

        $this->actingAs($clinicAdmin)
            ->postJson(route('users.update', $superAdmin), [
                'name' => $superAdmin->name,
                'email' => $superAdmin->email,
                'role' => 'super-admin',
                'is_active' => false,
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.user_management.0', 'Sistem harus selalu memiliki minimal satu super-admin aktif.');

        $this->assertTrue($superAdmin->fresh()->is_active);
    }

    public function test_super_admin_can_create_user_and_write_audit_log(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Access User',
                'email' => 'access.user@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'cashier',
                'is_active' => 1,
            ])
            ->assertRedirect(route('users'));

        $user = User::query()->where('email', 'access.user@example.com')->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'user_management',
            'action' => 'create',
            'auditable_type' => $user->getMorphClass(),
            'auditable_id' => $user->getKey(),
            'user_id' => $admin->getKey(),
        ]);
    }

    public function test_super_admin_can_update_user_and_write_audit_log(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $target = User::factory()->create([
            'name' => 'Queue Officer',
            'email' => 'queue.officer@example.com',
            'is_active' => true,
        ]);
        $target->assignRole('front-office');

        $this->actingAs($admin)
            ->post(route('users.update', $target), [
                'name' => 'Queue Supervisor',
                'email' => 'queue.supervisor@example.com',
                'role' => 'cashier',
                'is_active' => 1,
            ])
            ->assertRedirect(route('users'));

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'user_management',
            'action' => 'update',
            'auditable_type' => $target->getMorphClass(),
            'auditable_id' => $target->getKey(),
            'user_id' => $admin->getKey(),
        ]);
    }

    public function test_repeating_same_update_is_idempotent_and_does_not_create_audit_log(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $target = User::factory()->create([
            'name' => 'Stable User',
            'email' => 'stable.user@example.com',
            'is_active' => true,
        ]);
        $target->assignRole('cashier');

        $response = $this->actingAs($admin)
            ->postJson(route('users.update', $target), [
                'name' => 'Stable User',
                'email' => 'stable.user@example.com',
                'role' => 'cashier',
                'is_active' => true,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertDatabaseMissing('audit_logs', [
            'module' => 'user_management',
            'action' => 'update',
            'auditable_type' => $target->getMorphClass(),
            'auditable_id' => $target->getKey(),
        ]);
    }

    public function test_super_admin_can_archive_user_and_revoke_sessions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $target = User::factory()->create([
            'is_active' => true,
            'remember_token' => 'remember-me',
        ]);
        $target->assignRole('cashier');

        DB::table('sessions')->insert([
            'id' => 'session-test-user',
            'user_id' => $target->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($admin)
            ->postJson(route('users.archive', $target), [
                'reason' => 'Offboarding',
            ])
            ->assertOk()
            ->assertJsonPath('data.changed', true);

        $this->assertFalse($target->fresh()->is_active);
        $this->assertNull($target->fresh()->remember_token);
        $this->assertDatabaseMissing('sessions', [
            'user_id' => $target->getKey(),
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'user_management',
            'action' => 'archive',
            'auditable_type' => $target->getMorphClass(),
            'auditable_id' => $target->getKey(),
            'user_id' => $admin->getKey(),
        ]);
    }
}
