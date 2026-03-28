<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicines', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('generic_name')->nullable();
            $table->string('dosage_form');
            $table->string('strength')->nullable();
            $table->string('base_unit', 40)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_compoundable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('medicine_branch_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->decimal('selling_price', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'medicine_id']);
        });

        Schema::create('medicine_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number');
            $table->date('received_at');
            $table->date('expired_at')->nullable();
            $table->decimal('quantity_received', 12, 2);
            $table->decimal('quantity_available', 12, 2);
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->string('supplier_name')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'medicine_id', 'batch_number']);
            $table->index(['branch_id', 'medicine_id', 'expired_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicine_batches');
        Schema::dropIfExists('medicine_branch_prices');
        Schema::dropIfExists('medicines');
    }
};
