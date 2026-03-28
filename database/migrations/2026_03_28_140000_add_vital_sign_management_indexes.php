<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vital_sign_records', function (Blueprint $table): void {
            $table->index(['branch_id', 'recorded_at'], 'vital_sign_records_branch_recorded_at_index');
            $table->index(['patient_id', 'recorded_at'], 'vital_sign_records_patient_recorded_at_index');
            $table->index(['section_id', 'recorded_at'], 'vital_sign_records_section_recorded_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('vital_sign_records', function (Blueprint $table): void {
            $table->dropIndex('vital_sign_records_branch_recorded_at_index');
            $table->dropIndex('vital_sign_records_patient_recorded_at_index');
            $table->dropIndex('vital_sign_records_section_recorded_at_index');
        });
    }
};
