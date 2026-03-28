<?php

namespace Tests\Feature\Procurement;

use App\Models\Branch;
use App\Models\GoodsReceipt;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementManagementTest extends TestCase
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

    public function test_clinic_admin_can_open_procurement_pages(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $this->actingAs($user)->get(route('purchase-orders'))->assertOk()->assertSee('Purchase Orders');
        $this->actingAs($user)->get(route('goods-receipts'))->assertOk()->assertSee('Goods Receipts');
    }

    public function test_clinic_admin_can_create_purchase_order_and_goods_receipt(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        $supplier = Supplier::query()->create([
            'code' => 'SUP-TEST',
            'name' => 'Supplier Test',
            'is_active' => true,
        ]);
        $category = ProductCategory::query()->create([
            'code' => 'TEST',
            'name' => 'Test Category',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $medicine = Medicine::query()->create([
            'code' => 'PO001',
            'product_category_id' => $category->id,
            'name' => 'Procurement Demo Medicine',
            'dosage_form' => 'tablet',
            'strength' => '500 mg',
            'base_unit' => 'tablet',
            'is_compoundable' => false,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->from(route('purchase-orders'))
            ->post(route('purchase-orders.store'), [
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'order_date' => now()->toDateString(),
                'expected_date' => now()->addDays(3)->toDateString(),
                'notes' => 'Test PO',
                'items' => [
                    [
                        'medicine_id' => $medicine->id,
                        'quantity_ordered' => 100,
                        'unit_cost' => 1500,
                        'notes' => 'Line item',
                    ],
                ],
            ])
            ->assertRedirect(route('purchase-orders'));

        $purchaseOrder = PurchaseOrder::query()->with('items')->firstOrFail();

        $this->assertSame('draft', $purchaseOrder->status);
        $this->assertSame('150000.00', $purchaseOrder->total_amount);

        $this->actingAs($user)
            ->post(route('purchase-orders.submit', $purchaseOrder))
            ->assertRedirect();

        $purchaseOrder->refresh();

        $this->assertSame('ordered', $purchaseOrder->status);

        $purchaseOrderItem = $purchaseOrder->items()->firstOrFail();

        $this->actingAs($user)
            ->from(route('goods-receipts'))
            ->post(route('goods-receipts.store'), [
                'purchase_order_id' => $purchaseOrder->id,
                'received_at' => now()->toDateString(),
                'notes' => 'Partial receipt',
                'items' => [
                    [
                        'purchase_order_item_id' => $purchaseOrderItem->id,
                        'batch_number' => 'PO001-A',
                        'expired_at' => now()->addMonths(12)->toDateString(),
                        'quantity_received' => 40,
                        'unit_cost' => 1500,
                        'notes' => 'Received batch',
                    ],
                ],
            ])
            ->assertRedirect(route('goods-receipts'));

        $goodsReceipt = GoodsReceipt::query()->with('items')->firstOrFail();
        $batch = MedicineBatch::query()->where('batch_number', 'PO001-A')->firstOrFail();

        $purchaseOrder->refresh();

        $this->assertSame('received', $goodsReceipt->status);
        $this->assertSame('partial_received', $purchaseOrder->status);
        $this->assertSame($branch->id, $batch->branch_id);
        $this->assertSame($medicine->id, $batch->medicine_id);
        $this->assertSame('40.00', $batch->quantity_available);
    }

    public function test_high_value_purchase_order_requires_approval_before_receipt(): void
    {
        $clinicAdmin = User::factory()->create();
        $clinicAdmin->assignRole('clinic-admin');

        $pharmacist = User::factory()->create();
        $pharmacist->assignRole('pharmacist');

        $branch = Branch::query()->firstOrFail();
        $supplier = Supplier::query()->create([
            'code' => 'SUP-APP',
            'name' => 'Supplier Approval',
            'is_active' => true,
        ]);
        $category = ProductCategory::query()->create([
            'code' => 'APP',
            'name' => 'Approval Category',
            'sort_order' => 20,
            'is_active' => true,
        ]);
        $medicine = Medicine::query()->create([
            'code' => 'PO002',
            'product_category_id' => $category->id,
            'name' => 'High Value Medicine',
            'dosage_form' => 'capsule',
            'strength' => '250 mg',
            'base_unit' => 'capsule',
            'is_compoundable' => false,
            'is_active' => true,
        ]);

        $this->actingAs($pharmacist)
            ->post(route('purchase-orders.store'), [
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'order_date' => now()->toDateString(),
                'expected_date' => now()->addDays(5)->toDateString(),
                'notes' => 'Need approval',
                'items' => [
                    [
                        'medicine_id' => $medicine->id,
                        'quantity_ordered' => 1000,
                        'unit_cost' => 6000,
                        'notes' => 'High value line',
                    ],
                ],
            ])
            ->assertRedirect();

        $purchaseOrder = PurchaseOrder::query()->with('items')->latest('id')->firstOrFail();

        $this->actingAs($pharmacist)
            ->post(route('purchase-orders.submit', $purchaseOrder))
            ->assertRedirect();

        $purchaseOrder->refresh();

        $this->assertSame('submitted', $purchaseOrder->status);
        $this->assertNull($purchaseOrder->approved_at);

        $this->actingAs($clinicAdmin)
            ->post(route('purchase-orders.approve', $purchaseOrder), [
                'approval_notes' => 'Budget approved',
            ])
            ->assertRedirect();

        $purchaseOrder->refresh();

        $this->assertSame('ordered', $purchaseOrder->status);
        $this->assertNotNull($purchaseOrder->approved_at);
        $this->assertSame('Budget approved', $purchaseOrder->approval_notes);
    }

    public function test_purchase_order_and_goods_receipt_support_uom_conversion_to_base_stock(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        $supplier = Supplier::query()->create([
            'code' => 'SUP-UOM',
            'name' => 'Supplier Conversion',
            'is_active' => true,
        ]);
        $category = ProductCategory::query()->create([
            'code' => 'UOM',
            'name' => 'UOM Category',
            'sort_order' => 30,
            'is_active' => true,
        ]);
        $medicine = Medicine::query()->create([
            'code' => 'BOXTAB',
            'product_category_id' => $category->id,
            'name' => 'Box Tablet Demo',
            'dosage_form' => 'tablet',
            'strength' => '500 mg',
            'base_unit' => 'tablet',
            'is_compoundable' => false,
            'is_active' => true,
        ]);

        $medicine->units()->updateOrCreate(
            ['label' => 'BOX'],
            [
                'conversion_factor' => 100,
                'allow_purchase' => true,
                'allow_dispense' => false,
                'is_base' => false,
                'sort_order' => 20,
                'is_active' => true,
            ],
        );

        $boxUnit = $medicine->units()->where('label', 'BOX')->firstOrFail();

        $this->actingAs($user)
            ->from(route('purchase-orders'))
            ->post(route('purchase-orders.store'), [
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'order_date' => now()->toDateString(),
                'expected_date' => now()->addDays(2)->toDateString(),
                'notes' => 'PO in box unit',
                'items' => [
                    [
                        'medicine_id' => $medicine->id,
                        'medicine_unit_id' => $boxUnit->id,
                        'quantity_ordered' => 2,
                        'unit_cost' => 50000,
                        'notes' => '2 boxes',
                    ],
                ],
            ])
            ->assertRedirect(route('purchase-orders'));

        $purchaseOrder = PurchaseOrder::query()->with('items')->latest('id')->firstOrFail();
        $purchaseOrderItem = $purchaseOrder->items()->firstOrFail();

        $this->assertSame('2.00', $purchaseOrderItem->quantity_ordered);
        $this->assertSame('200.00', $purchaseOrderItem->quantity_ordered_base);
        $this->assertSame('50000.00', $purchaseOrderItem->unit_cost);
        $this->assertSame('500.0000', $purchaseOrderItem->unit_cost_base);

        $this->actingAs($user)
            ->post(route('purchase-orders.submit', $purchaseOrder))
            ->assertRedirect();

        $this->actingAs($user)
            ->from(route('goods-receipts'))
            ->post(route('goods-receipts.store'), [
                'purchase_order_id' => $purchaseOrder->id,
                'received_at' => now()->toDateString(),
                'notes' => 'Receive 1 box',
                'items' => [
                    [
                        'purchase_order_item_id' => $purchaseOrderItem->id,
                        'batch_number' => 'BOXTAB-A1',
                        'expired_at' => now()->addMonths(18)->toDateString(),
                        'quantity_received' => 1,
                        'unit_cost' => 50000,
                        'notes' => '1 box received',
                    ],
                ],
            ])
            ->assertRedirect(route('goods-receipts'));

        $goodsReceipt = GoodsReceipt::query()->with('items')->latest('id')->firstOrFail();
        $goodsReceiptItem = $goodsReceipt->items()->firstOrFail();
        $batch = MedicineBatch::query()->where('batch_number', 'BOXTAB-A1')->firstOrFail();

        $this->assertSame('1.00', $goodsReceiptItem->quantity_received);
        $this->assertSame('100.00', $goodsReceiptItem->quantity_received_base);
        $this->assertSame('100.00', $batch->quantity_available);
        $this->assertSame('500.00', $batch->purchase_cost);
    }
}
