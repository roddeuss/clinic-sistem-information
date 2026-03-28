<?php

namespace Tests\Feature\Fulfillment;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Doctor;
use App\Models\DrugInteractionRule;
use App\Models\MedicalRecord;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Section;
use App\Models\User;
use App\Models\VisitRegistration;
use App\Services\PatientRecordService;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrescriptionManagementHardeningTest extends TestCase
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

    public function test_prescription_index_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('prescriptions'))
            ->assertForbidden();
    }

    public function test_prescription_index_json_supports_filters_sort_and_pagination(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        $counter = $this->createCounter($branch);
        $section = $this->createSection($branch);
        $doctor = $this->createDoctor();

        $firstVisit = $this->createReadyVisit($branch, $counter, $section, $doctor, $user, [
            'patient_name' => 'Alpha Patient',
            'patient_nik' => '3174000000011001',
            'patient_phone' => '081301100001',
        ]);

        $secondVisit = $this->createReadyVisit($branch, $counter, $section, $doctor, $user, [
            'patient_name' => 'Zeta Patient',
            'patient_nik' => '3174000000011002',
            'patient_phone' => '081301100002',
        ]);

        $response = $this->actingAs($user)->getJson(route('prescriptions', [
            'branch' => $branch->id,
            'status' => 'none',
            'date' => now()->toDateString(),
            'visit_sort_by' => 'created_at',
            'visit_sort_direction' => 'desc',
            'visit_per_page' => 10,
            'item_sort_by' => 'finalized_at',
            'item_sort_direction' => 'desc',
            'item_per_page' => 10,
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('data.filters.branch', (string) $branch->id)
            ->assertJsonPath('data.filters.status', 'none')
            ->assertJsonPath('data.filters.visit_sort_by', 'created_at')
            ->assertJsonPath('data.filters.visit_per_page', 10)
            ->assertJsonCount(2, 'data.visits.data')
            ->assertJsonPath('data.visits.data.0.id', $secondVisit->id)
            ->assertJsonPath('data.visits.data.1.id', $firstVisit->id);
    }

    public function test_create_prescription_is_idempotent_and_writes_audit_log(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        $counter = $this->createCounter($branch);
        $section = $this->createSection($branch);
        $doctor = $this->createDoctor();
        $visit = $this->createReadyVisit($branch, $counter, $section, $doctor, $user);

        $payload = [
            'visit_registration_id' => $visit->id,
            'notes' => 'Prescription header draft.',
        ];

        $this->actingAs($user)
            ->postJson(route('prescriptions.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.changed', true);

        $this->actingAs($user)
            ->postJson(route('prescriptions.store'), $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $prescription = Prescription::query()->where('visit_registration_id', $visit->id)->firstOrFail();

        $this->assertSame(1, Prescription::query()->count());
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'prescription_management',
            'action' => 'create',
            'auditable_type' => $prescription->getMorphClass(),
            'auditable_id' => $prescription->id,
        ]);
        $this->assertSame(
            1,
            AuditLog::query()->where('module', 'prescription_management')->where('action', 'create')->count()
        );
    }

    public function test_update_prescription_item_is_idempotent(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        $counter = $this->createCounter($branch);
        $section = $this->createSection($branch);
        $doctor = $this->createDoctor();
        $visit = $this->createReadyVisit($branch, $counter, $section, $doctor, $user);
        $medicine = $this->createMedicine('PARA500', 'Paracetamol', 'analgesic');

        $prescription = $this->createPrescription($user, $visit);
        $item = $this->createPrescriptionItem($user, $prescription, $medicine);

        $payload = [
            'prescription_id' => $prescription->id,
            'item_type' => 'in_house',
            'medicine_id' => $medicine->id,
            'route' => 'oral',
            'dose_amount' => 1,
            'dose_unit' => 'tablet',
            'frequency' => '3x sehari',
            'duration_days' => 5,
            'instruction' => 'Sesudah makan',
            'quantity_prescribed' => 15,
            'dispense_unit' => 'tablet',
            'weight_snapshot_kg' => 60,
            'status' => 'pending',
            'notes' => 'Updated note.',
        ];

        $this->actingAs($user)
            ->postJson(route('prescription-items.update', $item), $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', true);

        $this->actingAs($user)
            ->postJson(route('prescription-items.update', $item), $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertSame(
            1,
            AuditLog::query()->where('module', 'prescription_management')->where('action', 'update_item')->count()
        );
    }

    public function test_finalize_prescription_is_idempotent_for_same_notes(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        $counter = $this->createCounter($branch);
        $section = $this->createSection($branch);
        $doctor = $this->createDoctor();
        $visit = $this->createReadyVisit($branch, $counter, $section, $doctor, $user);
        $medicine = $this->createMedicine('CTM004', 'Chlorpheniramine', 'antihistamine');

        $prescription = $this->createPrescription($user, $visit);
        $this->createPrescriptionItem($user, $prescription, $medicine);

        $payload = ['notes' => 'Finalized after review.'];

        $this->actingAs($user)
            ->postJson(route('prescriptions.finalize', $prescription), $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.prescription.status', 'finalized');

        $this->actingAs($user)
            ->postJson(route('prescriptions.finalize', $prescription), $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertSame(
            1,
            AuditLog::query()->where('module', 'prescription_management')->where('action', 'finalize')->count()
        );
    }

    public function test_major_interaction_override_is_idempotent_with_same_reason(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        $counter = $this->createCounter($branch);
        $section = $this->createSection($branch);
        $doctor = $this->createDoctor();
        $visit = $this->createReadyVisit($branch, $counter, $section, $doctor, $user);

        $firstMedicine = $this->createMedicine('MEDINT1', 'Medicine One', 'class-a', 'amoxicillin');
        $secondMedicine = $this->createMedicine('MEDINT2', 'Medicine Two', 'class-b', 'warfarin');

        DrugInteractionRule::query()->create([
            'code' => 'INT-MAJOR-001',
            'left_operand_type' => 'ingredient',
            'left_operand_value' => 'amoxicillin',
            'right_operand_type' => 'ingredient',
            'right_operand_value' => 'warfarin',
            'severity' => 'major',
            'title' => 'Major interaction: Amoxicillin and Warfarin',
            'clinical_effect' => 'Increased bleeding risk.',
            'management_advice' => 'Monitor INR and document override reason.',
            'is_active' => true,
        ]);

        $prescription = $this->createPrescription($user, $visit);
        $firstItem = $this->createPrescriptionItem($user, $prescription, $firstMedicine, [
            'notes' => 'First medicine.',
        ]);
        $this->createPrescriptionItem($user, $prescription, $secondMedicine, [
            'notes' => 'Second medicine.',
        ]);

        $payload = ['override_reason' => 'Manfaat klinis lebih besar, akan dimonitor ketat.'];

        $this->actingAs($user)
            ->postJson(route('prescription-items.override-interactions', $firstItem), $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', true);

        $this->actingAs($user)
            ->postJson(route('prescription-items.override-interactions', $firstItem), $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertSame(
            1,
            AuditLog::query()->where('module', 'prescription_management')->where('action', 'override_interaction')->count()
        );
    }

    private function createCounter(Branch $branch): Counter
    {
        $sequence = ++$this->sequence;

        return Counter::query()->create([
            'branch_id' => $branch->id,
            'name' => "Counter {$sequence}",
            'code' => "CTR{$sequence}",
            'location' => 'Lobby',
            'description' => 'Counter for prescription hardening tests',
            'sort_order' => $sequence * 10,
            'is_active' => true,
        ]);
    }

    private function createSection(Branch $branch): Section
    {
        $sequence = $this->sequence;

        return Section::query()->create([
            'branch_id' => $branch->id,
            'name' => "General {$sequence}",
            'code' => "GEN{$sequence}",
            'type' => 'regular',
            'queue_prefix' => 'GEN',
            'queue_number_padding' => 3,
            'allow_appointment' => true,
            'allow_walk_in' => true,
            'description' => 'General service',
            'sort_order' => $sequence * 10,
            'is_active' => true,
        ]);
    }

    private function createDoctor(): Doctor
    {
        $sequence = ++$this->sequence;

        return Doctor::query()->create([
            'full_name' => "Doctor {$sequence}",
            'title_prefix' => 'dr.',
            'title_suffix' => 'Sp.A',
            'specialization' => 'General',
            'consultation_fee' => 150000,
            'str_number' => sprintf('STR-PRX-%03d', $sequence),
            'str_expired_at' => now()->addYears(2)->toDateString(),
            'sip_number' => sprintf('SIP-PRX-%03d', $sequence),
            'sip_expired_at' => now()->addYears(2)->toDateString(),
            'phone' => sprintf('081390000%03d', $sequence),
            'email' => sprintf('doctor-prx-%03d@example.com', $sequence),
            'address' => 'Jakarta',
            'is_active' => true,
        ]);
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
            'full_name' => $overrides['patient_name'] ?? "Patient {$sequence}",
            'gender' => 'male',
            'date_of_birth' => '1995-01-10',
            'nik' => $overrides['patient_nik'] ?? sprintf('3174000000012%04d', $sequence),
            'phone' => $overrides['patient_phone'] ?? sprintf('081302200%03d', $sequence),
            'province_code' => '31',
            'province_name' => 'DKI Jakarta',
            'city_code' => '3173',
            'city_name' => 'Kota Jakarta Barat',
            'district_code' => '317304',
            'district_name' => 'Palmerah',
            'village_code' => '3173041001',
            'village_name' => 'Slipi',
            'address_line' => 'Jl. Test',
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
            'notes' => 'Prescription hardening test visit',
            'checked_in_at' => now(),
            'queued_at' => now(),
        ]);

        MedicalRecord::query()->create([
            'visit_registration_id' => $visit->id,
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'section_id' => $section->id,
            'doctor_id' => $doctor->id,
            'subjective' => 'Keluhan ringan.',
            'objective' => 'Kondisi stabil.',
            'assessment' => 'Observasi.',
            'plan' => 'Terapi simptomatik.',
            'diagnosis_notes' => 'Testing prescription hardening.',
            'status' => 'final',
            'finalized_at' => now(),
            'finalized_by_user_id' => $actor->id,
        ]);

        return $visit;
    }

    private function createMedicine(
        string $code,
        string $name,
        string $therapeuticClass,
        ?string $activeIngredient = null,
    ): Medicine {
        return Medicine::query()->create([
            'code' => $code,
            'name' => $name,
            'generic_name' => $name,
            'active_ingredients' => $activeIngredient ?? $name,
            'allergy_keywords' => $activeIngredient ?? $name,
            'dosage_form' => 'tablet',
            'therapeutic_class' => $therapeuticClass,
            'strength' => '500 mg',
            'base_unit' => 'tablet',
            'description' => 'Medicine for prescription hardening tests',
            'contraindication_notes' => null,
            'is_compoundable' => true,
            'is_active' => true,
        ]);
    }

    private function createPrescription(User $user, VisitRegistration $visit): Prescription
    {
        $this->actingAs($user)
            ->postJson(route('prescriptions.store'), [
                'visit_registration_id' => $visit->id,
                'notes' => 'Prescription header.',
            ])
            ->assertCreated();

        return Prescription::query()->where('visit_registration_id', $visit->id)->firstOrFail();
    }

    private function createPrescriptionItem(
        User $user,
        Prescription $prescription,
        Medicine $medicine,
        array $overrides = [],
    ): PrescriptionItem {
        $this->actingAs($user)
            ->postJson(route('prescription-items.store'), [
                'prescription_id' => $prescription->id,
                'item_type' => 'in_house',
                'medicine_id' => $medicine->id,
                'route' => 'oral',
                'dose_amount' => 1,
                'dose_unit' => 'tablet',
                'frequency' => '3x sehari',
                'duration_days' => 3,
                'instruction' => 'Sesudah makan',
                'quantity_prescribed' => 9,
                'dispense_unit' => 'tablet',
                'weight_snapshot_kg' => 60,
                'status' => 'pending',
                'notes' => 'Prescription item',
                ...$overrides,
            ])
            ->assertCreated();

        return PrescriptionItem::query()
            ->where('prescription_id', $prescription->id)
            ->latest('id')
            ->firstOrFail();
    }
}
