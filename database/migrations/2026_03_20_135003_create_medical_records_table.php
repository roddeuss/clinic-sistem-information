<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_registration_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
            $table->text('subjective')->nullable();
            $table->text('objective')->nullable();
            $table->text('assessment')->nullable();
            $table->text('plan')->nullable();
            $table->text('diagnosis_notes')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopen_requested_at')->nullable();
            $table->foreignId('reopen_requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reopen_request_reason')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reopen_approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reopen_approval_reason')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'section_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};
