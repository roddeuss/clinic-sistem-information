<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('leave_date');
            $table->string('leave_type', 20);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['doctor_id', 'leave_date']);
            $table->index(['branch_id', 'leave_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_leaves');
    }
};
