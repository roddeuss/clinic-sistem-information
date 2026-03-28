<?php

namespace Tests\Feature\Procedures;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\ProcedureMaster;
use App\Models\Section;
use App\Models\User;
use App\Models\VisitProcedure;
use App\Models\VisitRegistration;
use App\Services\PatientRecordService;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcedureManagementHardeningTest extends TestCase
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

    public function test_procedure_index_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('procedures'))
            ->assertForbidden();
    }

    public function test_procedure_index_json_supports_filters_sort_and_pagination(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        [$counter, $section, $doctor] = $this->createContext($branch);

        $this->createReadyVisit($branch, $counter, $section, $doctor, $user, [
            'patient_name' => 'Alpha Procedure',
            'patient_nik' => '3174000000021001',
        ]);
        $this->createReadyVisit($branch, $counter, $section, $doctor, $user, [
            'patient_name' => 'Zeta Procedure',
            'patient_nik' => '3174000000021002',
        ]);

        $response = $this->actingAs($user)->getJson(route('procedures', [
            'branch' => $branch->id,
            'status' => '',
            'master_status' => '',
            'date' => now()->toDateString(),
            'master_sort_by' => 'name',
            'master_sort_direction' => 'asc',
            'master_per_page' => 10,
            'order_sort_by' => 'ordered_at',
            'order_sort_direction' => 'desc',
            'order_per_page' => 10,
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('data.filters.branch', (string) $branch->id)
            ->assertJsonPath('data.filters.master_sort_by', 'name')
            ->assertJsonPath('data.filters.order_per_page', 10)
            ->assertJsonPath('data.masters.data', [])
            ->assertJsonPath('data.orders.data', []);
    }

    public function test_create_master_is_idempotent_and_writes_audit_log(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');
        $branch = Branch::query()->firstOrFail();

        $payload = [
            'code' => 'DRESSING',
            'name' => 'Dressing Luka',
            'description' => 'Perawatan luka standar',
            'performer_scope' => 'both',
            'requires_doctor_order' => true,
            'default_fee' => 50000,
            'branch_prices' => [
                $branch->id => 55000,
            ],
            'is_active' => true,
        ];

        $this->actingAs($user)
            ->postJson(route('procedure-masters.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.changed', true);

        $this->actingAs($user)
            ->postJson(route('procedure-masters.store'), $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $master = ProcedureMaster::query()->where('code', 'DRESSING')->firstOrFail();

        $this->assertSame(1, ProcedureMaster::query()->count());
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'procedure_management',
            'action' => 'create_master',
            'auditable_type' => $master->getMorphClass(),
            'auditable_id' => $master->id,
        ]);
    }

    public function test_update_master_is_idempotent_for_same_payload(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');
        $branch = Branch::query()->firstOrFail();

        $master = ProcedureMaster::query()->create([
            'code' => 'INJECT',
            'name' => 'Injection',
            'description' => 'Initial description',
            'performer_scope' => 'nurse_only',
            'requires_doctor_order' => true,
            'default_fee' => 25000,
            'is_active' => true,
        ]);

        $payload = [
            'code' => 'INJECT',
            'name' => 'Injection Updated',
            'description' => 'Updated description',
            'performer_scope' => 'both',
            'requires_doctor_order' => true,
            'default_fee' => 30000,
            'branch_prices' => [
                $branch->id => 32000,
            ],
            'is_active' => true,
        ];

        $this->actingAs($user)
            ->postJson(route('procedure-masters.update', $master), $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', true);

        $this->actingAs($user)
            ->postJson(route('procedure-masters.update', $master), $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertSame(
            1,
            AuditLog::query()->where('module', 'procedure_management')->where('action', 'update_master')->count()
        );
    }

    public function test_update_order_cannot_move_to_another_visit(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        [$counter, $section, $doctor] = $this->createContext($branch);
        $visit = $this->createReadyVisit($branch, $counter, $section, $doctor, $user, [
            'patient_name' => 'Procedure One',
            'patient_nik' => '3174000000022001',
        ]);
        $otherVisit = $this->createReadyVisit($branch, $counter, $section, $doctor, $user, [
            'patient_name' => 'Procedure Two',
            'patient_nik' => '3174000000022002',
        ]);

        $master = ProcedureMaster::query()->create([
            'code' => 'NEBTEST',
            'name' => 'Nebulizer Test',
            'description' => 'Neb test',
            'performer_scope' => 'both',
            'requires_doctor_order' => true,
            'default_fee' => 60000,
            'is_active' => true,
        ]);

        $order = VisitProcedure::query()->create([
            'visit_registration_id' => $visit->id,
            'branch_id' => $visit->branch_id,
            'section_id' => $visit->section_id,
            'procedure_master_id' => $master->id,
            'ordered_by_doctor_id' => $doctor->id,
            'quantity' => 1,
            'unit_price' => 60000,
            'subtotal' => 60000,
            'status' => 'ordered',
            'ordered_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson(route('procedure-orders.update', $order), [
                'visit_registration_id' => $otherVisit->id,
                'procedure_master_id' => $master->id,
                'quantity' => 1,
                'status' => 'ordered',
                'notes' => null,
            ])
            ->assertStatus(409)
            ->assertJsonPath('errors.visit_registration_id.0', 'Procedure order yang sudah tercatat tidak boleh dipindahkan ke visit lain.');
    }

    public function test_cancel_order_is_idempotent_and_logged(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        [$counter, $section, $doctor] = $this->createContext($branch);
        $visit = $this->createReadyVisit($branch, $counter, $section, $doctor, $user);

        $master = ProcedureMaster::query()->create([
            'code' => 'OBSERVE',
            'name' => 'Observation',
            'description' => 'Observation test',
            'performer_scope' => 'both',
            'requires_doctor_order' => false,
            'default_fee' => 45000,
            'is_active' => true,
        ]);

        $order = VisitProcedure::query()->create([
            'visit_registration_id' => $visit->id,
            'branch_id' => $visit->branch_id,
            'section_id' => $visit->section_id,
            'procedure_master_id' => $master->id,
            'ordered_by_doctor_id' => $doctor->id,
            'quantity' => 1,
            'unit_price' => 45000,
            'subtotal' => 45000,
            'status' => 'ordered',
            'ordered_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson(route('procedure-orders.delete', $order))
            ->assertOk()
            ->assertJsonPath('data.changed', true);

        $this->actingAs($user)
            ->postJson(route('procedure-orders.delete', $order))
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertSame(
            1,
            AuditLog::query()->where('module', 'procedure_management')->where('action', 'cancel_order')->count()
        );
    }

    private function createContext(Branch $branch): array
    {
        $sequence = ++$this->sequence;

        $counter = Counter::query()->create([
            'branch_id' => $branch->id,
            'name' => "Procedure Counter {$sequence}",
            'code' => "PRC-{$sequence}",
            'location' => 'Lobby',
            'description' => 'Counter for procedure hardening',
            'sort_order' => $sequence * 10,
            'is_active' => true,
        ]);

        $section = Section::query()->create([
            'branch_id' => $branch->id,
            'name' => "Procedure Section {$sequence}",
            'code' => "PRSEC{$sequence}",
            'type' => 'regular',
            'queue_prefix' => 'PR',
            'queue_number_padding' => 3,
            'allow_appointment' => true,
            'allow_walk_in' => true,
            'description' => 'Procedure section',
            'sort_order' => $sequence * 10,
            'is_active' => true,
        ]);

        $doctor = Doctor::query()->create([
            'full_name' => "Procedure Doctor {$sequence}",
            'title_prefix' => 'dr.',
            'title_suffix' => 'Sp.B',
            'specialization' => 'General',
            'consultation_fee' => 150000,
            'str_number' => sprintf('STR-PH-%03d', $sequence),
            'str_expired_at' => now()->addYears(2)->toDateString(),
            'sip_number' => sprintf('SIP-PH-%03d', $sequence),
            'sip_expired_at' => now()->addYears(2)->toDateString(),
            'phone' => sprintf('081377700%03d', $sequence),
            'email' => sprintf('procedure-hardening-%03d@example.com', $sequence),
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
            'full_name' => $overrides['patient_name'] ?? "Procedure Patient {$sequence}",
            'gender' => 'female',
            'date_of_birth' => '1992-10-10',
            'nik' => $overrides['patient_nik'] ?? sprintf('3174000000023%04d', $sequence),
            'phone' => sprintf('081355500%03d', $sequence),
            'province_code' => '31',
            'province_name' => 'DKI Jakarta',
            'city_code' => '3173',
            'city_name' => 'Kota Jakarta Barat',
            'district_code' => '317304',
            'district_name' => 'Palmerah',
            'village_code' => '3173041001',
            'village_name' => 'Slipi',
            'address_line' => 'Jl. Procedure Test',
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
            'care_stage' => 'waiting_doctor',
            'vital_status' => 'completed',
            'slot_start_time' => '08:00:00',
            'slot_end_time' => '08:15:00',
            'notes' => 'Procedure hardening visit',
            'checked_in_at' => now(),
            'queued_at' => now(),
        ]);

        MedicalRecord::query()->create([
            'visit_registration_id' => $visit->id,
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'section_id' => $section->id,
            'doctor_id' => $doctor->id,
            'subjective' => 'Keluhan tindakan.',
            'objective' => 'Pemeriksaan stabil.',
            'assessment' => 'Perlu tindakan minor.',
            'plan' => 'Lanjut tindakan.',
            'diagnosis_notes' => 'Procedure hardening visit.',
            'status' => 'final',
            'finalized_at' => now(),
            'finalized_by_user_id' => $actor->id,
        ]);

        return $visit;
    }
}
