<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('code', 60);
            $table->string('type', 20)->default('regular');
            $table->string('queue_prefix', 10);
            $table->unsignedTinyInteger('queue_number_padding')->default(3);
            $table->boolean('allow_appointment')->default(true);
            $table->boolean('allow_walk_in')->default(true);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'code']);
            $table->index(['branch_id', 'type', 'is_active']);
            $table->index(['branch_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
