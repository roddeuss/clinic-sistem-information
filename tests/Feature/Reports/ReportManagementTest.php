<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportManagementTest extends TestCase
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

    public function test_clinic_admin_can_open_reports_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $this->actingAs($user)
            ->get(route('reports'))
            ->assertOk()
            ->assertSee('Operational Reports')
            ->assertSee('Visit By Branch')
            ->assertSee('Procurement Summary');
    }

    public function test_cashier_cannot_open_reports_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('cashier');

        $this->actingAs($user)
            ->get(route('reports'))
            ->assertForbidden();
    }

    public function test_clinic_admin_can_export_reports_to_excel_and_pdf(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $excelResponse = $this->actingAs($user)
            ->get(route('reports.export-excel', [
                'dataset' => 'visits',
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->toDateString(),
            ]));

        $excelResponse
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->assertStringContainsString('.xlsx', (string) $excelResponse->headers->get('content-disposition'));

        $pdfResponse = $this->actingAs($user)
            ->get(route('reports.export-pdf', [
                'dataset' => 'diagnostics',
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->toDateString(),
            ]));

        $pdfResponse
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->assertStringContainsString('.pdf', (string) $pdfResponse->headers->get('content-disposition'));
    }
}
