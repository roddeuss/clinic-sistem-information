<?php

namespace Tests\Feature\ClinicSettings;

use App\Models\Branch;
use App\Models\Clinic;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            AccessControlSeeder::class,
            ClinicSettingsSeeder::class,
        ]);
    }

    public function test_clinic_admin_can_update_clinic_and_branch_settings(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $clinic = Clinic::query()->firstOrFail();
        $branch = Branch::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('clinic.profile'), [
                'clinic' => [
                    'name' => 'CSI Clinic Prime',
                    'code' => 'CSI',
                    'logo_path' => '/storage/logos/csi.png',
                    'phone' => '021-777-0001',
                    'email' => 'prime@csiclinic.local',
                    'address' => 'Jl. Klinik Prime No. 2',
                    'invoice_header' => 'CSI Clinic Prime',
                ],
            ])
            ->assertRedirect(route('clinic'));

        $this->actingAs($user)
            ->post(route('clinic-branches.update', $branch), [
                'branch' => [
                    'name' => 'Cabang Selatan',
                    'code' => 'SEL',
                    'phone' => '021-777-0002',
                    'address' => 'Jl. Cabang Selatan No. 10',
                    'opening_time' => '07:00',
                    'closing_time' => '21:00',
                    'queue_prefix' => 'S',
                    'queue_number_padding' => 4,
                    'is_active' => true,
                ],
            ])
            ->assertRedirect(route('clinic'));

        $this->assertDatabaseHas('clinics', [
            'id' => $clinic->id,
            'name' => 'CSI Clinic Prime',
            'logo_path' => '/storage/logos/csi.png',
        ]);

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'name' => 'Cabang Selatan',
            'queue_prefix' => 'S',
            'queue_number_padding' => 4,
        ]);
    }
}
