<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('slot_duration_minutes')->default(15);
            $table->unsignedSmallInteger('max_patients')->default(20);
            $table->string('notes', 1000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['doctor_id', 'branch_id', 'section_id', 'day_of_week', 'start_time'], 'doctor_schedule_unique_slot');
            $table->index(['doctor_id', 'day_of_week', 'is_active']);
            $table->index(['branch_id', 'section_id', 'day_of_week'], 'doctor_schedule_branch_section_day_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_schedules');
    }
};
