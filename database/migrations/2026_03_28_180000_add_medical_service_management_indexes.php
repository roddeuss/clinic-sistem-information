<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_services', function (Blueprint $table): void {
            $table->index(['is_active', 'name'], 'medical_services_active_name_index');
            $table->index('created_at', 'medical_services_created_at_index');
        });

        Schema::table('medical_service_branch_prices', function (Blueprint $table): void {
            $table->index(
                ['medical_service_id', 'is_active'],
                'medical_service_branch_prices_service_active_index',
            );
        });

        Schema::table('visit_medical_services', function (Blueprint $table): void {
            $table->index(
                ['branch_id', 'status', 'ordered_at'],
                'visit_medical_services_branch_status_ordered_index',
            );
            $table->index(
                ['visit_registration_id', 'status'],
                'visit_medical_services_visit_status_index',
            );
            $table->index(
                ['medical_service_id', 'status'],
                'visit_medical_services_service_status_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('visit_medical_services', function (Blueprint $table): void {
            $table->dropIndex('visit_medical_services_branch_status_ordered_index');
            $table->dropIndex('visit_medical_services_visit_status_index');
            $table->dropIndex('visit_medical_services_service_status_index');
        });

        Schema::table('medical_service_branch_prices', function (Blueprint $table): void {
            $table->dropIndex('medical_service_branch_prices_service_active_index');
        });

        Schema::table('medical_services', function (Blueprint $table): void {
            $table->dropIndex('medical_services_active_name_index');
            $table->dropIndex('medical_services_created_at_index');
        });
    }
};
