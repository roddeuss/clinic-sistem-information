<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table): void {
            $table->index(['branch_id', 'status'], 'prescriptions_branch_status_index');
            $table->index(['status', 'finalized_at'], 'prescriptions_status_finalized_at_index');
            $table->index(['patient_id', 'created_at'], 'prescriptions_patient_created_at_index');
        });

        Schema::table('prescription_items', function (Blueprint $table): void {
            $table->index(['prescription_id', 'status'], 'prescription_items_prescription_status_index');
            $table->index(['medicine_id', 'status'], 'prescription_items_medicine_status_index');
            $table->index(['prescription_id', 'sort_order'], 'prescription_items_prescription_sort_order_index');
        });

        Schema::table('prescription_compound_ingredients', function (Blueprint $table): void {
            $table->index(
                ['prescription_item_id', 'medicine_id'],
                'prescription_compound_ingredients_item_medicine_index'
            );
        });

        Schema::table('prescription_dispenses', function (Blueprint $table): void {
            $table->index(['branch_id', 'dispensed_at'], 'prescription_dispenses_branch_dispensed_at_index');
            $table->index(
                ['visit_registration_id', 'dispensed_at'],
                'prescription_dispenses_visit_dispensed_at_index'
            );
            $table->index(
                ['prescription_item_id', 'dispensed_at'],
                'prescription_dispenses_item_dispensed_at_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('prescription_dispenses', function (Blueprint $table): void {
            $table->dropIndex('prescription_dispenses_branch_dispensed_at_index');
            $table->dropIndex('prescription_dispenses_visit_dispensed_at_index');
            $table->dropIndex('prescription_dispenses_item_dispensed_at_index');
        });

        Schema::table('prescription_compound_ingredients', function (Blueprint $table): void {
            $table->dropIndex('prescription_compound_ingredients_item_medicine_index');
        });

        Schema::table('prescription_items', function (Blueprint $table): void {
            $table->dropIndex('prescription_items_prescription_status_index');
            $table->dropIndex('prescription_items_medicine_status_index');
            $table->dropIndex('prescription_items_prescription_sort_order_index');
        });

        Schema::table('prescriptions', function (Blueprint $table): void {
            $table->dropIndex('prescriptions_branch_status_index');
            $table->dropIndex('prescriptions_status_finalized_at_index');
            $table->dropIndex('prescriptions_patient_created_at_index');
        });
    }
};
