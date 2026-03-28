<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table): void {
            $table->foreignId('product_category_id')
                ->nullable()
                ->after('code')
                ->constrained('product_categories')
                ->nullOnDelete();
        });

        Schema::table('medicine_batches', function (Blueprint $table): void {
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('medicine_id')
                ->constrained('suppliers')
                ->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('payment_method_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('payment_methods')
                ->nullOnDelete();
            $table->foreignId('cashier_shift_id')
                ->nullable()
                ->after('payment_method_id')
                ->constrained('cashier_shifts')
                ->nullOnDelete();
            $table->string('payment_reference')->nullable()->after('paid_by_user_id');
            $table->foreignId('voided_by_user_id')
                ->nullable()
                ->after('paid_by_user_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('voided_at')->nullable()->after('paid_at');
            $table->text('void_reason')->nullable()->after('voided_at');
            $table->timestamp('printed_at')->nullable()->after('void_reason');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_method_id');
            $table->dropConstrainedForeignId('cashier_shift_id');
            $table->dropConstrainedForeignId('voided_by_user_id');
            $table->dropColumn([
                'payment_reference',
                'voided_at',
                'void_reason',
                'printed_at',
            ]);
        });

        Schema::table('medicine_batches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_id');
        });

        Schema::table('medicines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_category_id');
        });
    }
};
