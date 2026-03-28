<?php

namespace Tests\Feature\Patients;

use App\Models\Branch;
use App\Models\Patient;
use App\Models\PatientBranchRecord;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PatientManagementTest extends TestCase
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

    public function test_clinic_admin_can_open_patients_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $this->actingAs($user)
            ->get(route('patients'))
            ->assertOk()
            ->assertSee('Patients');
    }

    public function test_clinic_admin_can_create_and_update_patient_with_branch_record(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();

        $this->actingAs($user)
            ->from(route('patients'))
            ->post(route('patients.store'), [
                'branch_id' => $branch->id,
                'full_name' => 'Andini Lestari',
                'gender' => 'female',
                'date_of_birth' => '1994-08-17',
                'nik' => '3174011708940001',
                'phone' => '081234560001',
                'email' => 'andini@example.com',
                'province_code' => '31',
                'province_name' => 'DKI Jakarta',
                'city_code' => '3173',
                'city_name' => 'Kota Jakarta Barat',
                'district_code' => '317304',
                'district_name' => 'Palmerah',
                'village_code' => '3173041001',
                'village_name' => 'Slipi',
                'address_line' => 'Jl. Palmerah Barat No. 10',
                'allergy_notes' => 'Alergi udang',
                'is_active' => 1,
            ])
            ->assertRedirect(route('patients'));

        $patient = Patient::query()->where('phone', '081234560001')->firstOrFail();

        $this->assertSame('Andini Lestari', $patient->full_name);
        $this->assertDatabaseHas('patient_branch_records', [
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($user)
            ->from(route('patients'))
            ->post(route('patients.update', $patient), [
                'branch_id' => $branch->id,
                'full_name' => 'Andini Putri Lestari',
                'gender' => 'female',
                'date_of_birth' => '1994-08-17',
                'nik' => '3174011708940001',
                'phone' => '081234560001',
                'email' => 'andini@example.com',
                'province_code' => '31',
                'province_name' => 'DKI Jakarta',
                'city_code' => '3173',
                'city_name' => 'Kota Jakarta Barat',
                'district_code' => '317304',
                'district_name' => 'Palmerah',
                'village_code' => '3173041001',
                'village_name' => 'Slipi',
                'address_line' => 'Jl. Palmerah Barat No. 99',
                'allergy_notes' => 'Alergi udang dan kacang',
                'is_active' => 1,
            ])
            ->assertRedirect(route('patients'));

        $patient->refresh();

        $this->assertSame('Andini Putri Lestari', $patient->full_name);
        $this->assertSame('Jl. Palmerah Barat No. 99', $patient->address_line);
        $this->assertCount(1, PatientBranchRecord::query()->where('patient_id', $patient->id)->get());
    }

    public function test_region_proxy_returns_external_region_data(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        Http::fake([
            '*' => Http::response([
                ['id' => '31', 'name' => 'DKI Jakarta'],
            ], 200),
        ]);

        $this->actingAs($user)
            ->get(route('regions.provinces'))
            ->assertOk()
            ->assertJsonPath('data.0.code', '31')
            ->assertJsonPath('data.0.name', 'DKI Jakarta');
    }
}
