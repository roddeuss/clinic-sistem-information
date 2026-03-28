<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_services', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('service_type')->default('clinical');
            $table->text('description')->nullable();
            $table->decimal('default_fee', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('medical_service_branch_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medical_service_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'medical_service_id']);
        });

        Schema::create('visit_medical_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('visit_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('medical_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ordered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->string('status')->default('ordered');
            $table->timestamp('ordered_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_medical_services');
        Schema::dropIfExists('medical_service_branch_prices');
        Schema::dropIfExists('medical_services');
    }
};
