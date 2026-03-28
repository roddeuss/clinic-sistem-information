<?php

namespace Tests\Feature\DoctorLetters;

use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Counter;
use App\Models\Doctor;
use App\Models\DoctorLetter;
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

class DoctorLetterManagementTest extends TestCase
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

    public function test_clinic_admin_can_create_issue_print_void_and_reissue_doctor_letter(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $scenario = $this->createClinicalScenario();

        $this->actingAs($user)
            ->get(route('doctor-letters'))
            ->assertOk()
            ->assertSee('Doctor Letters');

        $this->actingAs($user)
            ->post(route('doctor-letters.store'), [
                'visit_registration_id' => $scenario['visit']->id,
                'letter_type' => 'sick_note',
                'issue_date' => now()->toDateString(),
                'diagnosis_summary' => 'Acute URI',
                'sick_start_date' => now()->toDateString(),
                'sick_end_date' => now()->addDays(2)->toDateString(),
                'notes' => 'Need rest for three days.',
            ])
            ->assertRedirect();

        $letter = DoctorLetter::query()->firstOrFail();
        $this->assertSame('draft', $letter->status);

        $this->actingAs($user)
            ->post(route('doctor-letters.issue', $letter))
            ->assertRedirect();

        $letter = $letter->fresh();
        $this->assertSame('issued', $letter->status);
        $this->assertNotNull($letter->letter_no);

        $this->actingAs($user)
            ->get(route('doctor-letters.print', $letter))
            ->assertOk()
            ->assertSee('SURAT SAKIT')
            ->assertSee($letter->letter_no);

        $this->actingAs($user)
            ->post(route('doctor-letters.void', $letter), [
                'void_reason' => 'Administrative correction',
            ])
            ->assertRedirect();

        $this->assertSame('voided', $letter->fresh()->status);

        $this->actingAs($user)
            ->post(route('doctor-letters.reissue', $letter))
            ->assertRedirect();

        $this->assertSame(2, DoctorLetter::query()->count());
        $this->assertNotNull(DoctorLetter::query()->where('id', '!=', $letter->id)->value('letter_no'));
    }

    public function test_clinic_admin_can_issue_and_print_drug_free_letter(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $scenario = $this->createClinicalScenario();

        $this->actingAs($user)
            ->post(route('doctor-letters.store'), [
                'visit_registration_id' => $scenario['visit']->id,
                'letter_type' => 'drug_free_note',
                'issue_date' => now()->toDateString(),
                'diagnosis_summary' => 'Administrative clearance',
                'drug_test_date' => now()->toDateString(),
                'drug_test_method' => 'Urine rapid test',
                'drug_test_result' => 'Negatif',
                'drug_free_statement' => 'Pasien dinyatakan bebas narkoba berdasarkan hasil screening.',
            ])
            ->assertRedirect();

        $letter = DoctorLetter::query()->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->post(route('doctor-letters.issue', $letter))
            ->assertRedirect();

        $letter = $letter->fresh();

        $this->assertSame('issued', $letter->status);
        $this->assertSame('drug_free_note', $letter->letter_type);

        $this->actingAs($user)
            ->get(route('doctor-letters.print', $letter))
            ->assertOk()
            ->assertSee('SURAT KETERANGAN BEBAS NARKOBA')
            ->assertSee('Urine rapid test')
            ->assertSee('Negatif');
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
            'name' => 'Letter Counter',
            'code' => 'CTR-LTR',
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
            'full_name' => 'Letter Doctor',
            'title_prefix' => 'dr.',
            'specialization' => 'General Practitioner',
            'consultation_fee' => 150000,
            'sip_number' => 'SIP-LTR-001',
            'is_active' => true,
        ]);

        $patient = Patient::query()->create([
            'full_name' => 'Letter Patient',
            'gender' => 'male',
            'date_of_birth' => '1991-02-10',
            'nik' => '3174011234567892',
            'phone' => '082222222222',
            'is_active' => true,
        ]);

        $branchRecord = PatientBranchRecord::query()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'medical_record_no' => 'P-00002',
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
