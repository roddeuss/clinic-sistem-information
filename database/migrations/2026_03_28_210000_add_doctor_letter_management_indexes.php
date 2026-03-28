<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctor_letters', function (Blueprint $table): void {
            $table->index(['branch_id', 'status', 'issued_at'], 'doc_letter_branch_status_issued_idx');
            $table->index(['visit_registration_id', 'letter_type', 'status'], 'doc_letter_visit_type_status_idx');
            $table->index(['patient_id', 'status'], 'doc_letter_patient_status_idx');
            $table->index('deleted_at', 'doc_letter_deleted_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('doctor_letters', function (Blueprint $table): void {
            $table->dropIndex('doc_letter_branch_status_issued_idx');
            $table->dropIndex('doc_letter_visit_type_status_idx');
            $table->dropIndex('doc_letter_patient_status_idx');
            $table->dropIndex('doc_letter_deleted_at_idx');
        });
    }
};
