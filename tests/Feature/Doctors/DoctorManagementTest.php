<?php

namespace Tests\Feature\Doctors;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorManagementTest extends TestCase
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

    public function test_clinic_admin_can_open_doctors_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $this->actingAs($user)
            ->get(route('doctors'))
            ->assertOk()
            ->assertSee('Doctors');
    }

    public function test_clinic_admin_can_create_and_update_doctor_with_sections(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();

        $general = Section::query()->create([
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

        $dental = Section::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Dental',
            'code' => 'DENTAL',
            'type' => 'regular',
            'queue_prefix' => 'DEN',
            'queue_number_padding' => 3,
            'allow_appointment' => true,
            'allow_walk_in' => true,
            'description' => 'Dental service.',
            'sort_order' => 20,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->from(route('doctors'))
            ->post(route('doctors.store'), [
                'title_prefix' => 'dr.',
                'full_name' => 'Andreas Girsang',
                'title_suffix' => 'Sp.A',
                'specialization' => 'Pediatrics',
                'consultation_fee' => 150000,
                'str_number' => 'STR-001',
                'str_expired_at' => '2028-12-31',
                'sip_number' => 'SIP-001',
                'sip_expired_at' => '2027-12-31',
                'phone' => '081234567890',
                'email' => 'doctor.andreas@example.com',
                'address' => 'Jl. Sudirman 123',
                'sections' => [$general->id, $dental->id],
                'is_active' => 1,
            ])
            ->assertRedirect(route('doctors'));

        $doctor = Doctor::query()->where('email', 'doctor.andreas@example.com')->firstOrFail();

        $this->assertSame('Andreas Girsang', $doctor->full_name);
        $this->assertSame('Pediatrics', $doctor->specialization);
        $this->assertCount(2, $doctor->sections);

        $this->actingAs($user)
            ->from(route('doctors'))
            ->post(route('doctors.update', $doctor), [
                'title_prefix' => 'dr.',
                'full_name' => 'Andreas Girsang',
                'title_suffix' => 'Sp.PD',
                'specialization' => 'Internal Medicine',
                'consultation_fee' => 175000,
                'str_number' => 'STR-001',
                'str_expired_at' => '2029-12-31',
                'sip_number' => 'SIP-001',
                'sip_expired_at' => '2028-12-31',
                'phone' => '081234567890',
                'email' => 'doctor.andreas@example.com',
                'address' => 'Jl. Sudirman 456',
                'sections' => [$dental->id],
                'is_active' => 1,
            ])
            ->assertRedirect(route('doctors'));

        $doctor->refresh();

        $this->assertSame('Internal Medicine', $doctor->specialization);
        $this->assertSame('Sp.PD', $doctor->title_suffix);
        $this->assertSame('175000.00', $doctor->consultation_fee);
        $this->assertTrue($doctor->sections->pluck('id')->contains($dental->id));
        $this->assertFalse($doctor->sections->pluck('id')->contains($general->id));
    }

    public function test_sidebar_respects_doctor_permission(): void
    {
        $clinicAdmin = User::factory()->create();
        $clinicAdmin->assignRole('clinic-admin');

        $this->actingAs($clinicAdmin)
            ->get(route('dashboard'))
            ->assertSeeHtml('href="' . route('doctors', absolute: false) . '"');

        auth()->logout();

        $frontOffice = User::factory()->create();
        $frontOffice->assignRole('front-office');

        $this->actingAs($frontOffice)
            ->get(route('dashboard'))
            ->assertDontSeeHtml('href="' . route('doctors', absolute: false) . '"');
    }
}
