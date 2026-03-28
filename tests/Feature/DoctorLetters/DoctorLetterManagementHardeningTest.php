<?php

namespace Tests\Feature\DoctorLetters;

use App\Models\AuditLog;
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

class DoctorLetterManagementHardeningTest extends TestCase
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

    public function test_doctor_letter_index_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('doctor-letters'))
            ->assertForbidden();
    }

    public function test_doctor_letter_index_json_supports_filters_sort_and_pagination(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $scenario = $this->createClinicalScenario([
            'patient_name' => 'Alpha Letter Patient',
            'patient_nik' => '3174011234567901',
        ]);

        DoctorLetter::query()->create([
            'visit_registration_id' => $scenario['visit']->id,
            'patient_id' => $scenario['patient']->id,
            'patient_branch_record_id' => $scenario['branchRecord']->id,
            'branch_id' => $scenario['branch']->id,
            'section_id' => $scenario['section']->id,
            'doctor_id' => $scenario['doctor']->id,
            'letter_type' => 'sick_note',
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'diagnosis_summary' => 'Alpha diagnosis',
            'notes' => 'Alpha note',
            'sick_start_date' => now()->toDateString(),
            'sick_end_date' => now()->addDay()->toDateString(),
            'sick_total_days' => 2,
        ]);

        $this->actingAs($user)
            ->getJson(route('doctor-letters', [
                'search' => 'Alpha',
                'status' => 'draft',
                'letter_type' => 'sick_note',
                'sort_by' => 'created_at',
                'sort_direction' => 'desc',
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertJsonPath('data.filters.search', 'Alpha')
            ->assertJsonPath('data.filters.status', 'draft')
            ->assertJsonPath('data.filters.letter_type', 'sick_note')
            ->assertJsonPath('data.letters.data.0.patient_name', 'Alpha Letter Patient');
    }

    public function test_create_letter_is_idempotent_for_same_draft_payload(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $scenario = $this->createClinicalScenario([
            'patient_name' => 'Create Letter Patient',
            'patient_nik' => '3174011234567902',
        ]);

        $payload = [
            'visit_registration_id' => $scenario['visit']->id,
            'letter_type' => 'control_note',
            'issue_date' => now()->toDateString(),
            'diagnosis_summary' => 'Control diagnosis',
            'notes' => 'Control note',
            'control_date' => now()->addDays(7)->toDateString(),
            'control_notes' => 'Control in one week',
        ];

        $this->actingAs($user)
            ->postJson(route('doctor-letters.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.changed', true);

        $this->actingAs($user)
            ->postJson(route('doctor-letters.store'), $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertSame(1, DoctorLetter::query()->count());
        $this->assertSame(
            1,
            AuditLog::query()->where('module', 'doctor_letter_management')->where('action', 'create_draft')->count()
        );
    }

    public function test_update_letter_is_idempotent_for_same_payload(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $scenario = $this->createClinicalScenario([
            'patient_name' => 'Update Letter Patient',
            'patient_nik' => '3174011234567903',
        ]);

        $letter = DoctorLetter::query()->create([
            'visit_registration_id' => $scenario['visit']->id,
            'patient_id' => $scenario['patient']->id,
            'patient_branch_record_id' => $scenario['branchRecord']->id,
            'branch_id' => $scenario['branch']->id,
            'section_id' => $scenario['section']->id,
            'doctor_id' => $scenario['doctor']->id,
            'letter_type' => 'fit_note',
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'diagnosis_summary' => 'Initial diagnosis',
            'notes' => null,
            'healthy_statement' => 'Initial statement',
        ]);

        $payload = [
            'visit_registration_id' => $scenario['visit']->id,
            'letter_type' => 'fit_note',
            'issue_date' => now()->toDateString(),
            'diagnosis_summary' => 'Updated diagnosis',
            'notes' => 'Updated notes',
            'healthy_statement' => 'Updated statement',
        ];

        $this->actingAs($user)
            ->postJson(route('doctor-letters.update', $letter), $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', true);

        $this->actingAs($user)
            ->postJson(route('doctor-letters.update', $letter), $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertSame(
            1,
            AuditLog::query()->where('module', 'doctor_letter_management')->where('action', 'update_draft')->count()
        );
    }

    public function test_issue_letter_is_idempotent_once_issued(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $scenario = $this->createClinicalScenario([
            'patient_name' => 'Issue Letter Patient',
            'patient_nik' => '3174011234567904',
        ]);

        $letter = DoctorLetter::query()->create([
            'visit_registration_id' => $scenario['visit']->id,
            'patient_id' => $scenario['patient']->id,
            'patient_branch_record_id' => $scenario['branchRecord']->id,
            'branch_id' => $scenario['branch']->id,
            'section_id' => $scenario['section']->id,
            'doctor_id' => $scenario['doctor']->id,
            'letter_type' => 'sick_note',
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'diagnosis_summary' => 'Diagnosis',
            'notes' => null,
            'sick_start_date' => now()->toDateString(),
            'sick_end_date' => now()->addDays(2)->toDateString(),
            'sick_total_days' => 3,
        ]);

        $this->actingAs($user)
            ->postJson(route('doctor-letters.issue', $letter))
            ->assertOk()
            ->assertJsonPath('data.changed', true);

        $this->actingAs($user)
            ->postJson(route('doctor-letters.issue', $letter))
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertSame(
            1,
            AuditLog::query()->where('module', 'doctor_letter_management')->where('action', 'issue_letter')->count()
        );
    }

    public function test_void_letter_is_idempotent_with_same_reason(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $scenario = $this->createClinicalScenario([
            'patient_name' => 'Void Letter Patient',
            'patient_nik' => '3174011234567905',
        ]);

        $letter = DoctorLetter::query()->create([
            'visit_registration_id' => $scenario['visit']->id,
            'patient_id' => $scenario['patient']->id,
            'patient_branch_record_id' => $scenario['branchRecord']->id,
            'branch_id' => $scenario['branch']->id,
            'section_id' => $scenario['section']->id,
            'doctor_id' => $scenario['doctor']->id,
            'letter_no' => 'LTR-TEST-001',
            'letter_type' => 'fit_note',
            'status' => 'issued',
            'issue_date' => now()->toDateString(),
            'diagnosis_summary' => 'Diagnosis',
            'notes' => null,
            'healthy_statement' => 'Healthy',
            'issued_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)
            ->postJson(route('doctor-letters.void', $letter), [
                'void_reason' => 'Administrative correction',
            ])
            ->assertOk()
            ->assertJsonPath('data.changed', true);

        $this->actingAs($user)
            ->postJson(route('doctor-letters.void', $letter), [
                'void_reason' => 'Administrative correction',
            ])
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertSame(
            1,
            AuditLog::query()->where('module', 'doctor_letter_management')->where('action', 'void_letter')->count()
        );
    }

    /**
     * @param array{patient_name?: string, patient_nik?: string} $overrides
     * @return array{clinic: Clinic, branch: Branch, counter: Counter, section: Section, doctor: Doctor, patient: Patient, branchRecord: PatientBranchRecord, visit: VisitRegistration}
     */
    private function createClinicalScenario(array $overrides = []): array
    {
        $sequence = ++$this->sequence;
        $clinic = Clinic::query()->firstOrFail();
        $branch = Branch::query()->firstOrFail();

        $counter = Counter::query()->create([
            'branch_id' => $branch->id,
            'name' => "Letter Counter {$sequence}",
            'code' => "CTR-LTR-{$sequence}",
            'location' => 'Front desk',
            'sort_order' => 10 + $sequence,
            'is_active' => true,
        ]);

        $section = Section::query()->create([
            'branch_id' => $branch->id,
            'name' => "General {$sequence}",
            'code' => "GENERAL-LTR{$sequence}",
            'type' => 'regular',
            'queue_prefix' => 'GEN',
            'queue_number_padding' => 3,
            'allow_appointment' => true,
            'allow_walk_in' => true,
            'sort_order' => 10 + $sequence,
            'is_active' => true,
        ]);

        $doctor = Doctor::query()->create([
            'full_name' => "Letter Doctor {$sequence}",
            'title_prefix' => 'dr.',
            'specialization' => 'General Practitioner',
            'consultation_fee' => 150000,
            'sip_number' => "SIP-LTR-{$sequence}",
            'is_active' => true,
        ]);

        $patient = Patient::query()->create([
            'full_name' => $overrides['patient_name'] ?? "Letter Patient {$sequence}",
            'gender' => 'male',
            'date_of_birth' => '1991-02-10',
            'nik' => $overrides['patient_nik'] ?? sprintf('3174011234569%03d', $sequence),
            'phone' => sprintf('082222222%03d', $sequence),
            'is_active' => true,
        ]);

        $branchRecord = PatientBranchRecord::query()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'medical_record_no' => sprintf('P-%05d', 200 + $sequence),
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
