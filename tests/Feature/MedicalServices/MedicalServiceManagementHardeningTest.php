<?php

namespace Tests\Feature\MedicalServices;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\MedicalService;
use App\Models\Patient;
use App\Models\Section;
use App\Models\User;
use App\Models\VisitMedicalService;
use App\Models\VisitRegistration;
use App\Services\PatientRecordService;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicalServiceManagementHardeningTest extends TestCase
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

    public function test_medical_service_index_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('medical-services'))
            ->assertForbidden();
    }

    public function test_medical_service_index_json_supports_filters_sort_and_pagination(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        [$counter, $section, $doctor] = $this->createContext($branch);
        $visit = $this->createReadyVisit($branch, $counter, $section, $doctor, $user, [
            'patient_name' => 'Alpha Service',
            'patient_nik' => '3174000000041001',
        ]);

        $medicalService = MedicalService::query()->create([
            'code' => 'OBS-A',
            'name' => 'Alpha Observation',
            'service_type' => 'observation',
            'description' => 'Observation alpha',
            'default_fee' => 45000,
            'is_active' => true,
        ]);

        VisitMedicalService::query()->create([
            'visit_registration_id' => $visit->id,
            'branch_id' => $visit->branch_id,
            'section_id' => $visit->section_id,
            'medical_service_id' => $medicalService->id,
            'ordered_by_user_id' => $user->id,
            'performed_by_user_id' => null,
            'quantity' => 1,
            'unit_price' => 45000,
            'subtotal' => 45000,
            'status' => 'ordered',
            'ordered_at' => now(),
            'notes' => 'Alpha note',
        ]);

        $response = $this->actingAs($user)->getJson(route('medical-services', [
            'search' => 'Alpha',
            'branch' => $branch->id,
            'status' => 'ordered',
            'master_status' => 'active',
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
            ->assertJsonPath('data.filters.search', 'Alpha')
            ->assertJsonPath('data.filters.branch', (string) $branch->id)
            ->assertJsonPath('data.filters.master_sort_by', 'name')
            ->assertJsonPath('data.filters.order_per_page', 10)
            ->assertJsonPath('data.masters.data.0.name', 'Alpha Observation')
            ->assertJsonPath('data.orders.data.0.status', 'ordered');
    }

    public function test_create_master_is_idempotent_and_writes_audit_log(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');
        $branch = Branch::query()->firstOrFail();

        $payload = [
            'code' => 'NURSE-FEE',
            'name' => 'Nurse Service Fee',
            'service_type' => 'nursing',
            'description' => 'Nurse service fee',
            'default_fee' => 35000,
            'branch_prices' => [
                $branch->id => 40000,
            ],
            'is_active' => true,
        ];

        $this->actingAs($user)
            ->postJson(route('medical-services.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.changed', true);

        $this->actingAs($user)
            ->postJson(route('medical-services.store'), $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $medicalService = MedicalService::query()->where('code', 'NURSE-FEE')->firstOrFail();

        $this->assertSame(1, MedicalService::query()->count());
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'medical_service_management',
            'action' => 'create_master',
            'auditable_type' => $medicalService->getMorphClass(),
            'auditable_id' => $medicalService->id,
        ]);
    }

    public function test_update_master_is_idempotent_for_same_payload(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');
        $branch = Branch::query()->firstOrFail();

        $medicalService = MedicalService::query()->create([
            'code' => 'OBSERVE',
            'name' => 'Observation Fee',
            'service_type' => 'observation',
            'description' => 'Initial description',
            'default_fee' => 50000,
            'is_active' => true,
        ]);

        $payload = [
            'code' => 'OBSERVE',
            'name' => 'Observation Fee Updated',
            'service_type' => 'observation',
            'description' => 'Updated description',
            'default_fee' => 55000,
            'branch_prices' => [
                $branch->id => 57500,
            ],
            'is_active' => true,
        ];

        $this->actingAs($user)
            ->postJson(route('medical-services.update', $medicalService), $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', true);

        $this->actingAs($user)
            ->postJson(route('medical-services.update', $medicalService), $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertSame(
            1,
            AuditLog::query()->where('module', 'medical_service_management')->where('action', 'update_master')->count()
        );
    }

    public function test_update_order_cannot_move_to_another_visit(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        [$counter, $section, $doctor] = $this->createContext($branch);
        $visit = $this->createReadyVisit($branch, $counter, $section, $doctor, $user, [
            'patient_name' => 'Service One',
            'patient_nik' => '3174000000042001',
        ]);
        $otherVisit = $this->createReadyVisit($branch, $counter, $section, $doctor, $user, [
            'patient_name' => 'Service Two',
            'patient_nik' => '3174000000042002',
        ]);

        $medicalService = MedicalService::query()->create([
            'code' => 'ADMIN-FEE',
            'name' => 'Administration Fee',
            'service_type' => 'administrative',
            'description' => 'Admin fee',
            'default_fee' => 20000,
            'is_active' => true,
        ]);

        $order = VisitMedicalService::query()->create([
            'visit_registration_id' => $visit->id,
            'branch_id' => $visit->branch_id,
            'section_id' => $visit->section_id,
            'medical_service_id' => $medicalService->id,
            'ordered_by_user_id' => $user->id,
            'performed_by_user_id' => null,
            'quantity' => 1,
            'unit_price' => 20000,
            'subtotal' => 20000,
            'status' => 'ordered',
            'ordered_at' => now(),
            'notes' => null,
        ]);

        $this->actingAs($user)
            ->postJson(route('medical-service-orders.update', $order), [
                'visit_registration_id' => $otherVisit->id,
                'medical_service_id' => $medicalService->id,
                'quantity' => 1,
                'status' => 'ordered',
                'notes' => null,
            ])
            ->assertStatus(409)
            ->assertJsonPath('errors.visit_registration_id.0', 'Service order yang sudah tercatat tidak boleh dipindahkan ke visit lain.');
    }

    public function test_cancel_order_is_idempotent_and_logged(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        [$counter, $section, $doctor] = $this->createContext($branch);
        $visit = $this->createReadyVisit($branch, $counter, $section, $doctor, $user);

        $medicalService = MedicalService::query()->create([
            'code' => 'OBS-CANCEL',
            'name' => 'Observation Cancel',
            'service_type' => 'observation',
            'description' => 'Cancel test',
            'default_fee' => 65000,
            'is_active' => true,
        ]);

        $order = VisitMedicalService::query()->create([
            'visit_registration_id' => $visit->id,
            'branch_id' => $visit->branch_id,
            'section_id' => $visit->section_id,
            'medical_service_id' => $medicalService->id,
            'ordered_by_user_id' => $user->id,
            'performed_by_user_id' => null,
            'quantity' => 1,
            'unit_price' => 65000,
            'subtotal' => 65000,
            'status' => 'ordered',
            'ordered_at' => now(),
            'notes' => null,
        ]);

        $this->actingAs($user)
            ->postJson(route('medical-service-orders.delete', $order))
            ->assertOk()
            ->assertJsonPath('data.changed', true);

        $this->actingAs($user)
            ->postJson(route('medical-service-orders.delete', $order))
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertSame(
            1,
            AuditLog::query()->where('module', 'medical_service_management')->where('action', 'cancel_order')->count()
        );
    }

    public function test_cannot_create_order_with_inactive_master_service(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        [$counter, $section, $doctor] = $this->createContext($branch);
        $visit = $this->createReadyVisit($branch, $counter, $section, $doctor, $user);

        $medicalService = MedicalService::query()->create([
            'code' => 'INACTIVE-SVC',
            'name' => 'Inactive Service',
            'service_type' => 'other',
            'description' => 'Inactive service',
            'default_fee' => 10000,
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->postJson(route('medical-service-orders.store'), [
                'visit_registration_id' => $visit->id,
                'medical_service_id' => $medicalService->id,
                'quantity' => 1,
                'status' => 'ordered',
                'notes' => null,
            ])
            ->assertStatus(409)
            ->assertJsonPath('errors.medical_service_id.0', 'Medical service yang tidak aktif tidak bisa dipakai untuk order baru.');
    }

    private function createContext(Branch $branch): array
    {
        $sequence = ++$this->sequence;

        $counter = Counter::query()->create([
            'branch_id' => $branch->id,
            'name' => "Medical Service Counter {$sequence}",
            'code' => "MSC-{$sequence}",
            'location' => 'Lobby',
            'description' => 'Counter for medical service hardening',
            'sort_order' => $sequence * 10,
            'is_active' => true,
        ]);

        $section = Section::query()->create([
            'branch_id' => $branch->id,
            'name' => "Medical Service Section {$sequence}",
            'code' => "MSEC{$sequence}",
            'type' => 'regular',
            'queue_prefix' => 'MS',
            'queue_number_padding' => 3,
            'allow_appointment' => true,
            'allow_walk_in' => true,
            'description' => 'Medical service section',
            'sort_order' => $sequence * 10,
            'is_active' => true,
        ]);

        $doctor = Doctor::query()->create([
            'full_name' => "Medical Service Doctor {$sequence}",
            'title_prefix' => 'dr.',
            'title_suffix' => 'Sp.A',
            'specialization' => 'General',
            'consultation_fee' => 150000,
            'str_number' => sprintf('STR-MS-%03d', $sequence),
            'str_expired_at' => now()->addYears(2)->toDateString(),
            'sip_number' => sprintf('SIP-MS-%03d', $sequence),
            'sip_expired_at' => now()->addYears(2)->toDateString(),
            'phone' => sprintf('081388800%03d', $sequence),
            'email' => sprintf('medical-service-hardening-%03d@example.com', $sequence),
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
            'full_name' => $overrides['patient_name'] ?? "Medical Service Patient {$sequence}",
            'gender' => 'female',
            'date_of_birth' => '1992-10-10',
            'nik' => $overrides['patient_nik'] ?? sprintf('3174000000043%04d', $sequence),
            'phone' => sprintf('081366600%03d', $sequence),
            'province_code' => '31',
            'province_name' => 'DKI Jakarta',
            'city_code' => '3173',
            'city_name' => 'Kota Jakarta Barat',
            'district_code' => '317304',
            'district_name' => 'Palmerah',
            'village_code' => '3173041001',
            'village_name' => 'Slipi',
            'address_line' => 'Jl. Medical Service Test',
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
            'care_stage' => 'ready_for_checkout',
            'vital_status' => 'completed',
            'slot_start_time' => '08:00:00',
            'slot_end_time' => '08:15:00',
            'notes' => 'Medical service hardening visit',
            'checked_in_at' => now(),
            'queued_at' => now(),
        ]);

        MedicalRecord::query()->create([
            'visit_registration_id' => $visit->id,
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'section_id' => $section->id,
            'doctor_id' => $doctor->id,
            'subjective' => 'Keluhan service.',
            'objective' => 'Pemeriksaan stabil.',
            'assessment' => 'Perlu layanan tambahan.',
            'plan' => 'Lanjut medical service.',
            'diagnosis_notes' => 'Medical service hardening visit.',
            'status' => 'final',
            'finalized_at' => now(),
            'finalized_by_user_id' => $actor->id,
        ]);

        return $visit;
    }
}
