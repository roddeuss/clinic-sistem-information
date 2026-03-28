<?php

namespace Tests\Feature\Safety;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\Doctor;
use App\Models\DrugInteractionRule;
use App\Models\MedicalRecord;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineReorderPolicy;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionInteractionOverride;
use App\Models\PrescriptionItem;
use App\Models\Section;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VisitRegistration;
use App\Services\PatientRecordService;
use Carbon\Carbon;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicationSafetyManagementTest extends TestCase
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

    public function test_clinic_admin_can_create_reorder_point_policy_with_supplier_priority(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        $supplierOne = Supplier::query()->create([
            'code' => 'SUP-ROP-1',
            'name' => 'Supplier ROP Satu',
            'is_active' => true,
        ]);
        $supplierTwo = Supplier::query()->create([
            'code' => 'SUP-ROP-2',
            'name' => 'Supplier ROP Dua',
            'is_active' => true,
        ]);

        $medicine = Medicine::query()->create([
            'code' => 'ROP001',
            'name' => 'Reorder Tablet',
            'generic_name' => 'Reorder Tablet',
            'active_ingredients' => 'Reorder Tablet',
            'dosage_form' => 'tablet',
            'therapeutic_class' => 'demo class',
            'strength' => '500 mg',
            'base_unit' => 'tablet',
            'contraindication_notes' => null,
            'is_compoundable' => false,
            'is_active' => true,
        ]);

        $stripUnit = $medicine->units()->updateOrCreate(
            ['label' => 'STRIP'],
            [
                'conversion_factor' => 10,
                'is_base' => false,
                'allow_purchase' => true,
                'allow_dispense' => true,
                'sort_order' => 20,
                'is_active' => true,
            ],
        );

        MedicineBatch::query()->create([
            'branch_id' => $branch->id,
            'medicine_id' => $medicine->id,
            'supplier_id' => $supplierOne->id,
            'batch_number' => 'ROP001-A1',
            'received_at' => now()->subDays(5)->toDateString(),
            'expired_at' => now()->addMonths(12)->toDateString(),
            'quantity_received' => 25,
            'quantity_available' => 25,
            'purchase_cost' => 500,
            'supplier_name' => $supplierOne->name,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->from(route('reorder-points'))
            ->post(route('reorder-points.store'), [
                'medicine_id' => $medicine->id,
                'branch_id' => $branch->id,
                'preferred_purchase_unit_id' => $stripUnit->id,
                'minimum_stock' => 10,
                'safety_stock' => 5,
                'reorder_point' => 30,
                'reorder_quantity' => 40,
                'lead_time_days' => 3,
                'notes' => 'Need faster replenishment.',
                'is_active' => 1,
                'supplier_preferences' => [
                    ['supplier_id' => $supplierOne->id, 'priority' => 10, 'is_primary' => 1],
                    ['supplier_id' => $supplierTwo->id, 'priority' => 20, 'is_primary' => 0],
                ],
            ])
            ->assertRedirect(route('reorder-points'));

        $policy = MedicineReorderPolicy::query()
            ->with('supplierPreferences')
            ->firstOrFail();

        $this->assertSame($medicine->id, $policy->medicine_id);
        $this->assertSame($branch->id, $policy->branch_id);
        $this->assertSame($stripUnit->id, $policy->preferred_purchase_unit_id);
        $this->assertCount(2, $policy->supplierPreferences);
        $this->assertTrue($policy->supplierPreferences->firstWhere('supplier_id', $supplierOne->id)?->is_primary);
        $this->assertSame(1, $user->fresh()->unreadNotifications()->count());

        $this->actingAs($user)
            ->get(route('reorder-points'))
            ->assertOk()
            ->assertSee('Reorder Tablet')
            ->assertSee('Low stock recommendations');
    }

    public function test_clinic_admin_can_manage_drug_interaction_rules(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $this->actingAs($user)
            ->from(route('drug-interactions'))
            ->post(route('drug-interactions.store'), [
                'code' => 'dir-test-01',
                'left_operand_type' => 'ingredient',
                'left_operand_value' => 'omeprazole',
                'right_operand_type' => 'ingredient',
                'right_operand_value' => 'clopidogrel',
                'severity' => 'major',
                'title' => 'Omeprazole with clopidogrel',
                'clinical_effect' => 'May reduce active metabolite.',
                'management_advice' => 'Consider alternative gastric protection.',
                'is_active' => 1,
            ])
            ->assertRedirect(route('drug-interactions'));

        $rule = DrugInteractionRule::query()->firstOrFail();

        $this->actingAs($user)
            ->from(route('drug-interactions'))
            ->post(route('drug-interactions.update', $rule), [
                'code' => 'DIR-TEST-01',
                'left_operand_type' => 'ingredient',
                'left_operand_value' => 'omeprazole',
                'right_operand_type' => 'ingredient',
                'right_operand_value' => 'clopidogrel',
                'severity' => 'moderate',
                'title' => 'Updated rule',
                'clinical_effect' => 'Updated effect.',
                'management_advice' => 'Updated advice.',
                'is_active' => 1,
            ])
            ->assertRedirect(route('drug-interactions'));

        $rule->refresh();

        $this->assertSame('moderate', $rule->severity);
        $this->assertSame('Updated rule', $rule->title);

        $this->actingAs($user)
            ->post(route('drug-interactions.delete', $rule))
            ->assertRedirect(route('drug-interactions'));

        $this->assertFalse($rule->fresh()->is_active);
    }

    public function test_major_drug_interaction_requires_override_before_finalize(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        $counter = Counter::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Counter Safety',
            'code' => 'CSF1',
            'location' => 'Lobby',
            'description' => 'Counter for safety test',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $section = Section::query()->create([
            'branch_id' => $branch->id,
            'name' => 'General Safety',
            'code' => 'GENSAFE',
            'type' => 'regular',
            'queue_prefix' => 'GS',
            'queue_number_padding' => 3,
            'allow_appointment' => true,
            'allow_walk_in' => true,
            'description' => 'General safety section',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $doctor = Doctor::query()->create([
            'full_name' => 'Safety Doctor',
            'title_prefix' => 'dr.',
            'specialization' => 'General Practitioner',
            'consultation_fee' => 150000,
            'str_number' => 'STR-SAFETY-1',
            'str_expired_at' => '2029-12-31',
            'sip_number' => 'SIP-SAFETY-1',
            'sip_expired_at' => '2028-12-31',
            'phone' => '081234560001',
            'email' => 'doctor.safety@example.com',
            'address' => 'Jakarta',
            'is_active' => true,
        ]);

        $patient = Patient::query()->create([
            'full_name' => 'Patient Interaction',
            'gender' => 'female',
            'date_of_birth' => '1994-04-12',
            'nik' => '3174011204940001',
            'phone' => '081234560002',
            'province_code' => '31',
            'province_name' => 'DKI Jakarta',
            'city_code' => '3173',
            'city_name' => 'Kota Jakarta Barat',
            'district_code' => '317304',
            'district_name' => 'Palmerah',
            'village_code' => '3173041001',
            'village_name' => 'Slipi',
            'address_line' => 'Jl. Safety No. 1',
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
            'visit_date' => Carbon::today()->toDateString(),
            'visit_type' => 'same_day',
            'registration_status' => 'queued',
            'care_stage' => 'waiting_doctor',
            'vital_status' => 'completed',
            'slot_start_time' => '08:00:00',
            'slot_end_time' => '08:15:00',
            'checked_in_at' => now(),
            'queued_at' => now(),
        ]);

        MedicalRecord::query()->create([
            'visit_registration_id' => $visit->id,
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'section_id' => $section->id,
            'doctor_id' => $doctor->id,
            'subjective' => 'Keluhan nyeri dada ringan dan dispepsia.',
            'objective' => 'Keadaan umum baik.',
            'assessment' => 'Need antiplatelet and gastric protection review.',
            'plan' => 'Prescription safety review.',
            'status' => 'final',
            'finalized_at' => now(),
            'finalized_by_user_id' => $user->id,
        ]);

        $omeprazole = Medicine::query()->create([
            'code' => 'SAFE-OMZ',
            'name' => 'Omeprazole',
            'generic_name' => 'Omeprazole',
            'active_ingredients' => 'Omeprazole',
            'dosage_form' => 'capsule',
            'therapeutic_class' => 'proton pump inhibitor',
            'strength' => '20 mg',
            'base_unit' => 'capsule',
            'is_compoundable' => false,
            'is_active' => true,
        ]);
        $clopidogrel = Medicine::query()->create([
            'code' => 'SAFE-CLOP',
            'name' => 'Clopidogrel',
            'generic_name' => 'Clopidogrel',
            'active_ingredients' => 'Clopidogrel',
            'dosage_form' => 'tablet',
            'therapeutic_class' => 'antiplatelet',
            'strength' => '75 mg',
            'base_unit' => 'tablet',
            'is_compoundable' => false,
            'is_active' => true,
        ]);

        DrugInteractionRule::query()->create([
            'code' => 'DIR-SAFETY-1',
            'left_operand_type' => 'ingredient',
            'left_operand_value' => 'omeprazole',
            'right_operand_type' => 'ingredient',
            'right_operand_value' => 'clopidogrel',
            'severity' => 'major',
            'title' => 'Omeprazole with clopidogrel',
            'clinical_effect' => 'Potentially reduces clopidogrel activation.',
            'management_advice' => 'Use alternative gastric protection or document override.',
            'is_active' => true,
        ]);

        $prescription = Prescription::query()->create([
            'visit_registration_id' => $visit->id,
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'section_id' => $section->id,
            'doctor_id' => $doctor->id,
            'status' => 'draft',
            'notes' => 'Interaction validation',
        ]);

        $omeprazoleItem = PrescriptionItem::query()->create([
            'prescription_id' => $prescription->id,
            'medicine_id' => $omeprazole->id,
            'item_type' => 'in_house',
            'display_name' => 'Omeprazole 20 mg',
            'route' => 'oral',
            'dose_amount' => 1,
            'dose_unit' => 'capsule',
            'frequency' => '1x sehari',
            'duration_days' => 7,
            'instruction' => 'Before breakfast.',
            'quantity_prescribed' => 7,
            'dispense_unit' => 'capsule',
            'status' => 'pending',
            'sort_order' => 10,
        ]);

        PrescriptionItem::query()->create([
            'prescription_id' => $prescription->id,
            'medicine_id' => $clopidogrel->id,
            'item_type' => 'in_house',
            'display_name' => 'Clopidogrel 75 mg',
            'route' => 'oral',
            'dose_amount' => 1,
            'dose_unit' => 'tablet',
            'frequency' => '1x sehari',
            'duration_days' => 7,
            'instruction' => 'After breakfast.',
            'quantity_prescribed' => 7,
            'dispense_unit' => 'tablet',
            'status' => 'pending',
            'sort_order' => 20,
        ]);

        $this->actingAs($user)
            ->from(route('prescriptions'))
            ->post(route('prescriptions.finalize', $prescription), [
                'notes' => 'Attempt finalize before override.',
            ])
            ->assertRedirect(route('prescriptions'))
            ->assertSessionHasErrors('prescription');

        $this->actingAs($user)
            ->from(route('prescriptions'))
            ->post(route('prescription-items.override-interactions', $omeprazoleItem), [
                'override_reason' => 'Benefit considered greater than interaction risk for this short course.',
            ])
            ->assertRedirect(route('prescriptions'));

        $this->assertDatabaseHas('prescription_interaction_overrides', [
            'prescription_id' => $prescription->id,
            'prescription_item_id' => $omeprazoleItem->id,
        ]);

        $this->actingAs($user)
            ->from(route('prescriptions'))
            ->post(route('prescriptions.finalize', $prescription), [
                'notes' => 'Finalize after override.',
            ])
            ->assertRedirect(route('prescriptions'));

        $prescription->refresh();

        $this->assertSame('finalized', $prescription->status);
        $this->assertNotNull($prescription->finalized_at);
        $this->assertGreaterThan(0, PrescriptionInteractionOverride::query()->count());
    }
}
