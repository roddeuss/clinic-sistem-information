<?php

namespace Tests\Feature\VisitRegistrations;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\PatientBranchRecord;
use App\Models\QueueTicket;
use App\Models\Section;
use App\Models\User;
use App\Models\VisitRegistration;
use App\Services\PatientRecordService;
use Carbon\Carbon;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitRegistrationHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected Counter $counter;
    protected Section $section;
    protected Doctor $doctor;
    protected DoctorSchedule $schedule;
    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            AccessControlSeeder::class,
            ClinicSettingsSeeder::class,
            NavigationSeeder::class,
        ]);

        $this->branch = Branch::query()->firstOrFail();
        $this->counter = Counter::query()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Counter Test',
            'code' => 'CT1',
            'location' => 'Lobby',
            'description' => 'Counter test',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $this->section = Section::query()->create([
            'branch_id' => $this->branch->id,
            'name' => 'General',
            'code' => 'GENERAL',
            'type' => 'regular',
            'queue_prefix' => 'GEN',
            'queue_number_padding' => 3,
            'allow_appointment' => true,
            'allow_walk_in' => true,
            'description' => 'General service',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $this->doctor = Doctor::query()->create([
            'full_name' => 'Maria Simanjuntak',
            'title_prefix' => 'dr.',
            'title_suffix' => 'Sp.PD',
            'specialization' => 'Internal Medicine',
            'consultation_fee' => 200000,
            'str_number' => 'STR-900',
            'str_expired_at' => '2029-12-31',
            'sip_number' => 'SIP-900',
            'sip_expired_at' => '2028-12-31',
            'phone' => '081234560002',
            'email' => 'maria@example.com',
            'address' => 'Jakarta',
            'is_active' => true,
        ]);
        $this->doctor->sections()->sync([$this->section->id]);
        $this->schedule = DoctorSchedule::query()->create([
            'doctor_id' => $this->doctor->id,
            'branch_id' => $this->branch->id,
            'section_id' => $this->section->id,
            'day_of_week' => Carbon::today()->dayOfWeekIso,
            'start_time' => '08:00',
            'end_time' => '12:00',
            'slot_duration_minutes' => 15,
            'max_patients' => 10,
            'notes' => 'Morning clinic',
            'is_active' => true,
        ]);
        $this->patient = Patient::query()->create([
            'full_name' => 'Rina Sembiring',
            'gender' => 'female',
            'date_of_birth' => '1998-05-12',
            'nik' => '3174011205980001',
            'phone' => '081234560003',
            'email' => 'rina@example.com',
            'is_active' => true,
        ]);

        app(PatientRecordService::class)->ensureBranchRecord($this->patient, $this->branch);
    }

    public function test_visit_registration_index_requires_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('doctor');

        $this->actingAs($user)
            ->get(route('visit-registrations'))
            ->assertForbidden();
    }

    public function test_visit_registration_index_json_supports_filter_sort_and_pagination(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $record = PatientBranchRecord::query()->where('patient_id', $this->patient->id)->firstOrFail();

        VisitRegistration::query()->create([
            'patient_id' => $this->patient->id,
            'patient_branch_record_id' => $record->id,
            'branch_id' => $this->branch->id,
            'counter_id' => $this->counter->id,
            'section_id' => $this->section->id,
            'doctor_id' => $this->doctor->id,
            'doctor_schedule_id' => $this->schedule->id,
            'visit_date' => now()->toDateString(),
            'visit_type' => 'same_day',
            'registration_status' => 'queued',
            'care_stage' => 'waiting_nurse',
            'vital_status' => 'pending',
            'slot_start_time' => '08:00:00',
            'slot_end_time' => '08:15:00',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_counter_id' => $this->counter->id])
            ->getJson(route('visit-registrations', [
                'type' => 'same_day',
                'status' => 'queued',
                'section' => $this->section->id,
                'sort_by' => 'visit_date',
                'sort_direction' => 'desc',
                'per_page' => 10,
            ]));

        $response
            ->assertOk()
            ->assertJsonPath('data.filters.type', 'same_day')
            ->assertJsonPath('data.filters.status', 'queued')
            ->assertJsonCount(1, 'data.registrations.data')
            ->assertJsonPath('data.registrations.data.0.patient_name', 'Rina Sembiring');
    }

    public function test_create_registration_writes_audit_log_and_queue(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $response = $this->actingAs($user)
            ->withSession(['active_counter_id' => $this->counter->id])
            ->postJson(route('visit-registrations.store'), [
                'patient_id' => $this->patient->id,
                'section_id' => $this->section->id,
                'visit_date' => Carbon::today()->toDateString(),
                'visit_type' => 'same_day',
                'doctor_schedule_id' => $this->schedule->id,
                'slot_start_time' => '08:00:00',
                'slot_end_time' => '08:15:00',
                'notes' => 'Same day test',
            ]);

        $registration = VisitRegistration::query()->firstOrFail();

        $response
            ->assertCreated()
            ->assertJsonPath('data.registration.registration_status', 'queued');

        $this->assertDatabaseHas('queue_tickets', [
            'visit_registration_id' => $registration->id,
            'status' => 'waiting',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'visit_registration_management',
            'action' => 'create',
            'auditable_type' => $registration->getMorphClass(),
            'auditable_id' => $registration->id,
        ]);
    }

    public function test_update_registration_is_idempotent_when_payload_is_same(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $bookingDate = Carbon::tomorrow();
        $futureSchedule = DoctorSchedule::query()->create([
            'doctor_id' => $this->doctor->id,
            'branch_id' => $this->branch->id,
            'section_id' => $this->section->id,
            'day_of_week' => $bookingDate->dayOfWeekIso,
            'start_time' => '08:00',
            'end_time' => '12:00',
            'slot_duration_minutes' => 15,
            'max_patients' => 10,
            'notes' => 'Future clinic',
            'is_active' => true,
        ]);

        $record = PatientBranchRecord::query()->where('patient_id', $this->patient->id)->firstOrFail();
        $booking = VisitRegistration::query()->create([
            'patient_id' => $this->patient->id,
            'patient_branch_record_id' => $record->id,
            'branch_id' => $this->branch->id,
            'counter_id' => $this->counter->id,
            'section_id' => $this->section->id,
            'doctor_id' => $this->doctor->id,
            'doctor_schedule_id' => $futureSchedule->id,
            'visit_date' => $bookingDate->toDateString(),
            'visit_type' => 'booking',
            'registration_status' => 'booked',
            'care_stage' => 'scheduled',
            'vital_status' => 'pending',
            'booking_code' => 'BK-' . $bookingDate->format('Ymd') . '-0001',
            'slot_start_time' => '08:30:00',
            'slot_end_time' => '08:45:00',
            'notes' => 'Booking',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_counter_id' => $this->counter->id])
            ->postJson(route('visit-registrations.update', $booking), [
                'patient_id' => $this->patient->id,
                'section_id' => $this->section->id,
                'visit_date' => $bookingDate->toDateString(),
                'visit_type' => 'booking',
                'doctor_schedule_id' => $futureSchedule->id,
                'slot_start_time' => '08:30:00',
                'slot_end_time' => '08:45:00',
                'notes' => 'Booking',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertSame(0, AuditLog::query()->where('module', 'visit_registration_management')->where('action', 'update')->count());
    }

    public function test_update_registration_is_blocked_when_active_queue_exists(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $record = PatientBranchRecord::query()->where('patient_id', $this->patient->id)->firstOrFail();
        $registration = VisitRegistration::query()->create([
            'patient_id' => $this->patient->id,
            'patient_branch_record_id' => $record->id,
            'branch_id' => $this->branch->id,
            'counter_id' => $this->counter->id,
            'section_id' => $this->section->id,
            'doctor_id' => $this->doctor->id,
            'doctor_schedule_id' => $this->schedule->id,
            'visit_date' => Carbon::today()->toDateString(),
            'visit_type' => 'same_day',
            'registration_status' => 'queued',
            'care_stage' => 'waiting_nurse',
            'vital_status' => 'pending',
            'slot_start_time' => '08:00:00',
            'slot_end_time' => '08:15:00',
        ]);

        QueueTicket::query()->create([
            'visit_registration_id' => $registration->id,
            'patient_id' => $this->patient->id,
            'patient_branch_record_id' => $record->id,
            'branch_id' => $this->branch->id,
            'section_id' => $this->section->id,
            'counter_id' => $this->counter->id,
            'doctor_id' => $this->doctor->id,
            'queue_date' => Carbon::today()->toDateString(),
            'queue_number' => 1,
            'queue_code' => 'GEN-001',
            'status' => 'waiting',
        ]);

        $this->actingAs($user)
            ->withSession(['active_counter_id' => $this->counter->id])
            ->postJson(route('visit-registrations.update', $registration), [
                'patient_id' => $this->patient->id,
                'section_id' => $this->section->id,
                'visit_date' => Carbon::today()->toDateString(),
                'visit_type' => 'same_day',
                'doctor_schedule_id' => $this->schedule->id,
                'slot_start_time' => '08:15:00',
                'slot_end_time' => '08:30:00',
                'notes' => 'Cannot edit',
            ])
            ->assertStatus(409)
            ->assertJsonPath('errors.visit_registration.0', 'Registrasi yang sudah memiliki antrian aktif tidak bisa diubah dari halaman ini.');
    }

    public function test_check_in_booking_is_idempotent(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $record = PatientBranchRecord::query()->where('patient_id', $this->patient->id)->firstOrFail();
        $booking = VisitRegistration::query()->create([
            'patient_id' => $this->patient->id,
            'patient_branch_record_id' => $record->id,
            'branch_id' => $this->branch->id,
            'counter_id' => $this->counter->id,
            'section_id' => $this->section->id,
            'doctor_id' => $this->doctor->id,
            'doctor_schedule_id' => $this->schedule->id,
            'visit_date' => Carbon::today()->toDateString(),
            'visit_type' => 'booking',
            'registration_status' => 'booked',
            'care_stage' => 'scheduled',
            'vital_status' => 'pending',
            'booking_code' => 'BK-' . Carbon::today()->format('Ymd') . '-0001',
            'slot_start_time' => '08:30:00',
            'slot_end_time' => '08:45:00',
        ]);

        $this->actingAs($user)
            ->withSession(['active_counter_id' => $this->counter->id])
            ->postJson(route('visit-registrations.check-in', $booking))
            ->assertOk()
            ->assertJsonPath('data.changed', true);

        $this->actingAs($user)
            ->withSession(['active_counter_id' => $this->counter->id])
            ->postJson(route('visit-registrations.check-in', $booking))
            ->assertOk()
            ->assertJsonPath('data.changed', false);
    }

    public function test_cancel_registration_writes_audit_log(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $record = PatientBranchRecord::query()->where('patient_id', $this->patient->id)->firstOrFail();
        $registration = VisitRegistration::query()->create([
            'patient_id' => $this->patient->id,
            'patient_branch_record_id' => $record->id,
            'branch_id' => $this->branch->id,
            'counter_id' => $this->counter->id,
            'section_id' => $this->section->id,
            'doctor_id' => $this->doctor->id,
            'doctor_schedule_id' => $this->schedule->id,
            'visit_date' => Carbon::today()->toDateString(),
            'visit_type' => 'same_day',
            'registration_status' => 'queued',
            'care_stage' => 'waiting_nurse',
            'vital_status' => 'pending',
            'slot_start_time' => '08:45:00',
            'slot_end_time' => '09:00:00',
        ]);

        $this->actingAs($user)
            ->withSession(['active_counter_id' => $this->counter->id])
            ->postJson(route('visit-registrations.cancel', $registration), [
                'reason' => 'Pasien batal datang',
            ])
            ->assertOk()
            ->assertJsonPath('data.changed', true);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'visit_registration_management',
            'action' => 'cancel',
            'auditable_type' => $registration->getMorphClass(),
            'auditable_id' => $registration->id,
        ]);
    }
}
