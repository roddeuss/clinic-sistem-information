<?php

namespace Tests\Feature\Counters;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounterManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            AccessControlSeeder::class,
            ClinicSettingsSeeder::class,
            NavigationSeeder::class,
        ]);
    }

    public function test_clinic_admin_can_open_counters_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $this->actingAs($user)
            ->get(route('counters'))
            ->assertOk()
            ->assertSee('Counters');
    }

    public function test_clinic_admin_can_create_and_update_counter(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();

        $this->actingAs($user)
            ->from(route('counters'))
            ->post(route('counters.store'), [
                'branch_id' => $branch->id,
                'name' => 'Counter 1',
                'code' => '',
                'location' => 'Lobby depan',
                'description' => 'Meja pendaftaran utama.',
                'sort_order' => 10,
                'is_active' => 1,
            ])
            ->assertRedirect(route('counters'));

        $counter = Counter::query()->where('name', 'Counter 1')->firstOrFail();

        $this->assertSame('C1', $counter->code);

        $this->actingAs($user)
            ->from(route('counters'))
            ->post(route('counters.update', $counter), [
                'branch_id' => $branch->id,
                'name' => 'Counter Utama',
                'code' => 'MAIN',
                'location' => 'Area registrasi',
                'description' => 'Dipakai untuk registrasi pasien umum.',
                'sort_order' => 20,
                'is_active' => 0,
            ])
            ->assertRedirect(route('counters'));

        $this->assertDatabaseHas('counters', [
            'id' => $counter->id,
            'name' => 'Counter Utama',
            'code' => 'MAIN',
            'location' => 'Area registrasi',
            'is_active' => false,
        ]);
    }

    public function test_sidebar_respects_counter_permission(): void
    {
        $clinicAdmin = User::factory()->create();
        $clinicAdmin->assignRole('clinic-admin');

        $this->actingAs($clinicAdmin)
            ->get(route('dashboard'))
            ->assertSeeHtml('href="' . route('counters', absolute: false) . '"');

        auth()->logout();

        $frontOffice = User::factory()->create();
        $frontOffice->assignRole('front-office');

        $this->actingAs($frontOffice)
            ->get(route('dashboard'))
            ->assertDontSeeHtml('href="' . route('counters', absolute: false) . '"');
    }
}
