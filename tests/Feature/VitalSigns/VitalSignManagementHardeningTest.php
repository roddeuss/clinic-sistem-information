<?php

namespace Tests\Feature\VitalSigns;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\PatientBranchRecord;
use App\Models\Section;
use App\Models\User;
use App\Models\VisitRegistration;
use App\Models\VitalSignRecord;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VitalSignManagementHardeningTest extends TestCase
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

    public function test_vital_sign_index_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('vital-signs'))
            ->assertForbidden();
    }

    public function test_vital_sign_index_json_supports_filter_sort_and_pagination(): void
    {
        $user = User::factory()->create();
        $user->assignRole('nurse');

        $branch = Branch::query()->firstOrFail();
        $recordA = $this->createVitalRecord($branch, [
            'patient_name' => 'Alpha Patient',
            'patient_nik' => '3174000000002001',
            'patient_phone' => '081300000001',
            'recorded_at' => now()->subMinutes(10),
            'systolic_bp' => 110,
        ]);

        $recordB = $this->createVitalRecord($branch, [
            'patient_name' => 'Zeta Patient',
            'patient_nik' => '3174000000002002',
            'patient_phone' => '081300000002',
            'recorded_at' => now(),
            'systolic_bp' => 145,
        ]);

        $response = $this->actingAs($user)->getJson(route('vital-signs', [
            'branch' => $branch->id,
            'date' => now()->toDateString(),
            'sort_by' => 'systolic_bp',
            'sort_direction' => 'desc',
            'per_page' => 10,
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('data.filters.branch', (string) $branch->id)
            ->assertJsonPath('data.filters.sort_by', 'systolic_bp')
            ->assertJsonPath('data.filters.sort_direction', 'desc')
            ->assertJsonCount(2, 'data.vital_signs.data')
            ->assertJsonPath('data.vital_signs.data.0.id', $recordB->id);

        $this->assertNotSame($recordA->id, $recordB->id);
    }

    public function test_create_vital_sign_writes_audit_log_and_advances_visit_stage(): void
    {
        $user = User::factory()->create();
        $user->assignRole('nurse');

        $branch = Branch::query()->firstOrFail();
        $visit = $this->createVisit($branch, [
            'care_stage' => 'waiting_nurse',
            'vital_status' => 'pending',
        ]);

        $response = $this->actingAs($user)->postJson(route('vital-signs.store'), [
            'visit_registration_id' => $visit->id,
            'systolic_bp' => 123,
            'diastolic_bp' => 81,
            'temperature_celsius' => 36.8,
            'pulse_rate' => 82,
            'respiratory_rate' => 20,
            'weight_kg' => 62.5,
            'height_cm' => 168,
            'spo2_percent' => 98,
            'notes' => 'Pengukuran awal',
            'recorded_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $record = VitalSignRecord::query()->latest('id')->firstOrFail();

        $response
            ->assertCreated()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.vital_sign.systolic_bp', 123);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'vital_sign_management',
            'action' => 'create',
            'auditable_type' => $record->getMorphClass(),
            'auditable_id' => $record->id,
        ]);

        $visit->refresh();
        $this->assertSame('waiting_doctor', $visit->care_stage);
        $this->assertSame('completed', $visit->vital_status);
    }

    public function test_create_vital_sign_is_idempotent_for_same_payload_and_timestamp(): void
    {
        $user = User::factory()->create();
        $user->assignRole('nurse');

        $branch = Branch::query()->firstOrFail();
        $visit = $this->createVisit($branch);
        $recordedAt = now()->format('Y-m-d H:i:s');

        $payload = [
            'visit_registration_id' => $visit->id,
            'systolic_bp' => 120,
            'diastolic_bp' => 80,
            'temperature_celsius' => 36.7,
            'pulse_rate' => 84,
            'respiratory_rate' => 19,
            'weight_kg' => 60.5,
            'height_cm' => 165,
            'spo2_percent' => 97,
            'notes' => 'Vital normal',
            'recorded_at' => $recordedAt,
        ];

        $this->actingAs($user)->postJson(route('vital-signs.store'), $payload)->assertCreated();

        $this->actingAs($user)
            ->postJson(route('vital-signs.store'), $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertSame(1, VitalSignRecord::query()->where('visit_registration_id', $visit->id)->count());
        $this->assertSame(
            1,
            AuditLog::query()->where('module', 'vital_sign_management')->where('action', 'create')->count()
        );
    }

    public function test_update_vital_sign_is_idempotent_when_payload_is_the_same(): void
    {
        $user = User::factory()->create();
        $user->assignRole('nurse');

        $branch = Branch::query()->firstOrFail();
        $record = $this->createVitalRecord($branch, [
            'recorded_by_user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->postJson(route('vital-signs.update', $record), [
            'visit_registration_id' => $record->visit_registration_id,
            'systolic_bp' => $record->systolic_bp,
            'diastolic_bp' => $record->diastolic_bp,
            'temperature_celsius' => (float) $record->temperature_celsius,
            'pulse_rate' => $record->pulse_rate,
            'respiratory_rate' => $record->respiratory_rate,
            'weight_kg' => (float) $record->weight_kg,
            'height_cm' => (float) $record->height_cm,
            'spo2_percent' => $record->spo2_percent,
            'notes' => $record->notes,
            'recorded_at' => $record->recorded_at?->format('Y-m-d H:i:s'),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertSame(
            0,
            AuditLog::query()->where('module', 'vital_sign_management')->where('action', 'update')->count()
        );
    }

    public function test_update_vital_sign_is_blocked_when_medical_record_is_final(): void
    {
        $user = User::factory()->create();
        $user->assignRole('nurse');

        $branch = Branch::query()->firstOrFail();
        $record = $this->createVitalRecord($branch);

        MedicalRecord::query()->create([
            'visit_registration_id' => $record->visit_registration_id,
            'patient_id' => $record->patient_id,
            'branch_id' => $record->branch_id,
            'section_id' => $record->section_id,
            'doctor_id' => null,
            'status' => 'final',
            'finalized_at' => now(),
            'finalized_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->postJson(route('vital-signs.update', $record), [
                'visit_registration_id' => $record->visit_registration_id,
                'systolic_bp' => 130,
                'diastolic_bp' => 85,
                'temperature_celsius' => 37.0,
                'pulse_rate' => 88,
                'respiratory_rate' => 21,
                'weight_kg' => 61.5,
                'height_cm' => 165,
                'spo2_percent' => 99,
                'notes' => 'Koreksi setelah final',
                'recorded_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertStatus(409)
            ->assertJsonPath('errors.vital_sign_management.0', 'Vital signs tidak bisa diubah ketika rekam medis sudah final atau menunggu reopen.');
    }

    public function test_update_vital_sign_cannot_move_record_to_another_visit(): void
    {
        $user = User::factory()->create();
        $user->assignRole('nurse');

        $branch = Branch::query()->firstOrFail();
        $record = $this->createVitalRecord($branch);
        $otherVisit = $this->createVisit($branch);

        $this->actingAs($user)
            ->postJson(route('vital-signs.update', $record), [
                'visit_registration_id' => $otherVisit->id,
                'systolic_bp' => 128,
                'diastolic_bp' => 82,
                'temperature_celsius' => 36.9,
                'pulse_rate' => 84,
                'respiratory_rate' => 20,
                'weight_kg' => 60.5,
                'height_cm' => 165,
                'spo2_percent' => 98,
                'notes' => 'Mencoba pindah visit',
                'recorded_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertStatus(409)
            ->assertJsonPath('errors.vital_sign_management.0', 'Vital signs yang sudah tercatat tidak boleh dipindahkan ke visit lain.');
    }

    private function createVitalRecord(Branch $branch, array $overrides = []): VitalSignRecord
    {
        $visit = $overrides['visit'] ?? $this->createVisit($branch, $overrides);

        return VitalSignRecord::query()->create([
            'visit_registration_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'branch_id' => $visit->branch_id,
            'section_id' => $visit->section_id,
            'recorded_by_user_id' => $overrides['recorded_by_user_id'] ?? null,
            'systolic_bp' => $overrides['systolic_bp'] ?? 120,
            'diastolic_bp' => $overrides['diastolic_bp'] ?? 80,
            'temperature_celsius' => $overrides['temperature_celsius'] ?? 36.7,
            'pulse_rate' => $overrides['pulse_rate'] ?? 84,
            'respiratory_rate' => $overrides['respiratory_rate'] ?? 19,
            'weight_kg' => $overrides['weight_kg'] ?? 60.5,
            'height_cm' => $overrides['height_cm'] ?? 165,
            'spo2_percent' => $overrides['spo2_percent'] ?? 98,
            'bmi' => 22.22,
            'notes' => $overrides['notes'] ?? 'Initial vital',
            'recorded_at' => $overrides['recorded_at'] ?? now(),
        ]);
    }

    private function createVisit(Branch $branch, array $overrides = []): VisitRegistration
    {
        $counter = Counter::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Counter Vital ' . fake()->unique()->numberBetween(1, 999),
            'code' => 'CV' . fake()->unique()->numberBetween(1, 999),
            'is_active' => true,
        ]);

        $section = Section::query()->create([
            'branch_id' => $branch->id,
            'name' => 'General ' . fake()->unique()->numberBetween(1, 999),
            'code' => 'GENERAL-' . fake()->unique()->numberBetween(1, 999),
            'type' => 'regular',
            'queue_prefix' => 'GEN',
            'queue_number_padding' => 3,
            'allow_appointment' => true,
            'allow_walk_in' => true,
            'is_active' => true,
        ]);

        $patient = Patient::query()->create([
            'full_name' => $overrides['patient_name'] ?? 'Patient Vital ' . fake()->unique()->numberBetween(1, 999),
            'gender' => 'female',
            'date_of_birth' => '1995-02-14',
            'nik' => $overrides['patient_nik'] ?? (string) fake()->unique()->numberBetween(3174000000002000, 3174999999999999),
            'phone' => $overrides['patient_phone'] ?? '0813' . fake()->unique()->numerify('######'),
            'email' => null,
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
            'doctor_id' => null,
            'doctor_schedule_id' => null,
            'visit_date' => $overrides['visit_date'] ?? now()->toDateString(),
            'visit_type' => 'same_day',
            'registration_status' => $overrides['registration_status'] ?? 'queued',
            'care_stage' => $overrides['care_stage'] ?? 'waiting_nurse',
            'vital_status' => $overrides['vital_status'] ?? 'pending',
            'booking_code' => null,
            'slot_start_time' => null,
            'slot_end_time' => null,
            'notes' => 'Vital test visit',
            'checked_in_at' => now(),
            'queued_at' => now(),
        ]);
    }
}
