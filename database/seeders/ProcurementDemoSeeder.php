<?php

namespace Database\Seeders;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Branch;
use Illuminate\Database\Seeder;

class ProcurementDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (PurchaseOrder::query()->exists()) {
            return;
        }

        $branch = Branch::query()->orderBy('id')->first();
        $supplier = Supplier::query()->where('is_active', true)->orderBy('id')->first();
        $user = User::query()->where('email', 'clinicadmin@csi.local')->first()
            ?? User::query()->orderBy('id')->first();
        $medicines = Medicine::query()->where('is_active', true)->orderBy('id')->take(3)->get();

        if (! $branch || ! $supplier || $medicines->count() < 2) {
            return;
        }

        $purchaseOrder = PurchaseOrder::query()->create([
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'created_by_user_id' => $user?->id,
            'submitted_by_user_id' => $user?->id,
            'po_no' => 'PO-DEMO-0001',
            'status' => 'partial_received',
            'order_date' => now()->subDays(2)->toDateString(),
            'expected_date' => now()->addDays(3)->toDateString(),
            'total_amount' => 0,
            'submitted_at' => now()->subDays(2),
            'notes' => 'Seed purchase order for procurement dashboard preview.',
        ]);

        $itemOne = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'medicine_id' => $medicines[0]->id,
            'quantity_ordered' => 100,
            'unit_cost' => 1250,
            'subtotal' => 125000,
            'notes' => 'First line item demo.',
        ]);

        $itemTwo = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'medicine_id' => $medicines[1]->id,
            'quantity_ordered' => 60,
            'unit_cost' => 2750,
            'subtotal' => 165000,
            'notes' => 'Second line item demo.',
        ]);

        $purchaseOrder->update([
            'total_amount' => 290000,
        ]);

        $goodsReceipt = GoodsReceipt::query()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'received_by_user_id' => $user?->id,
            'receipt_no' => 'GR-DEMO-0001',
            'status' => 'received',
            'received_at' => now()->subDay()->toDateString(),
            'total_amount' => 187500,
            'notes' => 'Seed goods receipt with partial quantity.',
        ]);

        $batchOne = MedicineBatch::query()->create([
            'branch_id' => $branch->id,
            'medicine_id' => $medicines[0]->id,
            'supplier_id' => $supplier->id,
            'batch_number' => 'DEMO-A-001',
            'received_at' => now()->subDay()->toDateString(),
            'expired_at' => now()->addYear()->toDateString(),
            'quantity_received' => 70,
            'quantity_available' => 70,
            'purchase_cost' => 1250,
            'supplier_name' => $supplier->name,
            'notes' => 'Seed batch from demo goods receipt.',
            'is_active' => true,
        ]);

        GoodsReceiptItem::query()->create([
            'goods_receipt_id' => $goodsReceipt->id,
            'purchase_order_item_id' => $itemOne->id,
            'medicine_id' => $medicines[0]->id,
            'medicine_batch_id' => $batchOne->id,
            'batch_number' => 'DEMO-A-001',
            'expired_at' => now()->addYear()->toDateString(),
            'quantity_received' => 70,
            'unit_cost' => 1250,
            'subtotal' => 87500,
            'notes' => 'Partial receipt demo.',
        ]);

        $batchTwo = MedicineBatch::query()->create([
            'branch_id' => $branch->id,
            'medicine_id' => $medicines[1]->id,
            'supplier_id' => $supplier->id,
            'batch_number' => 'DEMO-B-001',
            'received_at' => now()->subDay()->toDateString(),
            'expired_at' => now()->addMonths(10)->toDateString(),
            'quantity_received' => 40,
            'quantity_available' => 40,
            'purchase_cost' => 2500,
            'supplier_name' => $supplier->name,
            'notes' => 'Seed batch from demo goods receipt.',
            'is_active' => true,
        ]);

        GoodsReceiptItem::query()->create([
            'goods_receipt_id' => $goodsReceipt->id,
            'purchase_order_item_id' => $itemTwo->id,
            'medicine_id' => $medicines[1]->id,
            'medicine_batch_id' => $batchTwo->id,
            'batch_number' => 'DEMO-B-001',
            'expired_at' => now()->addMonths(10)->toDateString(),
            'quantity_received' => 40,
            'unit_cost' => 2500,
            'subtotal' => 100000,
            'notes' => 'Partial receipt demo.',
        ]);
    }
}
