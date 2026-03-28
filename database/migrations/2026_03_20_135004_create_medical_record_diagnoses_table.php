<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_record_diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('icd10_code_id')->constrained()->cascadeOnDelete();
            $table->string('diagnosis_type', 20)->default('secondary');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['medical_record_id', 'icd10_code_id', 'diagnosis_type'], 'medical_record_diagnosis_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_record_diagnoses');
    }
};
