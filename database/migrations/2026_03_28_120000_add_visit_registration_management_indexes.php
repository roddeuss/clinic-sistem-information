<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_registrations', function (Blueprint $table): void {
            $table->index(['branch_id', 'visit_date', 'registration_status'], 'visit_registrations_branch_date_status_index');
            $table->index(['doctor_schedule_id', 'visit_date', 'registration_status'], 'visit_registrations_schedule_date_status_index');
            $table->index(['patient_id', 'visit_date'], 'visit_registrations_patient_date_index');
            $table->index(['counter_id', 'visit_date'], 'visit_registrations_counter_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('visit_registrations', function (Blueprint $table): void {
            $table->dropIndex('visit_registrations_branch_date_status_index');
            $table->dropIndex('visit_registrations_schedule_date_status_index');
            $table->dropIndex('visit_registrations_patient_date_index');
            $table->dropIndex('visit_registrations_counter_date_index');
        });
    }
};
