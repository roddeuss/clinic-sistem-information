<?php

namespace Tests\Feature\VisitRegistrations;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
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

class VisitRegistrationManagementTest extends TestCase
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

    public function test_clinic_admin_can_create_same_day_registration_and_booking_check_in(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        $counter = Counter::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Counter Test',
            'code' => 'CT1',
            'location' => 'Lobby',
            'description' => 'Counter test',
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
            'str_number' => 'STR-900',
            'str_expired_at' => '2029-12-31',
            'sip_number' => 'SIP-900',
            'sip_expired_at' => '2028-12-31',
            'phone' => '081234560002',
            'email' => 'maria@example.com',
            'address' => 'Jakarta',
            'is_active' => true,
        ]);

        $doctor->sections()->sync([$section->id]);

        $schedule = DoctorSchedule::query()->create([
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'section_id' => $section->id,
            'day_of_week' => Carbon::today()->dayOfWeekIso,
            'start_time' => '08:00',
            'end_time' => '12:00',
            'slot_duration_minutes' => 15,
            'max_patients' => 10,
            'notes' => 'Morning clinic',
            'is_active' => true,
        ]);

        $patient = Patient::query()->create([
            'full_name' => 'Rina Sembiring',
            'gender' => 'female',
            'date_of_birth' => '1998-05-12',
            'nik' => '3174011205980001',
            'phone' => '081234560003',
            'email' => 'rina@example.com',
            'province_code' => '31',
            'province_name' => 'DKI Jakarta',
            'city_code' => '3173',
            'city_name' => 'Kota Jakarta Barat',
            'district_code' => '317304',
            'district_name' => 'Palmerah',
            'village_code' => '3173041001',
            'village_name' => 'Slipi',
            'address_line' => 'Jl. Kebon Jeruk',
            'allergy_notes' => null,
            'is_active' => true,
        ]);

        app(PatientRecordService::class)->ensureBranchRecord($patient, $branch);

        $this->actingAs($user)
            ->withSession(['active_counter_id' => $counter->id])
            ->post(route('visit-registrations.store'), [
                'patient_id' => $patient->id,
                'section_id' => $section->id,
                'visit_date' => Carbon::today()->toDateString(),
                'visit_type' => 'same_day',
                'doctor_schedule_id' => $schedule->id,
                'slot_start_time' => '08:00:00',
                'slot_end_time' => '08:15:00',
                'notes' => 'Same day test',
            ])
            ->assertRedirect(route('visit-registrations'));

        $registration = VisitRegistration::query()->where('patient_id', $patient->id)->where('visit_type', 'same_day')->firstOrFail();

        $this->assertSame('queued', $registration->registration_status);
        $this->assertDatabaseHas('queue_tickets', [
            'visit_registration_id' => $registration->id,
            'status' => 'waiting',
        ]);

        $booking = VisitRegistration::query()->create([
            'patient_id' => $patient->id,
            'patient_branch_record_id' => $registration->patient_branch_record_id,
            'branch_id' => $branch->id,
            'counter_id' => $counter->id,
            'section_id' => $section->id,
            'doctor_id' => $doctor->id,
            'doctor_schedule_id' => $schedule->id,
            'visit_date' => Carbon::today()->toDateString(),
            'visit_type' => 'booking',
            'registration_status' => 'booked',
            'booking_code' => 'BK-' . Carbon::today()->format('Ymd') . '-9999',
            'slot_start_time' => '08:30:00',
            'slot_end_time' => '08:45:00',
            'notes' => 'Booking for check-in',
        ]);

        $this->actingAs($user)
            ->withSession(['active_counter_id' => $counter->id])
            ->post(route('visit-registrations.check-in', $booking))
            ->assertRedirect(route('visit-registrations'));

        $booking->refresh();

        $this->assertSame('queued', $booking->registration_status);
        $this->assertNotNull($booking->checked_in_at);
        $this->assertDatabaseHas('queue_tickets', [
            'visit_registration_id' => $booking->id,
            'status' => 'waiting',
        ]);
    }
}
