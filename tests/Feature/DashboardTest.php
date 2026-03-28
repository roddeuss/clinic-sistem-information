<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessControlSeeder::class);
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Clinic Dashboard')
            ->assertSee('Quick Actions');
    }

    public function test_clinic_admin_sees_operational_dashboard_copy(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Clinic Control Center')
            ->assertSee('Visit Hari Ini')
            ->assertSee('Purchase Orders')
            ->assertSee('Queue Snapshot');
    }

    public function test_front_office_sees_front_desk_dashboard_copy(): void
    {
        $user = User::factory()->create();
        $user->assignRole('front-office');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Front Office Desk')
            ->assertSee('Registrasi Hari Ini')
            ->assertSee('Visit Registration')
            ->assertSee('Arrivals & Follow-up Hari Ini');
    }

    public function test_cashier_sees_cashier_dashboard_copy(): void
    {
        $user = User::factory()->create();
        $user->assignRole('cashier');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Cashier Control')
            ->assertSee('Invoice Perlu Tindakan')
            ->assertSee('Cashier Shifts');
    }

    public function test_doctor_sees_doctor_workspace_dashboard_copy(): void
    {
        $user = User::factory()->create([
            'email' => 'doctor@example.com',
        ]);
        $user->assignRole('doctor');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Doctor Workspace')
            ->assertSee('Pasien Saya Hari Ini')
            ->assertSee('Medical Records');
    }

    public function test_nurse_sees_nurse_station_dashboard_copy(): void
    {
        $user = User::factory()->create();
        $user->assignRole('nurse');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Nurse Station')
            ->assertSee('Waiting Vitals')
            ->assertSee('Vital Signs');
    }

    public function test_pharmacist_sees_pharmacy_dashboard_copy(): void
    {
        $user = User::factory()->create();
        $user->assignRole('pharmacist');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Pharmacy Desk')
            ->assertSee('Pending Dispense')
            ->assertSee('Reorder Points');
    }
}
