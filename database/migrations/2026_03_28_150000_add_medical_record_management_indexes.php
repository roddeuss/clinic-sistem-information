<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_records', function (Blueprint $table): void {
            $table->index(['doctor_id', 'status'], 'medical_records_doctor_status_index');
            $table->index(['branch_id', 'status', 'updated_at'], 'medical_records_branch_status_updated_index');
            $table->index(['finalized_by_user_id', 'finalized_at'], 'medical_records_finalized_by_index');
        });

        Schema::table('medical_record_diagnoses', function (Blueprint $table): void {
            $table->index(['icd10_code_id', 'diagnosis_type'], 'medical_record_diagnoses_code_type_index');
        });

        Schema::table('medical_record_audits', function (Blueprint $table): void {
            $table->index(['performed_by_user_id', 'created_at'], 'medical_record_audits_user_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('medical_record_audits', function (Blueprint $table): void {
            $table->dropIndex('medical_record_audits_user_created_index');
        });

        Schema::table('medical_record_diagnoses', function (Blueprint $table): void {
            $table->dropIndex('medical_record_diagnoses_code_type_index');
        });

        Schema::table('medical_records', function (Blueprint $table): void {
            $table->dropIndex('medical_records_doctor_status_index');
            $table->dropIndex('medical_records_branch_status_updated_index');
            $table->dropIndex('medical_records_finalized_by_index');
        });
    }
};
