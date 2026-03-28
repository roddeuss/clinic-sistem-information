<?php

namespace Tests\Feature\Procedures;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\ProcedureMaster;
use App\Models\Section;
use App\Models\User;
use App\Models\VisitRegistration;
use App\Services\PatientRecordService;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcedureManagementTest extends TestCase
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

    public function test_clinic_admin_can_create_master_and_complete_procedure_order(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        [$counter, $section, $doctor, $visit] = $this->createReadyVisitContext($branch, $user);

        $this->actingAs($user)
            ->post(route('procedure-masters.store'), [
                'code' => 'NEBULIZER',
                'name' => 'Nebulizer',
                'description' => 'Nebulizer therapy',
                'performer_scope' => 'both',
                'requires_doctor_order' => 1,
                'default_fee' => 75000,
                'branch_prices' => [
                    $branch->id => 80000,
                ],
                'is_active' => 1,
            ])
            ->assertRedirect(route('procedures'));

        $master = ProcedureMaster::query()->where('code', 'NEBULIZER')->firstOrFail();

        $this->actingAs($user)
            ->post(route('procedure-orders.store'), [
                'visit_registration_id' => $visit->id,
                'procedure_master_id' => $master->id,
                'quantity' => 2,
                'status' => 'completed',
                'notes' => 'Dilakukan di ruang tindakan.',
            ])
            ->assertRedirect(route('procedures'));

        $visit->refresh();
        $invoice = $visit->invoice()->with('items')->first();

        $this->assertNotNull($invoice);
        $this->assertTrue($invoice->items->contains(fn ($item) => $item->item_type === 'procedure' && (float) $item->quantity === 2.0));

        $this->assertNotNull($counter);
        $this->assertNotNull($section);
        $this->assertNotNull($doctor);
    }

    private function createReadyVisitContext(Branch $branch, User $actor): array
    {
        $counter = Counter::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Procedure Counter',
            'code' => 'PROC-01',
            'location' => 'Lobby',
            'description' => 'Counter for procedure tests',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $section = Section::query()->create([
            'branch_id' => $branch->id,
            'name' => 'General Procedure',
            'code' => 'GENPROC',
            'type' => 'regular',
            'queue_prefix' => 'GP',
            'queue_number_padding' => 3,
            'allow_appointment' => true,
            'allow_walk_in' => true,
            'description' => 'General procedure section',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $doctor = Doctor::query()->create([
            'full_name' => 'Procedure Doctor',
            'title_prefix' => 'dr.',
            'title_suffix' => 'Sp.P',
            'specialization' => 'Pulmonology',
            'consultation_fee' => 175000,
            'str_number' => 'STR-PROC-001',
            'str_expired_at' => now()->addYears(2)->toDateString(),
            'sip_number' => 'SIP-PROC-001',
            'sip_expired_at' => now()->addYears(2)->toDateString(),
            'phone' => '081388001001',
            'email' => 'procedure-doctor@example.com',
            'address' => 'Jakarta',
            'is_active' => true,
        ]);

        $patient = Patient::query()->create([
            'full_name' => 'Procedure Patient',
            'gender' => 'male',
            'date_of_birth' => '1990-05-10',
            'nik' => '3174000000099001',
            'phone' => '081399001001',
            'province_code' => '31',
            'province_name' => 'DKI Jakarta',
            'city_code' => '3173',
            'city_name' => 'Kota Jakarta Barat',
            'district_code' => '317304',
            'district_name' => 'Palmerah',
            'village_code' => '3173041001',
            'village_name' => 'Slipi',
            'address_line' => 'Jl. Procedure',
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
            'notes' => 'Procedure visit',
            'checked_in_at' => now(),
            'queued_at' => now(),
        ]);

        MedicalRecord::query()->create([
            'visit_registration_id' => $visit->id,
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'section_id' => $section->id,
            'doctor_id' => $doctor->id,
            'subjective' => 'Sesak napas ringan.',
            'objective' => 'Ronki halus minimal.',
            'assessment' => 'Bronkospasme ringan.',
            'plan' => 'Nebulizer.',
            'diagnosis_notes' => 'Procedure management test.',
            'status' => 'final',
            'finalized_at' => now(),
            'finalized_by_user_id' => $actor->id,
        ]);

        return [$counter, $section, $doctor, $visit];
    }
}
