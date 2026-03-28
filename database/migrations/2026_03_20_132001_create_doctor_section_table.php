<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_section', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['doctor_id', 'section_id']);
            $table->index(['section_id', 'doctor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_section');
    }
};
