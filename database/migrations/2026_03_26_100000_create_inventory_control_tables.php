<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicine_batches', function (Blueprint $table): void {
            $table->timestamp('quarantined_at')->nullable()->after('is_active');
            $table->foreignId('quarantined_by_user_id')->nullable()->after('quarantined_at')->constrained('users')->nullOnDelete();
            $table->string('quarantine_reason')->nullable()->after('quarantined_by_user_id');
        });

        Schema::create('purchase_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('return_no')->unique();
            $table->string('status', 40)->default('draft');
            $table->date('return_date');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['supplier_id', 'status']);
            $table->index('return_date');
        });

        Schema::create('purchase_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('medicine_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_returned', 12, 2);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['purchase_return_id', 'medicine_batch_id']);
        });

        Schema::create('stock_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('applied_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('adjustment_no')->unique();
            $table->string('adjustment_type', 30);
            $table->string('status', 40)->default('draft');
            $table->date('adjustment_date');
            $table->unsignedInteger('total_items')->default(0);
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['adjustment_type', 'status']);
            $table->index('adjustment_date');
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_before', 12, 2)->default(0);
            $table->decimal('quantity_delta', 12, 2);
            $table->decimal('quantity_after', 12, 2)->default(0);
            $table->decimal('unit_cost_snapshot', 12, 2)->nullable();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['stock_adjustment_id', 'medicine_batch_id']);
        });

        Schema::create('stock_opnames', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('opname_no')->unique();
            $table->string('status', 40)->default('draft');
            $table->date('opname_date');
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index('opname_date');
        });

        Schema::create('stock_opname_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->decimal('system_quantity_snapshot', 12, 2)->default(0);
            $table->decimal('counted_quantity', 12, 2)->default(0);
            $table->decimal('variance_quantity', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['stock_opname_id', 'medicine_batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');

        Schema::table('medicine_batches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('quarantined_by_user_id');
            $table->dropColumn([
                'quarantined_at',
                'quarantine_reason',
            ]);
        });
    }
};
