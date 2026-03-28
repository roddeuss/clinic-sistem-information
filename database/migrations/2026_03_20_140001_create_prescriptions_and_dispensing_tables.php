<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('visit_registration_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('prescription_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_type');
            $table->string('display_name');
            $table->string('route')->nullable();
            $table->decimal('dose_amount', 10, 2)->nullable();
            $table->string('dose_unit', 30)->nullable();
            $table->string('frequency')->nullable();
            $table->unsignedInteger('duration_days')->nullable();
            $table->text('instruction')->nullable();
            $table->decimal('quantity_prescribed', 12, 2);
            $table->string('dispense_unit', 30)->nullable();
            $table->decimal('weight_snapshot_kg', 10, 2)->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('prescription_compound_ingredients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prescription_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_required', 12, 2);
            $table->string('unit', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('prescription_dispenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prescription_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dispensed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('quantity_dispensed', 12, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamp('dispensed_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('prescription_dispense_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prescription_dispense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_batch_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_used', 12, 2);
            $table->decimal('purchase_cost_snapshot', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_dispense_batches');
        Schema::dropIfExists('prescription_dispenses');
        Schema::dropIfExists('prescription_compound_ingredients');
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
    }
};
