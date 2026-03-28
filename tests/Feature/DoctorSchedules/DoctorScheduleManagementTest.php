<?php

namespace Tests\Feature\DoctorSchedules;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorLeave;
use App\Models\DoctorSchedule;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorScheduleManagementTest extends TestCase
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

    public function test_clinic_admin_can_open_doctor_schedules_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $this->actingAs($user)
            ->get(route('doctor-schedules'))
            ->assertOk()
            ->assertSee('Doctor Schedules');
    }

    public function test_clinic_admin_can_create_and_update_schedule_and_leave(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();

        $section = Section::query()->create([
            'branch_id' => $branch->id,
            'name' => 'General',
            'code' => 'GENERAL',
            'type' => 'regular',
            'queue_prefix' => 'GEN',
            'queue_number_padding' => 3,
            'allow_appointment' => true,
            'allow_walk_in' => true,
            'description' => 'General service.',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $doctor = Doctor::query()->create([
            'title_prefix' => 'dr.',
            'full_name' => 'Maria Simanjuntak',
            'title_suffix' => 'Sp.PD',
            'specialization' => 'Internal Medicine',
            'consultation_fee' => 200000,
            'str_number' => 'STR-200',
            'str_expired_at' => '2029-12-31',
            'sip_number' => 'SIP-200',
            'sip_expired_at' => '2028-12-31',
            'phone' => '081234567891',
            'email' => 'maria.simanjuntak@example.com',
            'address' => 'Jl. Kesehatan No. 2',
            'is_active' => true,
        ]);

        $doctor->sections()->sync([$section->id]);

        $this->actingAs($user)
            ->from(route('doctor-schedules'))
            ->post(route('doctor-schedules.store'), [
                'doctor_id' => $doctor->id,
                'branch_id' => $branch->id,
                'section_id' => $section->id,
                'day_of_week' => 1,
                'start_time' => '08:00',
                'end_time' => '12:00',
                'slot_duration_minutes' => 15,
                'max_patients' => 16,
                'notes' => 'Schedule pagi.',
                'is_active' => 1,
            ])
            ->assertRedirect(route('doctor-schedules'));

        $schedule = DoctorSchedule::query()->firstOrFail();

        $this->actingAs($user)
            ->from(route('doctor-schedules'))
            ->post(route('doctor-schedules.update', $schedule), [
                'doctor_id' => $doctor->id,
                'branch_id' => $branch->id,
                'section_id' => $section->id,
                'day_of_week' => 3,
                'start_time' => '09:00',
                'end_time' => '13:00',
                'slot_duration_minutes' => 20,
                'max_patients' => 12,
                'notes' => 'Schedule siang.',
                'is_active' => 1,
            ])
            ->assertRedirect(route('doctor-schedules'));

        $this->assertDatabaseHas('doctor_schedules', [
            'id' => $schedule->id,
            'day_of_week' => 3,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'slot_duration_minutes' => 20,
            'max_patients' => 12,
        ]);

        $this->actingAs($user)
            ->from(route('doctor-schedules'))
            ->post(route('doctor-leaves.store'), [
                'doctor_id' => $doctor->id,
                'branch_id' => $branch->id,
                'leave_date' => '2026-04-10',
                'leave_type' => 'partial_time',
                'start_time' => '10:00',
                'end_time' => '12:00',
                'notes' => 'Seminar.',
                'is_active' => 1,
            ])
            ->assertRedirect(route('doctor-schedules'));

        $leave = DoctorLeave::query()->firstOrFail();

        $this->actingAs($user)
            ->from(route('doctor-schedules'))
            ->post(route('doctor-leaves.update', $leave), [
                'doctor_id' => $doctor->id,
                'branch_id' => $branch->id,
                'leave_date' => '2026-04-11',
                'leave_type' => 'full_day',
                'notes' => 'Cuti penuh.',
                'is_active' => 1,
            ])
            ->assertRedirect(route('doctor-schedules'));

        $this->assertDatabaseHas('doctor_leaves', [
            'id' => $leave->id,
            'leave_type' => 'full_day',
            'start_time' => null,
            'end_time' => null,
        ]);
        $this->assertSame('2026-04-11', $leave->fresh()->leave_date?->toDateString());
    }

    public function test_sidebar_respects_doctor_schedule_permission(): void
    {
        $clinicAdmin = User::factory()->create();
        $clinicAdmin->assignRole('clinic-admin');

        $this->actingAs($clinicAdmin)
            ->get(route('dashboard'))
            ->assertSeeHtml('href="' . route('doctor-schedules', absolute: false) . '"');

        auth()->logout();

        $frontOffice = User::factory()->create();
        $frontOffice->assignRole('front-office');

        $this->actingAs($frontOffice)
            ->get(route('dashboard'))
            ->assertDontSeeHtml('href="' . route('doctor-schedules', absolute: false) . '"');
    }
}
