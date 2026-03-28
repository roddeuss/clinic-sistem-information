<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->foreignId('approved_by_user_id')->nullable()->after('submitted_by_user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by_user_id')->nullable()->after('approved_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('submitted_at');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->text('approval_notes')->nullable()->after('rejected_at');
            $table->string('rejection_reason')->nullable()->after('approval_notes');

            $table->index(['status', 'approved_at']);
            $table->index(['status', 'rejected_at']);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropIndex(['status', 'approved_at']);
            $table->dropIndex(['status', 'rejected_at']);
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropConstrainedForeignId('rejected_by_user_id');
            $table->dropColumn([
                'approved_at',
                'rejected_at',
                'approval_notes',
                'rejection_reason',
            ]);
        });
    }
};
