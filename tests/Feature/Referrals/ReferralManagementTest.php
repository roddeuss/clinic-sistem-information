<?php

namespace Tests\Feature\Referrals;

use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Counter;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\PatientBranchRecord;
use App\Models\PatientReferral;
use App\Models\ReferralDestination;
use App\Models\Section;
use App\Models\User;
use App\Models\VisitRegistration;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralManagementTest extends TestCase
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

    public function test_clinic_admin_can_create_issue_print_void_and_reissue_referral(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $scenario = $this->createClinicalScenario();

        $this->actingAs($user)
            ->get(route('referrals'))
            ->assertOk()
            ->assertSee('Referrals');

        $this->actingAs($user)
            ->post(route('referral-destinations.store'), [
                'code' => 'RS-TST',
                'destination_type' => 'hospital',
                'name' => 'RS Test',
                'address' => 'Jl. Test Referral',
                'contact_person' => 'Desk',
                'phone' => '021123123',
                'notes' => 'Testing destination',
                'is_active' => 1,
            ])
            ->assertRedirect();

        $destination = ReferralDestination::query()->where('code', 'RS-TST')->firstOrFail();

        $this->actingAs($user)
            ->post(route('referrals.store'), [
                'visit_registration_id' => $scenario['visit']->id,
                'referral_destination_id' => $destination->id,
                'diagnosis_summary' => 'J06.9 - URI',
                'clinical_summary' => 'Patient requires higher level evaluation.',
                'treatment_summary' => 'Symptomatic treatment given.',
                'reason' => 'Need specialist support.',
                'notes' => 'Feature test referral.',
            ])
            ->assertRedirect();

        $referral = PatientReferral::query()->firstOrFail();
        $this->assertSame('draft', $referral->status);

        $this->actingAs($user)
            ->post(route('referrals.issue', $referral))
            ->assertRedirect();

        $referral = $referral->fresh();
        $this->assertSame('issued', $referral->status);
        $this->assertNotNull($referral->referral_no);

        $this->actingAs($user)
            ->get(route('referrals.print', $referral))
            ->assertOk()
            ->assertSee('SURAT RUJUKAN')
            ->assertSee($referral->referral_no);

        $this->actingAs($user)
            ->post(route('referrals.void', $referral), [
                'void_reason' => 'Administrative correction',
            ])
            ->assertRedirect();

        $this->assertSame('voided', $referral->fresh()->status);

        $this->actingAs($user)
            ->post(route('referrals.reissue', $referral))
            ->assertRedirect();

        $this->assertSame(2, PatientReferral::query()->count());
        $this->assertNotNull(PatientReferral::query()->where('id', '!=', $referral->id)->value('referral_no'));
    }

    /**
     * @return array{clinic: Clinic, branch: Branch, counter: Counter, section: Section, doctor: Doctor, patient: Patient, branchRecord: PatientBranchRecord, visit: VisitRegistration}
     */
    private function createClinicalScenario(): array
    {
        $clinic = Clinic::query()->firstOrFail();
        $branch = Branch::query()->firstOrFail();

        $counter = Counter::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Referral Counter',
            'code' => 'CTR-REF',
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
            'full_name' => 'Referral Doctor',
            'title_prefix' => 'dr.',
            'specialization' => 'General Practitioner',
            'consultation_fee' => 150000,
            'sip_number' => 'SIP-REF-001',
            'is_active' => true,
        ]);

        $patient = Patient::query()->create([
            'full_name' => 'Referral Patient',
            'gender' => 'female',
            'date_of_birth' => '1990-05-12',
            'nik' => '3174011234567891',
            'phone' => '081111111111',
            'is_active' => true,
        ]);

        $branchRecord = PatientBranchRecord::query()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'medical_record_no' => 'P-00001',
        ]);

        $visit = VisitRegistration::query()->create([
            'patient_id' => $patient->id,
            'patient_branch_record_id' => $branchRecord->id,
            'branch_id' => $branch->id,
            'counter_id' => $counter->id,
            'section_id' => $section->id,
            'visit_date' => now()->toDateString(),
            'visit_type' => 'same_day',
            'registration_status' => 'registered',
            'care_stage' => 'ready_for_checkout',
            'vital_status' => 'completed',
            'doctor_id' => $doctor->id,
        ]);

        MedicalRecord::query()->create([
            'visit_registration_id' => $visit->id,
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'section_id' => $section->id,
            'doctor_id' => $doctor->id,
            'subjective' => 'Subjective',
            'objective' => 'Objective',
            'assessment' => 'Assessment',
            'plan' => 'Plan',
            'status' => 'final',
            'finalized_at' => now()->subHour(),
            'finalized_by_user_id' => User::query()->whereHas('roles', fn ($query) => $query->where('name', 'clinic-admin'))->value('id'),
        ]);

        return compact('clinic', 'branch', 'counter', 'section', 'doctor', 'patient', 'branchRecord', 'visit');
    }
}
