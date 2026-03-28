<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_branch_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('medical_record_no', 30);
            $table->timestamps();

            $table->unique(['patient_id', 'branch_id']);
            $table->unique(['branch_id', 'medical_record_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_branch_records');
    }
};
