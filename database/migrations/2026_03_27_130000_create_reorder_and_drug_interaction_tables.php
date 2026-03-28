<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicine_reorder_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('preferred_purchase_unit_id')->nullable()->constrained('medicine_units')->nullOnDelete();
            $table->decimal('minimum_stock', 12, 2)->default(0);
            $table->decimal('safety_stock', 12, 2)->default(0);
            $table->decimal('reorder_point', 12, 2)->default(0);
            $table->decimal('reorder_quantity', 12, 2)->default(0);
            $table->unsignedInteger('lead_time_days')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['medicine_id', 'branch_id'], 'medicine_reorder_policies_medicine_branch_unique');
        });

        Schema::create('medicine_reorder_policy_suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('medicine_reorder_policy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('priority')->default(10);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['medicine_reorder_policy_id', 'supplier_id'],
                'medicine_reorder_policy_suppliers_policy_supplier_unique'
            );
        });

        Schema::create('drug_interaction_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('left_operand_type', 30);
            $table->string('left_operand_value', 160);
            $table->string('right_operand_type', 30);
            $table->string('right_operand_value', 160);
            $table->string('severity', 30);
            $table->string('title', 180);
            $table->text('clinical_effect')->nullable();
            $table->text('management_advice')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('prescription_interaction_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prescription_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('drug_interaction_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('interaction_key', 120);
            $table->string('interaction_title', 180);
            $table->string('severity', 30);
            $table->text('reason');
            $table->foreignId('overridden_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('overridden_at');
            $table->timestamps();

            $table->unique(['prescription_id', 'interaction_key'], 'prescription_interaction_overrides_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_interaction_overrides');
        Schema::dropIfExists('drug_interaction_rules');
        Schema::dropIfExists('medicine_reorder_policy_suppliers');
        Schema::dropIfExists('medicine_reorder_policies');
    }
};
