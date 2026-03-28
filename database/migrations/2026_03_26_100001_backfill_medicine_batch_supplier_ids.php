<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $batchSupplierRows = DB::table('goods_receipt_items')
            ->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_items.goods_receipt_id')
            ->whereNotNull('goods_receipt_items.medicine_batch_id')
            ->select([
                'goods_receipt_items.medicine_batch_id as batch_id',
                'goods_receipts.supplier_id',
            ])
            ->get();

        foreach ($batchSupplierRows as $row) {
            DB::table('medicine_batches')
                ->where('id', $row->batch_id)
                ->whereNull('supplier_id')
                ->update([
                    'supplier_id' => $row->supplier_id,
                ]);
        }

        $namedRows = DB::table('medicine_batches')
            ->join('suppliers', 'suppliers.name', '=', 'medicine_batches.supplier_name')
            ->whereNull('medicine_batches.supplier_id')
            ->select([
                'medicine_batches.id as batch_id',
                'suppliers.id as supplier_id',
            ])
            ->get();

        foreach ($namedRows as $row) {
            DB::table('medicine_batches')
                ->where('id', $row->batch_id)
                ->whereNull('supplier_id')
                ->update([
                    'supplier_id' => $row->supplier_id,
                ]);
        }
    }

    public function down(): void
    {
        // This migration only backfills missing supplier references.
    }
};
