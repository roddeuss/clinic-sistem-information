<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laboratory_tests', function (Blueprint $table): void {
            $table->index(['is_active', 'name'], 'laboratory_tests_active_name_index');
            $table->index(['diagnostic_category', 'is_active'], 'laboratory_tests_category_active_index');
            $table->index('created_at', 'laboratory_tests_created_at_index');
        });

        Schema::table('laboratory_test_parameters', function (Blueprint $table): void {
            $table->index(['laboratory_test_id', 'sort_order'], 'laboratory_test_parameters_test_sort_index');
        });

        Schema::table('laboratory_test_branch_prices', function (Blueprint $table): void {
            $table->index(['laboratory_test_id', 'is_active'], 'laboratory_test_branch_prices_test_active_index');
        });

        Schema::table('laboratory_orders', function (Blueprint $table): void {
            $table->index(['branch_id', 'status', 'ordered_at'], 'laboratory_orders_branch_status_ordered_index');
            $table->index(['visit_registration_id', 'status'], 'laboratory_orders_visit_status_index');
            $table->index(['laboratory_test_id', 'status'], 'laboratory_orders_test_status_index');
            $table->index(['provider_type', 'status'], 'laboratory_orders_provider_status_index');
        });

        Schema::table('laboratory_result_entries', function (Blueprint $table): void {
            $table->index(['laboratory_order_id', 'sort_order'], 'laboratory_result_entries_order_sort_index');
        });
    }

    public function down(): void
    {
        Schema::table('laboratory_result_entries', function (Blueprint $table): void {
            $table->dropIndex('laboratory_result_entries_order_sort_index');
        });

        Schema::table('laboratory_orders', function (Blueprint $table): void {
            $table->dropIndex('laboratory_orders_branch_status_ordered_index');
            $table->dropIndex('laboratory_orders_visit_status_index');
            $table->dropIndex('laboratory_orders_test_status_index');
            $table->dropIndex('laboratory_orders_provider_status_index');
        });

        Schema::table('laboratory_test_branch_prices', function (Blueprint $table): void {
            $table->dropIndex('laboratory_test_branch_prices_test_active_index');
        });

        Schema::table('laboratory_test_parameters', function (Blueprint $table): void {
            $table->dropIndex('laboratory_test_parameters_test_sort_index');
        });

        Schema::table('laboratory_tests', function (Blueprint $table): void {
            $table->dropIndex('laboratory_tests_active_name_index');
            $table->dropIndex('laboratory_tests_category_active_index');
            $table->dropIndex('laboratory_tests_created_at_index');
        });
    }
};
