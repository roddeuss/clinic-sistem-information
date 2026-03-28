<?php

namespace Tests\Feature\Fulfillment;

use App\Models\Branch;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FulfillmentModuleSmokeTest extends TestCase
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

    public function test_clinic_admin_can_open_fulfillment_pages(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $this->actingAs($user)->get(route('pharmacy'))->assertOk()->assertSee('Pharmacy');
        $this->actingAs($user)->get(route('prescriptions'))->assertOk()->assertSee('Prescription');
        $this->actingAs($user)->get(route('procedures'))->assertOk()->assertSee('Procedures');
        $this->actingAs($user)->get(route('laboratory'))->assertOk()->assertSee('Laboratory');
        $this->actingAs($user)->get(route('billing'))->assertOk()->assertSee('Sales Invoices');
    }

    public function test_clinic_admin_can_create_medicine_and_batch(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();

        $this->actingAs($user)
            ->from(route('pharmacy'))
            ->post(route('pharmacy.store'), [
                'code' => 'PCM500',
                'name' => 'Paracetamol',
                'generic_name' => 'Paracetamol',
                'dosage_form' => 'tablet',
                'strength' => '500 mg',
                'base_unit' => 'tablet',
                'description' => 'Pain relief',
                'branch_prices' => [
                    $branch->id => 1500,
                ],
                'is_compoundable' => 1,
                'is_active' => 1,
            ])
            ->assertRedirect(route('pharmacy'));

        $medicine = Medicine::query()->where('code', 'PCM500')->firstOrFail();

        $this->actingAs($user)
            ->from(route('pharmacy'))
            ->post(route('medicine-batches.store'), [
                'medicine_id' => $medicine->id,
                'branch_id' => $branch->id,
                'batch_number' => 'PCM500-A1',
                'received_at' => now()->toDateString(),
                'expired_at' => now()->addMonths(12)->toDateString(),
                'quantity_received' => 120,
                'quantity_available' => 120,
                'purchase_cost' => 800,
                'supplier_name' => 'PT Demo Farmasi',
                'notes' => 'Initial stock',
                'is_active' => 1,
            ])
            ->assertRedirect(route('pharmacy'));

        $batch = MedicineBatch::query()->where('batch_number', 'PCM500-A1')->firstOrFail();

        $this->assertSame($branch->id, $batch->branch_id);
        $this->assertSame($medicine->id, $batch->medicine_id);
        $this->assertSame('120.00', $batch->quantity_available);
    }
}
