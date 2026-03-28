<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\ProductCategory;
use App\Models\PurchaseReturn;
use App\Models\StockAdjustment;
use App\Models\StockOpname;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryControlManagementTest extends TestCase
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

    public function test_clinic_admin_can_open_inventory_control_pages(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $this->actingAs($user)->get(route('purchase-returns'))->assertOk()->assertSee('Purchase Returns');
        $this->actingAs($user)->get(route('stock-adjustments'))->assertOk()->assertSee('Stock Adjustments');
        $this->actingAs($user)->get(route('stock-opnames'))->assertOk()->assertSee('Stock Opnames');
        $this->actingAs($user)->get(route('expiry-monitoring'))->assertOk()->assertSee('Expiry Monitoring');
    }

    public function test_clinic_admin_can_create_and_complete_purchase_return(): void
    {
        [$user, $branch, $supplier, $medicine] = $this->inventoryContext('RET');

        $batch = MedicineBatch::query()->create([
            'branch_id' => $branch->id,
            'medicine_id' => $medicine->id,
            'supplier_id' => $supplier->id,
            'batch_number' => 'RET-BATCH-001',
            'received_at' => now()->subDays(2)->toDateString(),
            'expired_at' => now()->addMonths(8)->toDateString(),
            'quantity_received' => 50,
            'quantity_available' => 50,
            'purchase_cost' => 1500,
            'supplier_name' => $supplier->name,
            'notes' => 'Batch untuk test purchase return.',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->from(route('purchase-returns'))
            ->post(route('purchase-returns.store'), [
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'return_date' => now()->toDateString(),
                'notes' => 'Return testing',
                'items' => [
                    [
                        'medicine_batch_id' => $batch->id,
                        'quantity_returned' => 5,
                        'reason' => 'Kemasan penyok',
                        'notes' => 'Return item',
                    ],
                ],
            ])
            ->assertRedirect(route('purchase-returns'));

        $purchaseReturn = PurchaseReturn::query()->firstOrFail();

        $this->assertSame('draft', $purchaseReturn->status);
        $this->assertSame('7500.00', $purchaseReturn->total_amount);

        $this->actingAs($user)
            ->post(route('purchase-returns.complete', $purchaseReturn))
            ->assertRedirect();

        $batch->refresh();
        $purchaseReturn->refresh();

        $this->assertSame('completed', $purchaseReturn->status);
        $this->assertSame('45.00', $batch->quantity_available);
    }

    public function test_clinic_admin_can_apply_adjustment_finalize_opname_and_manage_quarantine(): void
    {
        [$user, $branch, $supplier, $medicine] = $this->inventoryContext('INV');

        $adjustmentBatch = MedicineBatch::query()->create([
            'branch_id' => $branch->id,
            'medicine_id' => $medicine->id,
            'supplier_id' => $supplier->id,
            'batch_number' => 'ADJ-BATCH-001',
            'received_at' => now()->subDays(4)->toDateString(),
            'expired_at' => now()->addMonths(9)->toDateString(),
            'quantity_received' => 40,
            'quantity_available' => 40,
            'purchase_cost' => 2200,
            'supplier_name' => $supplier->name,
            'notes' => 'Batch untuk test adjustment.',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->from(route('stock-adjustments'))
            ->post(route('stock-adjustments.store'), [
                'branch_id' => $branch->id,
                'adjustment_type' => 'decrease',
                'adjustment_date' => now()->toDateString(),
                'notes' => 'Adjustment testing',
                'items' => [
                    [
                        'medicine_batch_id' => $adjustmentBatch->id,
                        'quantity_adjusted' => 4,
                        'reason' => 'Rusak',
                        'notes' => 'Adjustment item',
                    ],
                ],
            ])
            ->assertRedirect(route('stock-adjustments'));

        $stockAdjustment = StockAdjustment::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('stock-adjustments.apply', $stockAdjustment))
            ->assertRedirect();

        $adjustmentBatch->refresh();
        $stockAdjustment->refresh();

        $this->assertSame('applied', $stockAdjustment->status);
        $this->assertSame('36.00', $adjustmentBatch->quantity_available);

        $this->actingAs($user)
            ->from(route('stock-opnames'))
            ->post(route('stock-opnames.store'), [
                'branch_id' => $branch->id,
                'opname_date' => now()->toDateString(),
                'notes' => 'Opname testing',
                'items' => [
                    [
                        'medicine_batch_id' => $adjustmentBatch->id,
                        'counted_quantity' => 33,
                        'notes' => 'Counted manually',
                    ],
                ],
            ])
            ->assertRedirect(route('stock-opnames'));

        $stockOpname = StockOpname::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('stock-opnames.finalize', $stockOpname))
            ->assertRedirect();

        $adjustmentBatch->refresh();
        $stockOpname->refresh();

        $this->assertSame('finalized', $stockOpname->status);
        $this->assertSame('33.00', $adjustmentBatch->quantity_available);

        $expiryBatch = MedicineBatch::query()->create([
            'branch_id' => $branch->id,
            'medicine_id' => $medicine->id,
            'supplier_id' => $supplier->id,
            'batch_number' => 'EXP-BATCH-001',
            'received_at' => now()->subDay()->toDateString(),
            'expired_at' => now()->addDays(10)->toDateString(),
            'quantity_received' => 12,
            'quantity_available' => 12,
            'purchase_cost' => 2200,
            'supplier_name' => $supplier->name,
            'notes' => 'Batch untuk test quarantine.',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->from(route('expiry-monitoring'))
            ->post(route('expiry-monitoring.update', $expiryBatch), [
                'action' => 'quarantine',
                'quarantine_reason' => 'Segel rusak',
            ])
            ->assertRedirect(route('expiry-monitoring'));

        $expiryBatch->refresh();

        $this->assertFalse($expiryBatch->is_active);
        $this->assertNotNull($expiryBatch->quarantined_at);

        $this->actingAs($user)
            ->from(route('expiry-monitoring'))
            ->post(route('expiry-monitoring.update', $expiryBatch), [
                'action' => 'release',
            ])
            ->assertRedirect(route('expiry-monitoring'));

        $expiryBatch->refresh();

        $this->assertTrue($expiryBatch->is_active);
        $this->assertNull($expiryBatch->quarantined_at);
    }

    /**
     * @return array{0: User, 1: Branch, 2: Supplier, 3: Medicine}
     */
    private function inventoryContext(string $codePrefix): array
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();

        $supplier = Supplier::query()->create([
            'code' => $codePrefix . '-SUP',
            'name' => $codePrefix . ' Supplier',
            'is_active' => true,
        ]);

        $category = ProductCategory::query()->create([
            'code' => $codePrefix . '-CAT',
            'name' => $codePrefix . ' Category',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $medicine = Medicine::query()->create([
            'code' => $codePrefix . '-MED',
            'product_category_id' => $category->id,
            'name' => $codePrefix . ' Demo Medicine',
            'generic_name' => $codePrefix . ' Demo Generic',
            'dosage_form' => 'tablet',
            'strength' => '500 mg',
            'base_unit' => 'tablet',
            'is_compoundable' => false,
            'is_active' => true,
        ]);

        return [$user, $branch, $supplier, $medicine];
    }
}
