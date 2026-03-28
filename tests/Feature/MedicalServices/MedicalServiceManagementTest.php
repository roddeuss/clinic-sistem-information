<?php

namespace Tests\Feature\MedicalServices;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\MedicalRecord;
use App\Models\MedicalService;
use App\Models\Patient;
use App\Models\PatientBranchRecord;
use App\Models\Section;
use App\Models\User;
use App\Models\VisitMedicalService;
use App\Models\VisitRegistration;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicalServiceManagementTest extends TestCase
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

    public function test_clinic_admin_can_create_medical_service_master_and_bill_completed_service(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $visit = $this->createVisitScenario($user);
        $branch = $visit->branch;

        $this->actingAs($user)
            ->get(route('medical-services'))
            ->assertOk()
            ->assertSee('Medical Services');

        $this->actingAs($user)
            ->from(route('medical-services'))
            ->post(route('medical-services.store'), [
                'code' => 'OBS-ROOM',
                'name' => 'Observation Room',
                'service_type' => 'observation',
                'description' => 'Observation charge',
                'default_fee' => 125000,
                'branch_prices' => [
                    $branch->id => 135000,
                ],
                'is_active' => 1,
            ])
            ->assertRedirect(route('medical-services'));

        $service = MedicalService::query()->where('code', 'OBS-ROOM')->firstOrFail();

        $this->actingAs($user)
            ->from(route('medical-services'))
            ->post(route('medical-service-orders.store'), [
                'visit_registration_id' => $visit->id,
                'medical_service_id' => $service->id,
                'quantity' => 2,
                'status' => 'completed',
                'notes' => 'Observation finished',
            ])
            ->assertRedirect(route('medical-services'));

        $order = VisitMedicalService::query()->where('visit_registration_id', $visit->id)->firstOrFail();
        $invoice = Invoice::query()->where('visit_registration_id', $visit->id)->with('items')->firstOrFail();

        $this->assertSame('completed', $order->status);
        $this->assertSame('270000.00', $order->subtotal);
        $this->assertNotNull($order->completed_at);
        $this->assertTrue(
            $invoice->items->contains(
                fn ($item): bool => $item->item_type === 'service'
                    && $item->description === 'Service - Observation Room'
                    && (float) $item->quantity === 2.0
            )
        );
    }

    private function createVisitScenario(User $user): VisitRegistration
    {
        $branch = Branch::query()->firstOrFail();

        $counter = Counter::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Counter Service',
            'code' => 'CSVC',
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
            'str_number' => 'STR-SVC-001',
            'sip_number' => 'SIP-SVC-001',
            'is_active' => true,
        ]);

        $patient = Patient::query()->create([
            'full_name' => 'Patient Service Billing',
            'gender' => 'female',
            'date_of_birth' => '1994-03-10',
            'nik' => '3174011234567099',
            'phone' => '081234560099',
            'is_active' => true,
        ]);

        $patientBranchRecord = PatientBranchRecord::query()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'medical_record_no' => 'P-92001',
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
            'care_stage' => 'ready_for_checkout',
            'vital_status' => 'completed',
        ]);

        MedicalRecord::query()->create([
            'visit_registration_id' => $visit->id,
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'section_id' => $section->id,
            'doctor_id' => $doctor->id,
            'subjective' => 'Need short observation',
            'objective' => 'Stable',
            'assessment' => 'Observation only',
            'plan' => 'Observation room service',
            'status' => 'final',
            'finalized_at' => now(),
            'finalized_by_user_id' => $user->id,
        ]);

        return $visit->fresh(['branch']);
    }
}
