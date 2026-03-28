<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedure_masters', function (Blueprint $table): void {
            $table->index(['is_active', 'name'], 'procedure_masters_is_active_name_index');
            $table->index('created_at', 'procedure_masters_created_at_index');
        });

        Schema::table('procedure_branch_prices', function (Blueprint $table): void {
            $table->index(['procedure_master_id', 'is_active'], 'procedure_branch_prices_master_active_index');
        });

        Schema::table('visit_procedures', function (Blueprint $table): void {
            $table->index(['branch_id', 'status', 'ordered_at'], 'visit_procedures_branch_status_ordered_at_index');
            $table->index(['visit_registration_id', 'status'], 'visit_procedures_visit_status_index');
            $table->index(['procedure_master_id', 'status'], 'visit_procedures_master_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('visit_procedures', function (Blueprint $table): void {
            $table->dropIndex('visit_procedures_branch_status_ordered_at_index');
            $table->dropIndex('visit_procedures_visit_status_index');
            $table->dropIndex('visit_procedures_master_status_index');
        });

        Schema::table('procedure_branch_prices', function (Blueprint $table): void {
            $table->dropIndex('procedure_branch_prices_master_active_index');
        });

        Schema::table('procedure_masters', function (Blueprint $table): void {
            $table->dropIndex('procedure_masters_is_active_name_index');
            $table->dropIndex('procedure_masters_created_at_index');
        });
    }
};
