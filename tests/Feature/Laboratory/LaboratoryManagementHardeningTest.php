<?php

namespace Tests\Feature\Laboratory;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Doctor;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryTest;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Section;
use App\Models\User;
use App\Models\VisitRegistration;
use App\Services\PatientRecordService;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaboratoryManagementHardeningTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            AccessControlSeeder::class,
            ClinicSettingsSeeder::class,
            NavigationSeeder::class,
        ]);
    }

    public function test_laboratory_index_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('laboratory'))
            ->assertForbidden();
    }

    public function test_laboratory_index_json_supports_filters_sort_and_pagination(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        [$counter, $section, $doctor] = $this->createContext($branch);
        $visit = $this->createReadyVisit($branch, $counter, $section, $doctor, $user, [
            'patient_name' => 'Alpha Diagnostic',
            'patient_nik' => '3174000000051001',
        ]);

        $test = LaboratoryTest::query()->create([
            'code' => 'ALPHA-LAB',
            'name' => 'Alpha Lab',
            'diagnostic_category' => 'laboratory',
            'sample_type' => 'Blood',
            'default_provider_type' => 'internal',
            'result_entry_mode' => 'structured',
            'description' => 'Alpha description',
            'is_active' => true,
        ]);

        LaboratoryOrder::query()->create([
            'visit_registration_id' => $visit->id,
            'branch_id' => $visit->branch_id,
            'section_id' => $visit->section_id,
            'laboratory_test_id' => $test->id,
            'ordered_by_doctor_id' => $doctor->id,
            'provider_type' => 'internal',
            'status' => 'ordered',
            'unit_price' => 90000,
            'ordered_at' => now(),
            'notes' => 'Alpha note',
        ]);

        $response = $this->actingAs($user)->getJson(route('laboratory', [
            'search' => 'Alpha',
            'branch' => $branch->id,
            'status' => 'ordered',
            'provider_type' => 'internal',
            'date' => now()->toDateString(),
            'test_status' => 'active',
            'test_sort_by' => 'name',
            'test_sort_direction' => 'asc',
            'test_per_page' => 10,
            'order_sort_by' => 'ordered_at',
            'order_sort_direction' => 'desc',
            'order_per_page' => 10,
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('data.filters.search', 'Alpha')
            ->assertJsonPath('data.filters.branch', (string) $branch->id)
            ->assertJsonPath('data.filters.test_sort_by', 'name')
            ->assertJsonPath('data.filters.order_per_page', 10)
            ->assertJsonPath('data.tests.data.0.name', 'Alpha Lab')
            ->assertJsonPath('data.orders.data.0.status', 'ordered');
    }

    public function test_create_test_is_idempotent_and_writes_audit_log(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');
        $branch = Branch::query()->firstOrFail();

        $payload = [
            'code' => 'CBC',
            'name' => 'Complete Blood Count',
            'diagnostic_category' => 'laboratory',
            'sample_type' => 'Blood',
            'default_provider_type' => 'internal',
            'result_entry_mode' => 'structured',
            'description' => 'CBC profile',
            'parameter_lines' => "HB|Hemoglobin|g/dL|12-15\nWBC|Leukocyte|10^3/uL|4-10",
            'internal_prices' => [$branch->id => 85000],
            'external_prices' => [$branch->id => 110000],
            'is_active' => true,
        ];

        $this->actingAs($user)
            ->postJson(route('laboratory-tests.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.changed', true);

        $this->actingAs($user)
            ->postJson(route('laboratory-tests.store'), $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $test = LaboratoryTest::query()->where('code', 'CBC')->firstOrFail();

        $this->assertSame(1, LaboratoryTest::query()->count());
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'laboratory_management',
            'action' => 'create_test',
            'auditable_type' => $test->getMorphClass(),
            'auditable_id' => $test->id,
        ]);
    }

    public function test_update_test_is_idempotent_for_same_payload(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');
        $branch = Branch::query()->firstOrFail();

        $test = LaboratoryTest::query()->create([
            'code' => 'XRAY',
            'name' => 'X-Ray',
            'diagnostic_category' => 'radiology',
            'sample_type' => null,
            'default_provider_type' => 'external',
            'result_entry_mode' => 'narrative',
            'description' => 'Initial description',
            'is_active' => true,
        ]);

        $payload = [
            'code' => 'XRAY',
            'name' => 'Chest X-Ray',
            'diagnostic_category' => 'radiology',
            'sample_type' => null,
            'default_provider_type' => 'external',
            'result_entry_mode' => 'narrative',
            'description' => 'Updated description',
            'parameter_lines' => '',
            'internal_prices' => [],
            'external_prices' => [$branch->id => 175000],
            'is_active' => true,
        ];

        $this->actingAs($user)
            ->postJson(route('laboratory-tests.update', $test), $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', true);

        $this->actingAs($user)
            ->postJson(route('laboratory-tests.update', $test), $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertSame(
            1,
            AuditLog::query()->where('module', 'laboratory_management')->where('action', 'update_test')->count()
        );
    }

    public function test_update_order_cannot_move_to_another_visit(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        [$counter, $section, $doctor] = $this->createContext($branch);
        $visit = $this->createReadyVisit($branch, $counter, $section, $doctor, $user, [
            'patient_name' => 'Diagnostic One',
            'patient_nik' => '3174000000052001',
        ]);
        $otherVisit = $this->createReadyVisit($branch, $counter, $section, $doctor, $user, [
            'patient_name' => 'Diagnostic Two',
            'patient_nik' => '3174000000052002',
        ]);

        $test = LaboratoryTest::query()->create([
            'code' => 'GLU',
            'name' => 'Blood Glucose',
            'diagnostic_category' => 'laboratory',
            'sample_type' => 'Blood',
            'default_provider_type' => 'internal',
            'result_entry_mode' => 'structured',
            'is_active' => true,
        ]);

        $order = LaboratoryOrder::query()->create([
            'visit_registration_id' => $visit->id,
            'branch_id' => $visit->branch_id,
            'section_id' => $visit->section_id,
            'laboratory_test_id' => $test->id,
            'ordered_by_doctor_id' => $doctor->id,
            'provider_type' => 'internal',
            'status' => 'ordered',
            'unit_price' => 75000,
            'ordered_at' => now(),
            'notes' => null,
        ]);

        $this->actingAs($user)
            ->postJson(route('laboratory-orders.update', $order), [
                'visit_registration_id' => $otherVisit->id,
                'laboratory_test_id' => $test->id,
                'provider_type' => 'internal',
                'status' => 'ordered',
                'partner_name' => null,
                'external_reference_no' => null,
                'result_attachment_path' => null,
                'result_summary' => null,
                'result_impression' => null,
                'result_lines' => null,
                'notes' => null,
            ])
            ->assertStatus(409)
            ->assertJsonPath('errors.visit_registration_id.0', 'Diagnostic order yang sudah tercatat tidak boleh dipindahkan ke visit lain.');
    }

    public function test_cancel_order_is_idempotent_and_logged(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        [$counter, $section, $doctor] = $this->createContext($branch);
        $visit = $this->createReadyVisit($branch, $counter, $section, $doctor, $user);

        $test = LaboratoryTest::query()->create([
            'code' => 'URN',
            'name' => 'Urinalysis',
            'diagnostic_category' => 'laboratory',
            'sample_type' => 'Urine',
            'default_provider_type' => 'internal',
            'result_entry_mode' => 'structured',
            'is_active' => true,
        ]);

        $order = LaboratoryOrder::query()->create([
            'visit_registration_id' => $visit->id,
            'branch_id' => $visit->branch_id,
            'section_id' => $visit->section_id,
            'laboratory_test_id' => $test->id,
            'ordered_by_doctor_id' => $doctor->id,
            'provider_type' => 'internal',
            'status' => 'ordered',
            'unit_price' => 65000,
            'ordered_at' => now(),
            'notes' => null,
        ]);

        $this->actingAs($user)
            ->postJson(route('laboratory-orders.delete', $order))
            ->assertOk()
            ->assertJsonPath('data.changed', true);

        $this->actingAs($user)
            ->postJson(route('laboratory-orders.delete', $order))
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertSame(
            1,
            AuditLog::query()->where('module', 'laboratory_management')->where('action', 'cancel_order')->count()
        );
    }

    public function test_non_doctor_actor_cannot_mark_order_reviewed(): void
    {
        $user = User::factory()->create();
        $user->assignRole('nurse');

        $branch = Branch::query()->firstOrFail();
        [$counter, $section, $doctor] = $this->createContext($branch);
        $visit = $this->createReadyVisit($branch, $counter, $section, $doctor, $user);

        $test = LaboratoryTest::query()->create([
            'code' => 'HB',
            'name' => 'Hemoglobin',
            'diagnostic_category' => 'laboratory',
            'sample_type' => 'Blood',
            'default_provider_type' => 'internal',
            'result_entry_mode' => 'structured',
            'is_active' => true,
        ]);

        $order = LaboratoryOrder::query()->create([
            'visit_registration_id' => $visit->id,
            'branch_id' => $visit->branch_id,
            'section_id' => $visit->section_id,
            'laboratory_test_id' => $test->id,
            'ordered_by_doctor_id' => $doctor->id,
            'provider_type' => 'internal',
            'status' => 'ordered',
            'unit_price' => 50000,
            'ordered_at' => now(),
            'notes' => null,
        ]);

        $this->actingAs($user)
            ->postJson(route('laboratory-orders.update', $order), [
                'visit_registration_id' => $visit->id,
                'laboratory_test_id' => $test->id,
                'provider_type' => 'internal',
                'status' => 'reviewed',
                'partner_name' => null,
                'external_reference_no' => null,
                'result_attachment_path' => null,
                'result_summary' => null,
                'result_impression' => null,
                'result_lines' => "HB|Hemoglobin|13.5|g/dL|12-15|normal|Stable",
                'notes' => 'Reviewed by nurse',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.status.0', 'Status reviewed hanya bisa disimpan oleh dokter atau admin klinik.');
    }

    public function test_result_print_is_blocked_until_reviewed(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        [$counter, $section, $doctor] = $this->createContext($branch);
        $visit = $this->createReadyVisit($branch, $counter, $section, $doctor, $user);

        $test = LaboratoryTest::query()->create([
            'code' => 'CRP',
            'name' => 'CRP',
            'diagnostic_category' => 'laboratory',
            'sample_type' => 'Blood',
            'default_provider_type' => 'internal',
            'result_entry_mode' => 'structured',
            'is_active' => true,
        ]);

        $order = LaboratoryOrder::query()->create([
            'visit_registration_id' => $visit->id,
            'branch_id' => $visit->branch_id,
            'section_id' => $visit->section_id,
            'laboratory_test_id' => $test->id,
            'ordered_by_doctor_id' => $doctor->id,
            'provider_type' => 'internal',
            'status' => 'resulted',
            'unit_price' => 95000,
            'ordered_at' => now(),
            'resulted_at' => now(),
            'resulted_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->getJson(route('laboratory-orders.result-print', $order))
            ->assertStatus(409)
            ->assertJsonPath('errors.laboratory_order.0', 'Hasil diagnostics hanya bisa dicetak setelah status reviewed.');
    }

    private function createContext(Branch $branch): array
    {
        $sequence = ++$this->sequence;

        $counter = Counter::query()->create([
            'branch_id' => $branch->id,
            'name' => "Diagnostics Counter {$sequence}",
            'code' => "DGC-{$sequence}",
            'location' => 'Lobby',
            'description' => 'Counter for diagnostics hardening',
            'sort_order' => $sequence * 10,
            'is_active' => true,
        ]);

        $section = Section::query()->create([
            'branch_id' => $branch->id,
            'name' => "Diagnostics Section {$sequence}",
            'code' => "DGSEC{$sequence}",
            'type' => 'regular',
            'queue_prefix' => 'DG',
            'queue_number_padding' => 3,
            'allow_appointment' => true,
            'allow_walk_in' => true,
            'description' => 'Diagnostics section',
            'sort_order' => $sequence * 10,
            'is_active' => true,
        ]);

        $doctor = Doctor::query()->create([
            'full_name' => "Diagnostics Doctor {$sequence}",
            'title_prefix' => 'dr.',
            'title_suffix' => 'Sp.PK',
            'specialization' => 'General',
            'consultation_fee' => 150000,
            'str_number' => sprintf('STR-DG-%03d', $sequence),
            'str_expired_at' => now()->addYears(2)->toDateString(),
            'sip_number' => sprintf('SIP-DG-%03d', $sequence),
            'sip_expired_at' => now()->addYears(2)->toDateString(),
            'phone' => sprintf('081399900%03d', $sequence),
            'email' => sprintf('diagnostics-hardening-%03d@example.com', $sequence),
            'address' => 'Jakarta',
            'is_active' => true,
        ]);

        return [$counter, $section, $doctor];
    }

    private function createReadyVisit(
        Branch $branch,
        Counter $counter,
        Section $section,
        Doctor $doctor,
        User $actor,
        array $overrides = [],
    ): VisitRegistration {
        $sequence = ++$this->sequence;

        $patient = Patient::query()->create([
            'full_name' => $overrides['patient_name'] ?? "Diagnostics Patient {$sequence}",
            'gender' => 'female',
            'date_of_birth' => '1992-10-10',
            'nik' => $overrides['patient_nik'] ?? sprintf('3174000000053%04d', $sequence),
            'phone' => sprintf('081377700%03d', $sequence),
            'province_code' => '31',
            'province_name' => 'DKI Jakarta',
            'city_code' => '3173',
            'city_name' => 'Kota Jakarta Barat',
            'district_code' => '317304',
            'district_name' => 'Palmerah',
            'village_code' => '3173041001',
            'village_name' => 'Slipi',
            'address_line' => 'Jl. Diagnostics Test',
            'allergy_notes' => null,
            'is_active' => true,
        ]);

        $patientRecord = app(PatientRecordService::class)->ensureBranchRecord($patient, $branch);

        $visit = VisitRegistration::query()->create([
            'patient_id' => $patient->id,
            'patient_branch_record_id' => $patientRecord->id,
            'branch_id' => $branch->id,
            'counter_id' => $counter->id,
            'section_id' => $section->id,
            'doctor_id' => $doctor->id,
            'doctor_schedule_id' => null,
            'visit_date' => now()->toDateString(),
            'visit_type' => 'same_day',
            'registration_status' => 'queued',
            'care_stage' => 'in_consultation',
            'vital_status' => 'completed',
            'slot_start_time' => '08:00:00',
            'slot_end_time' => '08:15:00',
            'notes' => 'Diagnostics hardening visit',
            'checked_in_at' => now(),
            'queued_at' => now(),
        ]);

        MedicalRecord::query()->create([
            'visit_registration_id' => $visit->id,
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'section_id' => $section->id,
            'doctor_id' => $doctor->id,
            'subjective' => 'Keluhan diagnostics.',
            'objective' => 'Pemeriksaan stabil.',
            'assessment' => 'Perlu penunjang.',
            'plan' => 'Lanjut diagnostics.',
            'diagnosis_notes' => 'Diagnostics hardening visit.',
            'status' => 'final',
            'finalized_at' => now(),
            'finalized_by_user_id' => $actor->id,
        ]);

        return $visit;
    }
}
