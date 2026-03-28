<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedure_masters', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('performer_scope')->default('both');
            $table->boolean('requires_doctor_order')->default(true);
            $table->decimal('default_fee', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('procedure_branch_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('procedure_master_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'procedure_master_id']);
        });

        Schema::create('visit_procedures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('visit_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('procedure_master_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ordered_by_doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('performed_by_role')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->string('status')->default('ordered');
            $table->timestamp('ordered_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_procedures');
        Schema::dropIfExists('procedure_branch_prices');
        Schema::dropIfExists('procedure_masters');
    }
};
