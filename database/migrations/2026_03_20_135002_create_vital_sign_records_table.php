<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vital_sign_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('systolic_bp');
            $table->unsignedSmallInteger('diastolic_bp');
            $table->decimal('temperature_celsius', 4, 1);
            $table->unsignedSmallInteger('pulse_rate');
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->decimal('weight_kg', 5, 2);
            $table->decimal('height_cm', 5, 2);
            $table->unsignedSmallInteger('spo2_percent');
            $table->decimal('bmi', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->index(['visit_registration_id', 'recorded_at']);
            $table->index(['branch_id', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vital_sign_records');
    }
};
