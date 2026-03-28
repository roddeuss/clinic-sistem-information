<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table): void {
            $table->index(['branch_id', 'queue_date', 'status'], 'queue_tickets_branch_date_status_index');
            $table->index(['branch_id', 'section_id', 'queue_date', 'status', 'queue_number'], 'queue_tickets_branch_section_date_status_number_index');
            $table->index(['branch_id', 'queue_code'], 'queue_tickets_branch_queue_code_index');
            $table->index(['doctor_id', 'queue_date'], 'queue_tickets_doctor_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table): void {
            $table->dropIndex('queue_tickets_branch_date_status_index');
            $table->dropIndex('queue_tickets_branch_section_date_status_number_index');
            $table->dropIndex('queue_tickets_branch_queue_code_index');
            $table->dropIndex('queue_tickets_doctor_date_index');
        });
    }
};
