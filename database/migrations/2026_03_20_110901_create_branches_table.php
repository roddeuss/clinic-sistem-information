<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->time('opening_time')->nullable();
            $table->time('closing_time')->nullable();
            $table->string('queue_prefix', 10)->nullable();
            $table->unsignedTinyInteger('queue_number_padding')->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
