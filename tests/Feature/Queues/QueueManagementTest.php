<?php

namespace Tests\Feature\Queues;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\QueueTicket;
use App\Models\Section;
use App\Models\User;
use App\Models\VisitRegistration;
use App\Services\PatientRecordService;
use App\Services\QueueService;
use Carbon\Carbon;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueManagementTest extends TestCase
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

    public function test_cashier_can_call_next_queue_from_active_counter(): void
    {
        $user = User::factory()->create();
        $user->assignRole('cashier');

        $branch = Branch::query()->firstOrFail();
        $counter = Counter::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Counter Queue',
            'code' => 'CQ1',
            'location' => 'Lobby',
            'description' => 'Counter queue',
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
            'description' => 'General service',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $patient = Patient::query()->create([
            'full_name' => 'Kevin Pasaribu',
            'gender' => 'male',
            'date_of_birth' => '2000-04-10',
            'nik' => '3174011004000001',
            'phone' => '081234560004',
            'email' => null,
            'province_code' => '31',
            'province_name' => 'DKI Jakarta',
            'city_code' => '3173',
            'city_name' => 'Kota Jakarta Barat',
            'district_code' => '317304',
            'district_name' => 'Palmerah',
            'village_code' => '3173041001',
            'village_name' => 'Slipi',
            'address_line' => 'Jl. Tomang',
            'allergy_notes' => null,
            'is_active' => true,
        ]);

        $record = app(PatientRecordService::class)->ensureBranchRecord($patient, $branch);

        $registration = VisitRegistration::query()->create([
            'patient_id' => $patient->id,
            'patient_branch_record_id' => $record->id,
            'branch_id' => $branch->id,
            'counter_id' => $counter->id,
            'section_id' => $section->id,
            'doctor_id' => null,
            'doctor_schedule_id' => null,
            'visit_date' => Carbon::today()->toDateString(),
            'visit_type' => 'emergency',
            'registration_status' => 'queued',
            'booking_code' => null,
            'slot_start_time' => null,
            'slot_end_time' => null,
            'notes' => 'Queue test',
            'checked_in_at' => now(),
        ]);

        app(QueueService::class)->createForRegistration($registration);

        $this->actingAs($user)
            ->withSession(['active_counter_id' => $counter->id])
            ->post(route('queues.call-next'), [
                'section_id' => $section->id,
            ])
            ->assertRedirect(route('queues'));

        $ticket = QueueTicket::query()->firstOrFail();

        $this->assertSame('called', $ticket->status);
    }

    public function test_queue_board_endpoint_returns_live_queue_with_doctor_and_room(): void
    {
        $user = User::factory()->create();
        $user->assignRole('cashier');

        $branch = Branch::query()->firstOrFail();
        $counter = Counter::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Counter Queue',
            'code' => 'CQ2',
            'location' => 'Lobby',
            'description' => 'Counter queue board',
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
            'description' => 'General service',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $doctor = Doctor::query()->create([
            'full_name' => 'Maria Simanjuntak',
            'title_prefix' => 'dr.',
            'title_suffix' => 'Sp.PD',
            'specialization' => 'Internal Medicine',
            'consultation_fee' => 200000,
            'str_number' => 'STR-QUEUE-001',
            'str_expired_at' => '2029-12-31',
            'sip_number' => 'SIP-QUEUE-001',
            'sip_expired_at' => '2028-12-31',
            'phone' => '081234561111',
            'email' => 'queue-maria@example.com',
            'address' => 'Jakarta',
            'is_active' => true,
        ]);

        $schedule = DoctorSchedule::query()->create([
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'section_id' => $section->id,
            'day_of_week' => Carbon::today()->dayOfWeekIso,
            'start_time' => '08:00',
            'end_time' => '12:00',
            'slot_duration_minutes' => 15,
            'max_patients' => 10,
            'room_label' => 'Room 1',
            'notes' => 'Morning clinic',
            'is_active' => true,
        ]);

        $patient = Patient::query()->create([
            'full_name' => 'Reina Hutapea',
            'gender' => 'female',
            'date_of_birth' => '1995-08-15',
            'nik' => '3174011508950001',
            'phone' => '081234561112',
            'email' => null,
            'province_code' => '31',
            'province_name' => 'DKI Jakarta',
            'city_code' => '3173',
            'city_name' => 'Kota Jakarta Barat',
            'district_code' => '317304',
            'district_name' => 'Palmerah',
            'village_code' => '3173041001',
            'village_name' => 'Slipi',
            'address_line' => 'Jl. Tomang',
            'allergy_notes' => null,
            'is_active' => true,
        ]);

        $record = app(PatientRecordService::class)->ensureBranchRecord($patient, $branch);

        $registration = VisitRegistration::query()->create([
            'patient_id' => $patient->id,
            'patient_branch_record_id' => $record->id,
            'branch_id' => $branch->id,
            'counter_id' => $counter->id,
            'section_id' => $section->id,
            'doctor_id' => $doctor->id,
            'doctor_schedule_id' => $schedule->id,
            'visit_date' => Carbon::today()->toDateString(),
            'visit_type' => 'same_day',
            'registration_status' => 'queued',
            'care_stage' => 'waiting_doctor',
            'vital_status' => 'completed',
            'booking_code' => null,
            'slot_start_time' => '08:00:00',
            'slot_end_time' => '08:15:00',
            'notes' => 'Queue board test',
            'checked_in_at' => now(),
            'queued_at' => now(),
        ]);

        MedicalRecord::query()->create([
            'visit_registration_id' => $registration->id,
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'section_id' => $section->id,
            'doctor_id' => $doctor->id,
            'subjective' => 'Batuk pilek',
            'objective' => 'Keadaan umum baik',
            'assessment' => 'ISPA ringan',
            'plan' => 'Terapi simptomatik',
            'diagnosis_notes' => null,
            'status' => 'final',
            'finalized_at' => now(),
            'finalized_by_user_id' => $user->id,
        ]);

        $ticket = app(QueueService::class)->createForRegistration($registration);
        app(QueueService::class)->transition($ticket, 'call');

        $response = $this->actingAs($user)
            ->withSession(['active_counter_id' => $counter->id])
            ->getJson(route('queues.board', ['date' => Carbon::today()->toDateString()]));

        $response->assertOk()
            ->assertJsonPath('liveBoard.0.queue_code', 'GEN-001')
            ->assertJsonPath('liveBoard.0.doctor_name', 'dr. Maria Simanjuntak, Sp.PD')
            ->assertJsonPath('liveBoard.0.room_label', 'Room 1')
            ->assertJsonPath('sectionSummaries.0.current_queue_code', 'GEN-001');
    }
}
