<?php

namespace Tests\Feature\Referrals;

use App\Models\AuditLog;
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

class ReferralManagementHardeningTest extends TestCase
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

    public function test_referral_index_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('referrals'))
            ->assertForbidden();
    }

    public function test_referral_index_json_supports_filters_sort_and_pagination(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $scenario = $this->createClinicalScenario([
            'patient_name' => 'Alpha Referral Patient',
            'patient_nik' => '3174011234567001',
        ]);

        $destination = ReferralDestination::query()->create([
            'code' => 'RS-ALPHA',
            'destination_type' => 'hospital',
            'name' => 'Alpha Hospital',
            'address' => 'Jl. Alpha',
            'contact_person' => 'Desk Alpha',
            'phone' => '021000111',
            'notes' => null,
            'is_active' => true,
        ]);

        PatientReferral::query()->create([
            'visit_registration_id' => $scenario['visit']->id,
            'patient_id' => $scenario['patient']->id,
            'patient_branch_record_id' => $scenario['branchRecord']->id,
            'branch_id' => $scenario['branch']->id,
            'section_id' => $scenario['section']->id,
            'doctor_id' => $scenario['doctor']->id,
            'referral_destination_id' => $destination->id,
            'status' => 'draft',
            'destination_type' => $destination->destination_type,
            'destination_name' => $destination->name,
            'destination_address' => $destination->address,
            'destination_phone' => $destination->phone,
            'diagnosis_summary' => 'J06.9 - URI',
            'clinical_summary' => 'Clinical summary',
            'treatment_summary' => 'Treatment summary',
            'reason' => 'Alpha reason',
            'notes' => 'Alpha note',
        ]);

        $this->actingAs($user)
            ->getJson(route('referrals', [
                'destination_search' => 'Alpha',
                'destination_status' => 'active',
                'destination_type' => 'hospital',
                'destination_sort_by' => 'name',
                'destination_sort_direction' => 'asc',
                'destination_per_page' => 10,
                'referral_search' => 'Alpha',
                'referral_status' => 'draft',
                'referral_sort_by' => 'created_at',
                'referral_sort_direction' => 'desc',
                'referral_per_page' => 10,
            ]))
            ->assertOk()
            ->assertJsonPath('data.filters.destination_search', 'Alpha')
            ->assertJsonPath('data.filters.destination_type', 'hospital')
            ->assertJsonPath('data.filters.referral_status', 'draft')
            ->assertJsonPath('data.destinations.data.0.name', 'Alpha Hospital')
            ->assertJsonPath('data.referrals.data.0.patient_name', 'Alpha Referral Patient');
    }

    public function test_create_destination_is_idempotent_and_logged(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $payload = [
            'code' => 'RS-IDEMP',
            'destination_type' => 'hospital',
            'name' => 'Rumah Sakit Idempotent',
            'address' => 'Jl. Idempotent 1',
            'contact_person' => 'Desk',
            'phone' => '021123123',
            'notes' => 'Testing',
            'is_active' => true,
        ];

        $this->actingAs($user)
            ->postJson(route('referral-destinations.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.changed', true);

        $this->actingAs($user)
            ->postJson(route('referral-destinations.store'), $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $destination = ReferralDestination::query()->where('code', 'RS-IDEMP')->firstOrFail();

        $this->assertSame(1, ReferralDestination::query()->where('code', 'RS-IDEMP')->count());
        $this->assertSame(
            1,
            AuditLog::query()->where('module', 'referral_management')->where('action', 'create_destination')->count()
        );
        $this->assertSame('Rumah Sakit Idempotent', $destination->name);
    }

    public function test_update_destination_is_idempotent_for_same_payload(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $destination = ReferralDestination::query()->create([
            'code' => 'RS-UPD',
            'destination_type' => 'specialist',
            'name' => 'Specialist Center',
            'address' => 'Jl. Lama',
            'contact_person' => 'Desk',
            'phone' => '021999000',
            'notes' => null,
            'is_active' => true,
        ]);

        $payload = [
            'code' => 'RS-UPD',
            'destination_type' => 'specialist',
            'name' => 'Specialist Center Updated',
            'address' => 'Jl. Baru',
            'contact_person' => 'Desk Baru',
            'phone' => '021999111',
            'notes' => 'Updated note',
            'is_active' => true,
        ];

        $this->actingAs($user)
            ->postJson(route('referral-destinations.update', $destination), $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', true);

        $this->actingAs($user)
            ->postJson(route('referral-destinations.update', $destination), $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertSame(
            1,
            AuditLog::query()->where('module', 'referral_management')->where('action', 'update_destination')->count()
        );
    }

    public function test_issue_referral_is_idempotent_once_issued(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $scenario = $this->createClinicalScenario([
            'patient_name' => 'Issue Referral Patient',
            'patient_nik' => '3174011234567002',
        ]);

        $destination = ReferralDestination::query()->create([
            'code' => 'RS-ISSUE',
            'destination_type' => 'hospital',
            'name' => 'Issue Hospital',
            'address' => 'Jl. Issue',
            'contact_person' => 'Desk Issue',
            'phone' => '021777111',
            'notes' => null,
            'is_active' => true,
        ]);

        $referral = PatientReferral::query()->create([
            'visit_registration_id' => $scenario['visit']->id,
            'patient_id' => $scenario['patient']->id,
            'patient_branch_record_id' => $scenario['branchRecord']->id,
            'branch_id' => $scenario['branch']->id,
            'section_id' => $scenario['section']->id,
            'doctor_id' => $scenario['doctor']->id,
            'referral_destination_id' => $destination->id,
            'status' => 'draft',
            'destination_type' => $destination->destination_type,
            'destination_name' => $destination->name,
            'destination_address' => $destination->address,
            'destination_phone' => $destination->phone,
            'diagnosis_summary' => 'A00 - Diagnosis',
            'clinical_summary' => 'Clinical summary',
            'treatment_summary' => 'Treatment summary',
            'reason' => 'Need higher care',
            'notes' => null,
        ]);

        $this->actingAs($user)
            ->postJson(route('referrals.issue', $referral))
            ->assertOk()
            ->assertJsonPath('data.changed', true);

        $this->actingAs($user)
            ->postJson(route('referrals.issue', $referral))
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertSame(
            1,
            AuditLog::query()->where('module', 'referral_management')->where('action', 'issue_referral')->count()
        );
    }

    public function test_void_referral_is_idempotent_with_same_reason(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $scenario = $this->createClinicalScenario([
            'patient_name' => 'Void Referral Patient',
            'patient_nik' => '3174011234567003',
        ]);

        $destination = ReferralDestination::query()->create([
            'code' => 'RS-VOID',
            'destination_type' => 'hospital',
            'name' => 'Void Hospital',
            'address' => 'Jl. Void',
            'contact_person' => 'Desk Void',
            'phone' => '021888111',
            'notes' => null,
            'is_active' => true,
        ]);

        $referral = PatientReferral::query()->create([
            'visit_registration_id' => $scenario['visit']->id,
            'patient_id' => $scenario['patient']->id,
            'patient_branch_record_id' => $scenario['branchRecord']->id,
            'branch_id' => $scenario['branch']->id,
            'section_id' => $scenario['section']->id,
            'doctor_id' => $scenario['doctor']->id,
            'referral_destination_id' => $destination->id,
            'referral_no' => 'REF-TEST-001',
            'status' => 'issued',
            'destination_type' => $destination->destination_type,
            'destination_name' => $destination->name,
            'destination_address' => $destination->address,
            'destination_phone' => $destination->phone,
            'diagnosis_summary' => 'A00 - Diagnosis',
            'clinical_summary' => 'Clinical summary',
            'treatment_summary' => 'Treatment summary',
            'reason' => 'Need higher care',
            'notes' => null,
            'issued_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)
            ->postJson(route('referrals.void', $referral), [
                'void_reason' => 'Administrative correction',
            ])
            ->assertOk()
            ->assertJsonPath('data.changed', true);

        $this->actingAs($user)
            ->postJson(route('referrals.void', $referral), [
                'void_reason' => 'Administrative correction',
            ])
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertSame(
            1,
            AuditLog::query()->where('module', 'referral_management')->where('action', 'void_referral')->count()
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
            'name' => "Referral Counter {$sequence}",
            'code' => "CTR-REF-{$sequence}",
            'location' => 'Front desk',
            'sort_order' => 10 + $sequence,
            'is_active' => true,
        ]);

        $section = Section::query()->create([
            'branch_id' => $branch->id,
            'name' => "General {$sequence}",
            'code' => "GENERAL{$sequence}",
            'type' => 'regular',
            'queue_prefix' => 'GEN',
            'queue_number_padding' => 3,
            'allow_appointment' => true,
            'allow_walk_in' => true,
            'sort_order' => 10 + $sequence,
            'is_active' => true,
        ]);

        $doctor = Doctor::query()->create([
            'full_name' => "Referral Doctor {$sequence}",
            'title_prefix' => 'dr.',
            'specialization' => 'General Practitioner',
            'consultation_fee' => 150000,
            'sip_number' => "SIP-REF-{$sequence}",
            'is_active' => true,
        ]);

        $patient = Patient::query()->create([
            'full_name' => $overrides['patient_name'] ?? "Referral Patient {$sequence}",
            'gender' => 'female',
            'date_of_birth' => '1990-05-12',
            'nik' => $overrides['patient_nik'] ?? sprintf('3174011234568%03d', $sequence),
            'phone' => sprintf('081111111%03d', $sequence),
            'is_active' => true,
        ]);

        $branchRecord = PatientBranchRecord::query()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'medical_record_no' => sprintf('P-%05d', $sequence),
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
