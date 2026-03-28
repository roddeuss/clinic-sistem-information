<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type', 40);
            $table->text('description')->nullable();
            $table->boolean('is_cash')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('cashier_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('counter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('shift_code')->unique();
            $table->string('status', 40)->default('open');
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->text('opening_notes')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('closing_balance', 12, 2)->nullable();
            $table->decimal('expected_cash_total', 12, 2)->nullable();
            $table->decimal('cash_variance', 12, 2)->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('closing_notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['counter_id', 'status']);
            $table->index(['branch_id', 'opened_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('auditable');
            $table->string('module', 80);
            $table->string('action', 80);
            $table->string('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['module', 'action']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('cashier_shifts');
        Schema::dropIfExists('payment_methods');
    }
};
