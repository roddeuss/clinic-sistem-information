<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_destinations', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('destination_type', 40);
            $table->string('name', 160);
            $table->text('address')->nullable();
            $table->string('contact_person', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('patient_referrals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('visit_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_branch_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('referral_destination_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reissued_from_id')->nullable()->constrained('patient_referrals')->nullOnDelete();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('referral_no', 80)->nullable()->unique();
            $table->string('status', 40)->default('draft');
            $table->string('destination_type', 40)->nullable();
            $table->string('destination_name', 160)->nullable();
            $table->text('destination_address')->nullable();
            $table->string('destination_phone', 40)->nullable();
            $table->text('diagnosis_summary')->nullable();
            $table->text('clinical_summary')->nullable();
            $table->text('treatment_summary')->nullable();
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('doctor_name_snapshot', 190)->nullable();
            $table->string('doctor_specialization_snapshot', 120)->nullable();
            $table->string('doctor_signature_path_snapshot')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('doctor_letters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('visit_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_branch_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reissued_from_id')->nullable()->constrained('doctor_letters')->nullOnDelete();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('letter_no', 80)->nullable()->unique();
            $table->string('letter_type', 40);
            $table->string('status', 40)->default('draft');
            $table->date('issue_date')->nullable();
            $table->text('diagnosis_summary')->nullable();
            $table->text('notes')->nullable();
            $table->date('sick_start_date')->nullable();
            $table->date('sick_end_date')->nullable();
            $table->unsignedSmallInteger('sick_total_days')->nullable();
            $table->text('healthy_statement')->nullable();
            $table->date('control_date')->nullable();
            $table->text('control_notes')->nullable();
            $table->string('doctor_name_snapshot', 190)->nullable();
            $table->string('doctor_specialization_snapshot', 120)->nullable();
            $table->string('doctor_signature_path_snapshot')->nullable();
            $table->string('sip_number_snapshot', 60)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_letters');
        Schema::dropIfExists('patient_referrals');
        Schema::dropIfExists('referral_destinations');
    }
};
