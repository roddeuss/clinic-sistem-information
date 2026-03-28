<?php

namespace Tests\Feature\MedicalRecords;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Doctor;
use App\Models\Icd10Code;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordAudit;
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

class MedicalRecordManagementHardeningTest extends TestCase
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

    public function test_medical_record_index_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('medical-records'))
            ->assertForbidden();
    }

    public function test_medical_record_index_json_supports_filters_sort_and_pagination(): void
    {
        $doctorUser = User::factory()->create();
        $doctorUser->assignRole('doctor');

        $branch = Branch::query()->firstOrFail();
        $doctor = $this->createDoctor();

        $firstVisit = $this->createVisit($branch, $doctor, [
            'patient_name' => 'Alpha Patient',
            'patient_nik' => '3174000000003001',
            'patient_phone' => '081300000101',
        ]);

        $secondVisit = $this->createVisit($branch, $doctor, [
            'patient_name' => 'Zeta Patient',
            'patient_nik' => '3174000000003002',
            'patient_phone' => '081300000102',
        ]);

        $response = $this->actingAs($doctorUser)->getJson(route('medical-records', [
            'branch' => $branch->id,
            'status' => 'pending',
            'date' => now()->toDateString(),
            'sort_by' => 'created_at',
            'sort_direction' => 'desc',
            'per_page' => 10,
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('data.filters.branch', (string) $branch->id)
            ->assertJsonPath('data.filters.status', 'pending')
            ->assertJsonPath('data.filters.sort_by', 'created_at')
            ->assertJsonPath('data.filters.per_page', 10)
            ->assertJsonCount(2, 'data.visits.data')
            ->assertJsonPath('data.visits.data.0.id', $secondVisit->id);

        $this->assertNotSame($firstVisit->id, $secondVisit->id);
    }

    public function test_create_final_medical_record_writes_audit_logs_and_refreshes_visit(): void
    {
        $doctorUser = User::factory()->create();
        $doctorUser->assignRole('doctor');

        $branch = Branch::query()->firstOrFail();
        $doctor = $this->createDoctor();
        $visit = $this->createVisit($branch, $doctor);
        [$primary, $secondary] = $this->createIcd10Codes();

        $response = $this->actingAs($doctorUser)->postJson(route('medical-records.store'), [
            'visit_registration_id' => $visit->id,
            'doctor_id' => $doctor->id,
            'subjective' => 'Batuk pilek sejak dua hari.',
            'objective' => 'Keadaan umum baik.',
            'assessment' => 'ISPA akut non komplikata.',
            'plan' => 'Terapi simptomatik.',
            'diagnosis_notes' => 'Catatan testing.',
            'primary_icd10_id' => $primary->id,
            'secondary_icd10_ids' => [$secondary->id],
            'submit_action' => 'final',
        ]);

        $record = MedicalRecord::query()->firstOrFail();

        $response
            ->assertCreated()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.medical_record.status', 'final');

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'medical_record_management',
            'action' => 'create',
            'auditable_type' => $record->getMorphClass(),
            'auditable_id' => $record->id,
        ]);

        $this->assertDatabaseHas('medical_record_audits', [
            'medical_record_id' => $record->id,
            'action' => 'finalized',
        ]);

        $visit->refresh();
        $this->assertSame('ready_for_checkout', $visit->care_stage);
    }

    public function test_create_medical_record_is_idempotent_for_same_payload(): void
    {
        $doctorUser = User::factory()->create();
        $doctorUser->assignRole('doctor');

        $branch = Branch::query()->firstOrFail();
        $doctor = $this->createDoctor();
        $visit = $this->createVisit($branch, $doctor);
        [$primary, $secondary] = $this->createIcd10Codes();

        $payload = [
            'visit_registration_id' => $visit->id,
            'doctor_id' => $doctor->id,
            'subjective' => 'Batuk pilek sejak dua hari.',
            'objective' => 'Keadaan umum baik.',
            'assessment' => 'ISPA akut non komplikata.',
            'plan' => 'Terapi simptomatik.',
            'diagnosis_notes' => 'Catatan testing.',
            'primary_icd10_id' => $primary->id,
            'secondary_icd10_ids' => [$secondary->id],
            'submit_action' => 'final',
        ];

        $this->actingAs($doctorUser)
            ->postJson(route('medical-records.store'), $payload)
            ->assertCreated();

        $this->actingAs($doctorUser)
            ->postJson(route('medical-records.store'), $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertSame(1, MedicalRecord::query()->count());
        $this->assertSame(
            1,
            AuditLog::query()->where('module', 'medical_record_management')->where('action', 'create')->count()
        );
        $this->assertSame(
            1,
            MedicalRecordAudit::query()->where('action', 'finalized')->count()
        );
    }

    public function test_update_medical_record_cannot_move_record_to_another_visit(): void
    {
        $doctorUser = User::factory()->create();
        $doctorUser->assignRole('doctor');

        $branch = Branch::query()->firstOrFail();
        $doctor = $this->createDoctor();
        $visit = $this->createVisit($branch, $doctor);
        $otherVisit = $this->createVisit($branch, $doctor, [
            'patient_name' => 'Other Patient',
            'patient_nik' => '3174000000003004',
            'patient_phone' => '081300000104',
        ]);
        [$primary, $secondary] = $this->createIcd10Codes();

        $record = MedicalRecord::query()->create([
            'visit_registration_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'branch_id' => $visit->branch_id,
            'section_id' => $visit->section_id,
            'doctor_id' => $doctor->id,
            'subjective' => 'Draft',
            'objective' => 'Draft',
            'assessment' => 'Draft',
            'plan' => 'Draft',
            'diagnosis_notes' => 'Draft',
            'status' => 'draft',
        ]);

        $record->diagnoses()->create([
            'icd10_code_id' => $primary->id,
            'diagnosis_type' => 'primary',
            'sort_order' => 1,
        ]);
        $record->diagnoses()->create([
            'icd10_code_id' => $secondary->id,
            'diagnosis_type' => 'secondary',
            'sort_order' => 1,
        ]);

        $this->actingAs($doctorUser)
            ->postJson(route('medical-records.update', $record), [
                'visit_registration_id' => $otherVisit->id,
                'doctor_id' => $doctor->id,
                'subjective' => 'Update',
                'objective' => 'Update',
                'assessment' => 'Update',
                'plan' => 'Update',
                'diagnosis_notes' => 'Update',
                'primary_icd10_id' => $primary->id,
                'secondary_icd10_ids' => [$secondary->id],
                'submit_action' => 'draft',
            ])
            ->assertStatus(409)
            ->assertJsonPath('errors.visit_registration_id.0', 'Rekam medis yang sudah tercatat tidak boleh dipindahkan ke visit lain.');
    }

    public function test_doctor_request_reopen_and_admin_approve_are_idempotent(): void
    {
        $doctorUser = User::factory()->create();
        $doctorUser->assignRole('doctor');

        $adminUser = User::factory()->create();
        $adminUser->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        $doctor = $this->createDoctor();
        $visit = $this->createVisit($branch, $doctor);

        $record = MedicalRecord::query()->create([
            'visit_registration_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'branch_id' => $visit->branch_id,
            'section_id' => $visit->section_id,
            'doctor_id' => $doctor->id,
            'subjective' => 'Final',
            'objective' => 'Final',
            'assessment' => 'Final',
            'plan' => 'Final',
            'diagnosis_notes' => 'Final',
            'status' => 'final',
            'finalized_at' => now(),
            'finalized_by_user_id' => $doctorUser->id,
        ]);

        $this->actingAs($doctorUser)
            ->postJson(route('medical-records.request-reopen', $record), [
                'reason' => 'Perlu koreksi plan.',
            ])
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.medical_record.status', 'reopen_requested');

        $this->actingAs($adminUser)
            ->postJson(route('medical-records.approve-reopen', $record), [
                'reason' => 'Disetujui untuk revisi.',
            ])
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.medical_record.status', 'reopened');

        $this->actingAs($adminUser)
            ->postJson(route('medical-records.approve-reopen', $record->fresh()), [
                'reason' => 'Disetujui untuk revisi.',
            ])
            ->assertOk()
            ->assertJsonPath('data.changed', false);
    }

    private function createDoctor(): Doctor
    {
        return Doctor::query()->create([
            'full_name' => 'dr. Hardening ' . fake()->unique()->numberBetween(1, 999),
            'specialization' => 'General Practice',
            'consultation_fee' => 125000,
            'is_active' => true,
        ]);
    }

    private function createVisit(Branch $branch, Doctor $doctor, array $overrides = []): VisitRegistration
    {
        $counter = Counter::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Counter MR ' . fake()->unique()->numberBetween(1, 999),
            'code' => 'CMR' . fake()->unique()->numberBetween(1, 999),
            'is_active' => true,
        ]);

        $section = Section::query()->create([
            'branch_id' => $branch->id,
            'name' => 'General MR ' . fake()->unique()->numberBetween(1, 999),
            'code' => 'MR-' . fake()->unique()->numberBetween(1, 999),
            'type' => 'regular',
            'queue_prefix' => 'MR',
            'queue_number_padding' => 3,
            'allow_appointment' => true,
            'allow_walk_in' => true,
            'is_active' => true,
        ]);
        $section->doctors()->attach($doctor->id);

        $patient = Patient::query()->create([
            'full_name' => $overrides['patient_name'] ?? 'Patient MR ' . fake()->unique()->numberBetween(1, 999),
            'gender' => 'female',
            'date_of_birth' => '1995-02-14',
            'nik' => $overrides['patient_nik'] ?? (string) fake()->unique()->numberBetween(3174000000003000, 3174999999999999),
            'phone' => $overrides['patient_phone'] ?? '0813' . fake()->unique()->numerify('######'),
            'is_active' => true,
        ]);

        $record = PatientBranchRecord::query()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'medical_record_no' => 'P-' . fake()->unique()->numerify('#####'),
        ]);

        return VisitRegistration::query()->create([
            'patient_id' => $patient->id,
            'patient_branch_record_id' => $record->id,
            'branch_id' => $branch->id,
            'counter_id' => $counter->id,
            'section_id' => $section->id,
            'doctor_id' => $doctor->id,
            'doctor_schedule_id' => null,
            'visit_date' => $overrides['visit_date'] ?? now()->toDateString(),
            'visit_type' => 'same_day',
            'registration_status' => $overrides['registration_status'] ?? 'queued',
            'care_stage' => $overrides['care_stage'] ?? 'waiting_doctor',
            'vital_status' => $overrides['vital_status'] ?? 'completed',
            'booking_code' => null,
            'slot_start_time' => null,
            'slot_end_time' => null,
            'notes' => 'Medical record hardening visit',
            'checked_in_at' => now(),
            'queued_at' => now(),
        ]);
    }

    private function createIcd10Codes(): array
    {
        $primary = Icd10Code::query()->create([
            'code' => 'J06.9',
            'name_en' => 'Acute upper respiratory infection, unspecified',
            'name_id' => 'Infeksi saluran napas atas akut, tidak spesifik',
            'is_active' => true,
        ]);

        $secondary = Icd10Code::query()->create([
            'code' => 'R05',
            'name_en' => 'Cough',
            'name_id' => 'Batuk',
            'is_active' => true,
        ]);

        return [$primary, $secondary];
    }
}
