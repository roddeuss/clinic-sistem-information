<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_destinations', function (Blueprint $table): void {
            $table->index(['destination_type', 'is_active'], 'ref_dest_type_active_idx');
            $table->index(['is_active', 'name'], 'ref_dest_active_name_idx');
            $table->index('created_at', 'ref_dest_created_at_idx');
        });

        Schema::table('patient_referrals', function (Blueprint $table): void {
            $table->index(['branch_id', 'status', 'issued_at'], 'pat_ref_branch_status_issued_idx');
            $table->index(['visit_registration_id', 'status'], 'pat_ref_visit_status_idx');
            $table->index(['referral_destination_id', 'status'], 'pat_ref_dest_status_idx');
            $table->index(['patient_id', 'status'], 'pat_ref_patient_status_idx');
            $table->index('deleted_at', 'pat_ref_deleted_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('patient_referrals', function (Blueprint $table): void {
            $table->dropIndex('pat_ref_branch_status_issued_idx');
            $table->dropIndex('pat_ref_visit_status_idx');
            $table->dropIndex('pat_ref_dest_status_idx');
            $table->dropIndex('pat_ref_patient_status_idx');
            $table->dropIndex('pat_ref_deleted_at_idx');
        });

        Schema::table('referral_destinations', function (Blueprint $table): void {
            $table->dropIndex('ref_dest_type_active_idx');
            $table->dropIndex('ref_dest_active_name_idx');
            $table->dropIndex('ref_dest_created_at_idx');
        });
    }
};
