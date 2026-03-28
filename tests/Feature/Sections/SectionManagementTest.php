<?php

namespace Tests\Feature\Sections;

use App\Models\Branch;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionManagementTest extends TestCase
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

    public function test_clinic_admin_can_open_sections_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $this->actingAs($user)
            ->get(route('sections'))
            ->assertOk()
            ->assertSee('Sections');
    }

    public function test_clinic_admin_can_create_and_update_section(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();

        $this->actingAs($user)
            ->from(route('sections'))
            ->post(route('sections.store'), [
                'branch_id' => $branch->id,
                'name' => 'General',
                'type' => 'regular',
                'queue_prefix' => '',
                'queue_number_padding' => 3,
                'allow_appointment' => 1,
                'allow_walk_in' => 1,
                'description' => 'Layanan umum harian.',
                'sort_order' => 10,
                'is_active' => 1,
            ])
            ->assertRedirect(route('sections'));

        $section = Section::query()->where('name', 'General')->firstOrFail();

        $this->assertSame('GENERAL', $section->code);
        $this->assertSame('GEN', $section->queue_prefix);

        $this->actingAs($user)
            ->from(route('sections'))
            ->post(route('sections.update', $section), [
                'branch_id' => $branch->id,
                'name' => 'Emergency',
                'type' => 'emergency',
                'queue_prefix' => '',
                'queue_number_padding' => 4,
                'allow_appointment' => 0,
                'allow_walk_in' => 1,
                'description' => 'Layanan emergency.',
                'sort_order' => 20,
                'is_active' => 1,
            ])
            ->assertRedirect(route('sections'));

        $this->assertDatabaseHas('sections', [
            'id' => $section->id,
            'name' => 'Emergency',
            'code' => 'EMERGENCY',
            'type' => 'emergency',
            'queue_number_padding' => 4,
            'allow_appointment' => false,
        ]);
    }

    public function test_sidebar_respects_section_permission(): void
    {
        $clinicAdmin = User::factory()->create();
        $clinicAdmin->assignRole('clinic-admin');

        $this->actingAs($clinicAdmin)
            ->get(route('dashboard'))
            ->assertSeeHtml('href="' . route('sections', absolute: false) . '"');

        auth()->logout();

        $frontOffice = User::factory()->create();
        $frontOffice->assignRole('front-office');

        $this->actingAs($frontOffice)
            ->get(route('dashboard'))
            ->assertDontSeeHtml('href="' . route('sections', absolute: false) . '"');
    }
}
