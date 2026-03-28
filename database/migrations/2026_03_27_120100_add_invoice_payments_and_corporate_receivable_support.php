<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('payer_name', 190)->nullable()->after('payer_type');
            $table->json('payer_meta')->nullable()->after('payer_name');
        });

        Schema::create('invoice_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->foreignId('cashier_shift_id')->nullable()->constrained('cashier_shifts')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamp('payment_date');
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn([
                'payer_name',
                'payer_meta',
            ]);
        });
    }
};
