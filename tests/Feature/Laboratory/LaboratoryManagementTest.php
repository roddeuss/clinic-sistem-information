<?php

namespace Tests\Feature\Laboratory;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\Doctor;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryTest;
use App\Models\MedicalRecord;
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

class LaboratoryManagementTest extends TestCase
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

    public function test_clinic_admin_can_review_and_print_lab_documents(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $scenario = $this->createLabScenario($user);

        $this->actingAs($user)
            ->post(route('laboratory-orders.update', $scenario['order']), [
                'visit_registration_id' => $scenario['visit']->id,
                'laboratory_test_id' => $scenario['test']->id,
                'provider_type' => 'internal',
                'status' => 'reviewed',
                'partner_name' => null,
                'external_reference_no' => null,
                'result_attachment_path' => null,
                'result_lines' => "HB|Hemoglobin|13.5|g/dL|12-15|normal|Stable\nGLU|Glucose|104|mg/dL|70-140|normal|Post meal",
                'notes' => 'Reviewed test',
            ])
            ->assertRedirect();

        $order = $scenario['order']->fresh();
        $this->assertSame('reviewed', $order->status);
        $this->assertSame($user->id, $order->reviewed_by_user_id);

        $this->actingAs($user)
            ->get(route('laboratory-orders.request-print', $order))
            ->assertOk()
            ->assertSee('Laboratory Request')
            ->assertSee($scenario['patient']->full_name);

        $this->actingAs($user)
            ->get(route('laboratory-orders.result-print', $order))
            ->assertOk()
            ->assertSee('Laboratory Result')
            ->assertSee('Reviewed by')
            ->assertSee('Hemoglobin');
    }

    public function test_clinic_admin_can_review_and_print_radiology_narrative_documents(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $scenario = $this->createLabScenario($user);

        $radiologyTest = LaboratoryTest::query()->create([
            'code' => 'CXR',
            'name' => 'Chest X-Ray',
            'diagnostic_category' => 'radiology',
            'sample_type' => null,
            'default_provider_type' => 'external',
            'result_entry_mode' => 'narrative',
            'is_active' => true,
        ]);

        $radiologyTest->branchPrices()->create([
            'branch_id' => $scenario['branch']->id,
            'internal_price' => null,
            'external_price' => 175000,
            'is_active' => true,
        ]);

        $order = LaboratoryOrder::query()->create([
            'visit_registration_id' => $scenario['visit']->id,
            'branch_id' => $scenario['branch']->id,
            'section_id' => $scenario['section']->id,
            'laboratory_test_id' => $radiologyTest->id,
            'ordered_by_doctor_id' => $scenario['doctor']->id,
            'provider_type' => 'external',
            'partner_name' => 'Radiology Partner',
            'status' => 'ordered',
            'unit_price' => 175000,
            'ordered_at' => now(),
            'notes' => 'Need chest x-ray',
        ]);

        $this->actingAs($user)
            ->post(route('laboratory-orders.update', $order), [
                'visit_registration_id' => $scenario['visit']->id,
                'laboratory_test_id' => $radiologyTest->id,
                'provider_type' => 'external',
                'status' => 'reviewed',
                'partner_name' => 'Radiology Partner',
                'external_reference_no' => 'RAD-001',
                'result_attachment_path' => 'radiology/cxr-demo.pdf',
                'result_summary' => 'No focal infiltrate.',
                'result_impression' => 'No active cardiopulmonary disease.',
                'result_lines' => '',
                'notes' => 'Reviewed radiology result',
            ])
            ->assertRedirect();

        $order->refresh();

        $this->assertSame('reviewed', $order->status);
        $this->assertSame('radiology', $order->laboratoryTest->diagnostic_category);

        $this->actingAs($user)
            ->get(route('laboratory-orders.request-print', $order))
            ->assertOk()
            ->assertSee('Diagnostic Request')
            ->assertSee('Chest X-Ray');

        $this->actingAs($user)
            ->get(route('laboratory-orders.result-print', $order))
            ->assertOk()
            ->assertSee('Diagnostic Result')
            ->assertSee('No active cardiopulmonary disease.')
            ->assertSee('Reviewed by');
    }

    /**
     * @return array{branch: Branch, counter: Counter, section: Section, doctor: Doctor, patient: Patient, patientBranchRecord: PatientBranchRecord, visit: VisitRegistration, test: LaboratoryTest, order: LaboratoryOrder}
     */
    private function createLabScenario(User $user): array
    {
        $branch = Branch::query()->firstOrFail();

        $counter = Counter::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Counter Lab',
            'code' => 'CLB1',
            'location' => 'Front desk',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $section = Section::query()->create([
            'branch_id' => $branch->id,
            'name' => 'General',
            'code' => 'GENERAL',
            'type' => 'regular',
            'queue_prefix' => 'GEN',
            'queue_number_padding' => 3,
            'allow_appointment' => true,
            'allow_walk_in' => true,
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $doctor = Doctor::query()->create([
            'full_name' => 'Rika Sitanggang',
            'title_prefix' => 'dr.',
            'specialization' => 'General Practitioner',
            'consultation_fee' => 150000,
            'str_number' => 'STR-LAB-001',
            'sip_number' => 'SIP-LAB-001',
            'is_active' => true,
        ]);

        $patient = Patient::query()->create([
            'full_name' => 'Patient Lab Print',
            'gender' => 'male',
            'date_of_birth' => '1992-08-10',
            'nik' => '3174011234567002',
            'phone' => '081234560002',
            'is_active' => true,
        ]);

        $patientBranchRecord = PatientBranchRecord::query()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'medical_record_no' => 'P-91001',
        ]);

        $visit = VisitRegistration::query()->create([
            'patient_id' => $patient->id,
            'patient_branch_record_id' => $patientBranchRecord->id,
            'branch_id' => $branch->id,
            'counter_id' => $counter->id,
            'section_id' => $section->id,
            'doctor_id' => $doctor->id,
            'visit_date' => now()->toDateString(),
            'visit_type' => 'same_day',
            'registration_status' => 'registered',
            'care_stage' => 'in_consultation',
            'vital_status' => 'completed',
        ]);

        MedicalRecord::query()->create([
            'visit_registration_id' => $visit->id,
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'section_id' => $section->id,
            'doctor_id' => $doctor->id,
            'subjective' => 'Need lab support',
            'objective' => 'Stable',
            'assessment' => 'Observation',
            'plan' => 'Check lab',
            'status' => 'final',
            'finalized_at' => now(),
            'finalized_by_user_id' => $user->id,
        ]);

        $test = LaboratoryTest::query()->create([
            'code' => 'GLU',
            'name' => 'Blood Glucose',
            'sample_type' => 'Blood',
            'default_provider_type' => 'internal',
            'is_active' => true,
        ]);

        $test->parameters()->createMany([
            ['code' => 'HB', 'name' => 'Hemoglobin', 'unit' => 'g/dL', 'reference_range' => '12-15', 'sort_order' => 10, 'is_active' => true],
            ['code' => 'GLU', 'name' => 'Glucose', 'unit' => 'mg/dL', 'reference_range' => '70-140', 'sort_order' => 20, 'is_active' => true],
        ]);

        $test->branchPrices()->create([
            'branch_id' => $branch->id,
            'internal_price' => 75000,
            'external_price' => 95000,
            'is_active' => true,
        ]);

        $order = LaboratoryOrder::query()->create([
            'visit_registration_id' => $visit->id,
            'branch_id' => $branch->id,
            'section_id' => $section->id,
            'laboratory_test_id' => $test->id,
            'ordered_by_doctor_id' => $doctor->id,
            'provider_type' => 'internal',
            'status' => 'ordered',
            'unit_price' => 75000,
            'ordered_at' => now(),
            'notes' => 'Initial order',
        ]);

        return compact('branch', 'counter', 'section', 'doctor', 'patient', 'patientBranchRecord', 'visit', 'test', 'order');
    }
}
