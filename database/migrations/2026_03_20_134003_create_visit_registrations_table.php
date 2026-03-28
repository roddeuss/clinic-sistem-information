<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_branch_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('counter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('doctor_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->date('visit_date');
            $table->string('visit_type', 30);
            $table->string('registration_status', 30);
            $table->string('booking_code', 30)->nullable()->unique();
            $table->time('slot_start_time')->nullable();
            $table->time('slot_end_time')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['visit_date', 'visit_type']);
            $table->index(['registration_status', 'visit_date']);
            $table->index(['section_id', 'visit_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_registrations');
    }
};
