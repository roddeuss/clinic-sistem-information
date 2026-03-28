<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\ProductCategory;
use App\Models\PurchaseReturn;
use App\Models\StockAdjustment;
use App\Models\StockOpname;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class InventoryControlDemoSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::query()->orderBy('id')->first();
        $supplier = Supplier::query()->where('is_active', true)->orderBy('id')->first();
        $user = User::query()->where('email', 'clinicadmin@csi.local')->first()
            ?? User::query()->orderBy('id')->first();
        $medicines = Medicine::query()->where('is_active', true)->orderBy('id')->take(3)->get();

        if (! $branch || ! $supplier || ! $user || $medicines->count() < 3) {
            return;
        }

        if (! ProductCategory::query()->exists()) {
            ProductCategory::query()->create([
                'code' => 'ICD',
                'name' => 'Inventory Control Demo',
                'sort_order' => 90,
                'is_active' => true,
            ]);
        }

        $batches = [
            'return' => $this->upsertBatch(
                $branch->id,
                $supplier->id,
                $medicines[0],
                'CTRL-RET-001',
                90,
                75,
                1250,
                now()->subDays(3)->toDateString(),
                now()->addMonths(10)->toDateString(),
                'Batch demo untuk completed purchase return.',
            ),
            'return_draft' => $this->upsertBatch(
                $branch->id,
                $supplier->id,
                $medicines[0],
                'CTRL-RET-002',
                60,
                60,
                1250,
                now()->subDay()->toDateString(),
                now()->addMonths(7)->toDateString(),
                'Batch demo untuk draft purchase return.',
            ),
            'adjustment' => $this->upsertBatch(
                $branch->id,
                $supplier->id,
                $medicines[1],
                'CTRL-ADJ-001',
                60,
                50,
                2100,
                now()->subDays(4)->toDateString(),
                now()->addMonths(9)->toDateString(),
                'Batch demo untuk stock adjustment applied.',
            ),
            'adjustment_draft' => $this->upsertBatch(
                $branch->id,
                $supplier->id,
                $medicines[1],
                'CTRL-ADJ-002',
                30,
                30,
                2100,
                now()->subDays(2)->toDateString(),
                now()->addMonths(8)->toDateString(),
                'Batch demo untuk stock adjustment draft.',
            ),
            'opname' => $this->upsertBatch(
                $branch->id,
                $supplier->id,
                $medicines[2],
                'CTRL-OPN-001',
                42,
                36,
                3200,
                now()->subDays(6)->toDateString(),
                now()->addMonths(11)->toDateString(),
                'Batch demo untuk stock opname finalized.',
            ),
            'opname_draft' => $this->upsertBatch(
                $branch->id,
                $supplier->id,
                $medicines[2],
                'CTRL-OPN-002',
                18,
                18,
                3200,
                now()->subDay()->toDateString(),
                now()->addMonths(6)->toDateString(),
                'Batch demo untuk stock opname draft.',
            ),
            'near_expiry' => $this->upsertBatch(
                $branch->id,
                $supplier->id,
                $medicines[0],
                'CTRL-EXP-NEAR',
                24,
                24,
                1500,
                now()->subDays(2)->toDateString(),
                now()->addDays(12)->toDateString(),
                'Batch demo near expiry.',
            ),
            'expired' => $this->upsertBatch(
                $branch->id,
                $supplier->id,
                $medicines[1],
                'CTRL-EXP-OLD',
                8,
                8,
                1800,
                now()->subDays(30)->toDateString(),
                now()->subDays(4)->toDateString(),
                'Batch demo expired.',
            ),
            'quarantined' => $this->upsertBatch(
                $branch->id,
                $supplier->id,
                $medicines[2],
                'CTRL-EXP-QTN',
                14,
                14,
                2750,
                now()->subDays(5)->toDateString(),
                now()->addDays(25)->toDateString(),
                'Batch demo quarantined.',
                false,
                $user->id,
                'Kemasan luar rusak saat inspeksi.',
            ),
        ];

        $this->seedPurchaseReturns($branch->id, $supplier->id, $user->id, $batches);
        $this->seedStockAdjustments($branch->id, $user->id, $batches);
        $this->seedStockOpnames($branch->id, $user->id, $batches);
    }

    private function seedPurchaseReturns(int $branchId, int $supplierId, int $userId, array $batches): void
    {
        $completed = PurchaseReturn::query()->updateOrCreate(
            ['return_no' => 'PR-DEMO-0001'],
            [
                'branch_id' => $branchId,
                'supplier_id' => $supplierId,
                'created_by_user_id' => $userId,
                'completed_by_user_id' => $userId,
                'cancelled_by_user_id' => null,
                'status' => 'completed',
                'return_date' => now()->subDay()->toDateString(),
                'total_amount' => 18750,
                'completed_at' => now()->subDay()->setTime(15, 10),
                'cancelled_at' => null,
                'cancel_reason' => null,
                'notes' => 'Demo purchase return yang sudah selesai.',
            ],
        );

        $completed->items()->delete();
        $completed->items()->create([
            'goods_receipt_item_id' => null,
            'medicine_batch_id' => $batches['return']->id,
            'medicine_id' => $batches['return']->medicine_id,
            'quantity_returned' => 15,
            'unit_cost' => 1250,
            'subtotal' => 18750,
            'reason' => 'Kemasan rusak',
            'notes' => 'Return demo completed.',
        ]);

        $draft = PurchaseReturn::query()->updateOrCreate(
            ['return_no' => 'PR-DEMO-0002'],
            [
                'branch_id' => $branchId,
                'supplier_id' => $supplierId,
                'created_by_user_id' => $userId,
                'completed_by_user_id' => null,
                'cancelled_by_user_id' => null,
                'status' => 'draft',
                'return_date' => now()->toDateString(),
                'total_amount' => 7500,
                'completed_at' => null,
                'cancelled_at' => null,
                'cancel_reason' => null,
                'notes' => 'Demo purchase return draft.',
            ],
        );

        $draft->items()->delete();
        $draft->items()->create([
            'goods_receipt_item_id' => null,
            'medicine_batch_id' => $batches['return_draft']->id,
            'medicine_id' => $batches['return_draft']->medicine_id,
            'quantity_returned' => 6,
            'unit_cost' => 1250,
            'subtotal' => 7500,
            'reason' => 'Overstock',
            'notes' => 'Return demo draft.',
        ]);
    }

    private function seedStockAdjustments(int $branchId, int $userId, array $batches): void
    {
        $applied = StockAdjustment::query()->updateOrCreate(
            ['adjustment_no' => 'ADJ-DEMO-0001'],
            [
                'branch_id' => $branchId,
                'created_by_user_id' => $userId,
                'applied_by_user_id' => $userId,
                'cancelled_by_user_id' => null,
                'adjustment_type' => 'decrease',
                'status' => 'applied',
                'adjustment_date' => now()->subDay()->toDateString(),
                'total_items' => 1,
                'applied_at' => now()->subDay()->setTime(16, 0),
                'cancelled_at' => null,
                'cancel_reason' => null,
                'notes' => 'Demo adjustment applied karena selisih stok.',
            ],
        );

        $applied->items()->delete();
        $applied->items()->create([
            'medicine_batch_id' => $batches['adjustment']->id,
            'medicine_id' => $batches['adjustment']->medicine_id,
            'quantity_before' => 60,
            'quantity_delta' => -10,
            'quantity_after' => 50,
            'unit_cost_snapshot' => 2100,
            'reason' => 'Barang pecah',
            'notes' => 'Adjustment demo applied.',
        ]);

        $draft = StockAdjustment::query()->updateOrCreate(
            ['adjustment_no' => 'ADJ-DEMO-0002'],
            [
                'branch_id' => $branchId,
                'created_by_user_id' => $userId,
                'applied_by_user_id' => null,
                'cancelled_by_user_id' => null,
                'adjustment_type' => 'increase',
                'status' => 'draft',
                'adjustment_date' => now()->toDateString(),
                'total_items' => 1,
                'applied_at' => null,
                'cancelled_at' => null,
                'cancel_reason' => null,
                'notes' => 'Demo adjustment draft untuk koreksi masuk.',
            ],
        );

        $draft->items()->delete();
        $draft->items()->create([
            'medicine_batch_id' => $batches['adjustment_draft']->id,
            'medicine_id' => $batches['adjustment_draft']->medicine_id,
            'quantity_before' => 0,
            'quantity_delta' => 4,
            'quantity_after' => 0,
            'unit_cost_snapshot' => 2100,
            'reason' => 'Temuan item tertinggal',
            'notes' => 'Adjustment demo draft.',
        ]);
    }

    private function seedStockOpnames(int $branchId, int $userId, array $batches): void
    {
        $finalized = StockOpname::query()->updateOrCreate(
            ['opname_no' => 'OPN-DEMO-0001'],
            [
                'branch_id' => $branchId,
                'created_by_user_id' => $userId,
                'finalized_by_user_id' => $userId,
                'cancelled_by_user_id' => null,
                'status' => 'finalized',
                'opname_date' => now()->subDay()->toDateString(),
                'finalized_at' => now()->subDay()->setTime(17, 0),
                'cancelled_at' => null,
                'cancel_reason' => null,
                'notes' => 'Demo stock opname finalized.',
            ],
        );

        $finalized->items()->delete();
        $finalized->items()->create([
            'medicine_batch_id' => $batches['opname']->id,
            'medicine_id' => $batches['opname']->medicine_id,
            'system_quantity_snapshot' => 42,
            'counted_quantity' => 36,
            'variance_quantity' => -6,
            'notes' => 'Opname demo finalized.',
        ]);

        $draft = StockOpname::query()->updateOrCreate(
            ['opname_no' => 'OPN-DEMO-0002'],
            [
                'branch_id' => $branchId,
                'created_by_user_id' => $userId,
                'finalized_by_user_id' => null,
                'cancelled_by_user_id' => null,
                'status' => 'draft',
                'opname_date' => now()->toDateString(),
                'finalized_at' => null,
                'cancelled_at' => null,
                'cancel_reason' => null,
                'notes' => 'Demo stock opname draft.',
            ],
        );

        $draft->items()->delete();
        $draft->items()->create([
            'medicine_batch_id' => $batches['opname_draft']->id,
            'medicine_id' => $batches['opname_draft']->medicine_id,
            'system_quantity_snapshot' => 18,
            'counted_quantity' => 16,
            'variance_quantity' => -2,
            'notes' => 'Opname demo draft.',
        ]);
    }

    private function upsertBatch(
        int $branchId,
        int $supplierId,
        Medicine $medicine,
        string $batchNumber,
        float $quantityReceived,
        float $quantityAvailable,
        float $purchaseCost,
        string $receivedAt,
        string $expiredAt,
        string $notes,
        bool $isActive = true,
        ?int $quarantinedByUserId = null,
        ?string $quarantineReason = null,
    ): MedicineBatch {
        return MedicineBatch::query()->updateOrCreate(
            ['batch_number' => $batchNumber],
            [
                'branch_id' => $branchId,
                'medicine_id' => $medicine->id,
                'supplier_id' => $supplierId,
                'received_at' => $receivedAt,
                'expired_at' => $expiredAt,
                'quantity_received' => $quantityReceived,
                'quantity_available' => $quantityAvailable,
                'purchase_cost' => $purchaseCost,
                'supplier_name' => Supplier::query()->find($supplierId)?->name,
                'notes' => $notes,
                'is_active' => $isActive,
                'quarantined_at' => $quarantinedByUserId ? now()->subDay() : null,
                'quarantined_by_user_id' => $quarantinedByUserId,
                'quarantine_reason' => $quarantineReason,
            ],
        );
    }
}
