<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessControlSeeder::class);
    }

    public function test_clinic_admin_can_open_role_permission_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $this->actingAs($user)
            ->get(route('roles'))
            ->assertOk();
    }

    public function test_front_office_is_forbidden_from_role_permission_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('front-office');

        $this->actingAs($user)
            ->get(route('roles'))
            ->assertForbidden();
    }

    public function test_super_admin_can_update_user_role_and_active_status(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $target = User::factory()->create([
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('users.update', $target), [
                'name' => 'Cashier Candidate',
                'email' => 'cashier.candidate@example.com',
                'role' => 'cashier',
                'is_active' => 0,
            ])
            ->assertRedirect(route('users'));

        $this->assertTrue($target->fresh()->hasRole('cashier'));
        $this->assertFalse($target->fresh()->is_active);
        $this->assertSame('Cashier Candidate', $target->fresh()->name);
        $this->assertSame('cashier.candidate@example.com', $target->fresh()->email);
    }

    public function test_super_admin_can_update_role_permissions_from_modal_flow(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $frontOfficeRole = Role::query()->where('name', 'front-office')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('roles.update', $frontOfficeRole), [
                'permissions' => [
                    'view dashboard',
                    'view menu management',
                ],
            ])
            ->assertRedirect(route('roles'));

        $this->assertTrue($frontOfficeRole->hasPermissionTo('view dashboard'));
        $this->assertTrue($frontOfficeRole->fresh()->hasPermissionTo('view menu management'));
    }

    public function test_roles_page_recreates_missing_permission_records(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        Permission::query()->where('name', 'view role permission')->delete();

        $this->actingAs($admin)
            ->get(route('roles'))
            ->assertOk();

        $this->assertDatabaseHas('permissions', [
            'name' => 'view role permission',
        ]);
    }

    public function test_super_admin_can_create_role_and_user_for_ui_testing(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $this->actingAs($admin)
            ->post(route('roles.store'), [
                'name' => 'Inventory Admin',
            ])
            ->assertRedirect(route('roles'));

        $this->assertDatabaseHas('roles', [
            'name' => 'inventory-admin',
        ]);

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Inventory Supervisor',
                'email' => 'inventory.supervisor@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'inventory-admin',
                'is_active' => 1,
            ])
            ->assertRedirect(route('users'));

        $user = User::query()->where('email', 'inventory.supervisor@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('inventory-admin'));
        $this->assertTrue($user->is_active);
    }

    public function test_dashboard_shows_role_specific_operational_content(): void
    {
        $clinicAdmin = User::factory()->create();
        $clinicAdmin->assignRole('clinic-admin');

        $this->actingAs($clinicAdmin)
            ->get(route('dashboard'))
            ->assertSee('Clinic Control Center')
            ->assertSee('Purchase Orders')
            ->assertSee('Reports')
            ->assertSee('Queue Snapshot');

        auth()->logout();

        $frontOffice = User::factory()->create();
        $frontOffice->assignRole('front-office');

        $this->actingAs($frontOffice)
            ->get(route('dashboard'))
            ->assertSee('Front Office Desk')
            ->assertSee('Visit Registration')
            ->assertSee('Arrivals & Follow-up Hari Ini')
            ->assertDontSee('Purchase Orders')
            ->assertDontSee('Reports');
    }
}
