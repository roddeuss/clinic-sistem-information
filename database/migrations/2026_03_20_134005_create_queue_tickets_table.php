<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('visit_registration_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_branch_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('counter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('called_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('queue_date');
            $table->unsignedInteger('queue_number');
            $table->string('queue_code', 30);
            $table->string('status', 30);
            $table->timestamp('called_at')->nullable();
            $table->timestamp('serving_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['section_id', 'queue_date', 'queue_number']);
            $table->index(['queue_date', 'status']);
            $table->index(['section_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_tickets');
    }
};
