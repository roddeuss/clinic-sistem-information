<?php

namespace Tests\Feature\Patients;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Patient;
use App\Models\PatientBranchRecord;
use App\Models\Section;
use App\Models\User;
use App\Models\VisitRegistration;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientManagementHardeningTest extends TestCase
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

    public function test_patient_index_requires_patient_view_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('doctor');

        $this->actingAs($user)
            ->get(route('patients'))
            ->assertForbidden();
    }

    public function test_patient_index_json_supports_filter_sort_and_pagination(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();

        $alpha = Patient::query()->create([
            'full_name' => 'Alpha Patient',
            'gender' => 'female',
            'date_of_birth' => '1995-01-01',
            'nik' => '3173000000000001',
            'phone' => '081111111001',
            'email' => null,
            'is_active' => true,
        ]);

        $zeta = Patient::query()->create([
            'full_name' => 'Zeta Patient',
            'gender' => 'male',
            'date_of_birth' => '1990-02-02',
            'nik' => '3173000000000002',
            'phone' => '081111111002',
            'email' => null,
            'is_active' => false,
        ]);

        PatientBranchRecord::query()->create([
            'patient_id' => $alpha->id,
            'branch_id' => $branch->id,
            'medical_record_no' => 'P-00001',
        ]);

        PatientBranchRecord::query()->create([
            'patient_id' => $zeta->id,
            'branch_id' => $branch->id,
            'medical_record_no' => 'P-00002',
        ]);

        $response = $this->actingAs($user)->getJson(route('patients', [
            'status' => 'inactive',
            'branch' => $branch->id,
            'sort_by' => 'created_at',
            'sort_direction' => 'desc',
            'per_page' => 10,
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('data.filters.status', 'inactive')
            ->assertJsonPath('data.filters.branch', (string) $branch->id)
            ->assertJsonPath('data.filters.sort_by', 'created_at')
            ->assertJsonCount(1, 'data.patients.data')
            ->assertJsonPath('data.patients.data.0.full_name', 'Zeta Patient');
    }

    public function test_create_patient_returns_duplicate_warning_and_audit_log(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();

        $existing = Patient::query()->create([
            'full_name' => 'Andini Lestari',
            'gender' => 'female',
            'date_of_birth' => '1994-08-17',
            'nik' => '3174011708940009',
            'phone' => '081234560001',
            'email' => null,
            'is_active' => true,
        ]);

        PatientBranchRecord::query()->create([
            'patient_id' => $existing->id,
            'branch_id' => $branch->id,
            'medical_record_no' => 'P-00001',
        ]);

        $response = $this->actingAs($user)->postJson(route('patients.store'), [
            'branch_id' => $branch->id,
            'full_name' => 'Andini Lestari',
            'gender' => 'female',
            'date_of_birth' => '1994-08-17',
            'nik' => '3174011708940010',
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
            'is_active' => true,
        ]);

        $patient = Patient::query()->where('email', 'andini@example.com')->firstOrFail();

        $response
            ->assertCreated()
            ->assertJsonPath('data.patient.full_name', 'Andini Lestari')
            ->assertJsonCount(1, 'data.duplicate_warnings');

        $this->assertDatabaseHas('patient_branch_records', [
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'patient_management',
            'action' => 'create',
            'auditable_type' => $patient->getMorphClass(),
            'auditable_id' => $patient->id,
        ]);
    }

    public function test_update_patient_is_idempotent_when_payload_is_the_same(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        $patient = Patient::query()->create([
            'full_name' => 'Rizky Saputra',
            'gender' => 'male',
            'date_of_birth' => '1992-03-11',
            'nik' => '3174011103920001',
            'phone' => '081299900001',
            'email' => 'rizky@example.com',
            'province_code' => '31',
            'province_name' => 'DKI Jakarta',
            'city_code' => '3171',
            'city_name' => 'Jakarta Pusat',
            'district_code' => '317101',
            'district_name' => 'Menteng',
            'village_code' => '3171011001',
            'village_name' => 'Menteng',
            'address_line' => 'Jl. Cikini Raya No. 1',
            'allergy_notes' => 'Alergi debu',
            'is_active' => true,
        ]);

        PatientBranchRecord::query()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'medical_record_no' => 'P-00001',
        ]);

        $response = $this->actingAs($user)->postJson(route('patients.update', $patient), [
            'branch_id' => $branch->id,
            'full_name' => 'Rizky Saputra',
            'gender' => 'male',
            'date_of_birth' => '1992-03-11',
            'nik' => '3174011103920001',
            'phone' => '081299900001',
            'email' => 'rizky@example.com',
            'province_code' => '31',
            'province_name' => 'DKI Jakarta',
            'city_code' => '3171',
            'city_name' => 'Jakarta Pusat',
            'district_code' => '317101',
            'district_name' => 'Menteng',
            'village_code' => '3171011001',
            'village_name' => 'Menteng',
            'address_line' => 'Jl. Cikini Raya No. 1',
            'allergy_notes' => 'Alergi debu',
            'is_active' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertSame(0, AuditLog::query()->where('module', 'patient_management')->where('action', 'update')->count());
    }

    public function test_archive_patient_is_blocked_when_there_is_an_active_visit(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        $counter = Counter::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Counter A',
            'code' => 'COUNTER-A',
            'is_active' => true,
        ]);
        $section = Section::query()->create([
            'branch_id' => $branch->id,
            'name' => 'General',
            'code' => 'GENERAL',
            'type' => 'regular',
            'queue_prefix' => 'G',
            'queue_number_padding' => 3,
            'allow_appointment' => true,
            'allow_walk_in' => true,
            'is_active' => true,
        ]);
        $patient = Patient::query()->create([
            'full_name' => 'Budi Hartono',
            'gender' => 'male',
            'date_of_birth' => '1988-12-01',
            'nik' => '3174010112880001',
            'phone' => '081288800001',
            'email' => null,
            'is_active' => true,
        ]);
        $record = PatientBranchRecord::query()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'medical_record_no' => 'P-00001',
        ]);

        VisitRegistration::query()->create([
            'patient_id' => $patient->id,
            'patient_branch_record_id' => $record->id,
            'branch_id' => $branch->id,
            'counter_id' => $counter->id,
            'section_id' => $section->id,
            'doctor_id' => null,
            'doctor_schedule_id' => null,
            'visit_date' => now()->toDateString(),
            'visit_type' => 'same_day',
            'registration_status' => 'queued',
            'care_stage' => 'waiting_nurse',
            'vital_status' => 'pending',
            'notes' => null,
        ]);

        $this->actingAs($user)
            ->postJson(route('patients.archive', $patient), [
                'reason' => 'Data ganda',
            ])
            ->assertStatus(409)
            ->assertJsonPath('errors.patient_management.0', 'Patient masih memiliki registrasi atau antrian aktif, jadi belum bisa dinonaktifkan.');

        $this->assertTrue($patient->fresh()->is_active);
    }

    public function test_archive_patient_marks_it_inactive_and_logs_audit_when_safe(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $patient = Patient::query()->create([
            'full_name' => 'Sari Wulandari',
            'gender' => 'female',
            'date_of_birth' => '1991-04-23',
            'nik' => '3174012304910001',
            'phone' => '081277700001',
            'email' => null,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->postJson(route('patients.archive', $patient), [
                'reason' => 'Pasien meminta data di-nonaktifkan.',
            ])
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.patient.is_active', false);

        $this->assertFalse($patient->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'patient_management',
            'action' => 'archive',
            'auditable_type' => $patient->getMorphClass(),
            'auditable_id' => $patient->id,
        ]);
    }
}
