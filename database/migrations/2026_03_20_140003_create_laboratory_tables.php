<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laboratory_tests', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('sample_type')->nullable();
            $table->string('default_provider_type')->default('internal');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('laboratory_test_parameters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('laboratory_test_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('unit', 30)->nullable();
            $table->string('reference_range')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('laboratory_test_branch_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('laboratory_test_id')->constrained()->cascadeOnDelete();
            $table->decimal('internal_price', 12, 2)->nullable();
            $table->decimal('external_price', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'laboratory_test_id']);
        });

        Schema::create('laboratory_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('visit_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('laboratory_test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ordered_by_doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->string('provider_type');
            $table->string('partner_name')->nullable();
            $table->string('external_reference_no')->nullable();
            $table->string('status')->default('ordered');
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->timestamp('ordered_at');
            $table->timestamp('sample_collected_at')->nullable();
            $table->timestamp('sent_to_partner_at')->nullable();
            $table->timestamp('resulted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('result_attachment_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('laboratory_result_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('laboratory_order_id')->constrained()->cascadeOnDelete();
            $table->string('parameter_code')->nullable();
            $table->string('parameter_name');
            $table->string('value')->nullable();
            $table->string('unit', 30)->nullable();
            $table->string('reference_range')->nullable();
            $table->string('result_flag', 20)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laboratory_result_entries');
        Schema::dropIfExists('laboratory_orders');
        Schema::dropIfExists('laboratory_test_branch_prices');
        Schema::dropIfExists('laboratory_test_parameters');
        Schema::dropIfExists('laboratory_tests');
    }
};
