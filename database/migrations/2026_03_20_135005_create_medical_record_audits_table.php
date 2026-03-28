<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_record_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_record_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->text('notes')->nullable();
            $table->json('snapshot')->nullable();
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['medical_record_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_record_audits');
    }
};
