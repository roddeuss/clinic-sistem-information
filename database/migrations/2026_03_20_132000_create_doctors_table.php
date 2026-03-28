<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 160);
            $table->string('title_prefix', 30)->nullable();
            $table->string('title_suffix', 60)->nullable();
            $table->string('specialization', 120);
            $table->decimal('consultation_fee', 12, 2)->default(0);
            $table->string('str_number', 60)->nullable()->unique();
            $table->date('str_expired_at')->nullable();
            $table->string('sip_number', 60)->nullable()->unique();
            $table->date('sip_expired_at')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 120)->nullable()->unique();
            $table->string('address', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'full_name']);
            $table->index(['specialization', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
