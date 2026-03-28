<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receivables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->unique()->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('patient_branch_record_id')->nullable()->constrained('patient_branch_records')->nullOnDelete();
            $table->string('status', 30)->default('open');
            $table->date('due_date');
            $table->timestamp('opened_at');
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('extended_at')->nullable();
            $table->foreignId('extended_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('extension_reason')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('settled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_date']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receivables');
    }
};
