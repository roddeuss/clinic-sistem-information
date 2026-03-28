<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('po_no')->unique();
            $table->string('status', 40)->default('draft');
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['supplier_id', 'status']);
            $table->index(['order_date']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_ordered', 12, 2);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['purchase_order_id', 'medicine_id']);
        });

        Schema::create('goods_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receipt_no')->unique();
            $table->string('status', 40)->default('received');
            $table->date('received_at');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['purchase_order_id', 'status']);
            $table->index(['branch_id', 'received_at']);
        });

        Schema::create('goods_receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_number');
            $table->date('expired_at')->nullable();
            $table->decimal('quantity_received', 12, 2);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['goods_receipt_id', 'purchase_order_item_id', 'batch_number'], 'gr_items_receipt_item_batch_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
