<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laboratory_orders', function (Blueprint $table): void {
            $table->foreignId('resulted_by_user_id')->nullable()->after('resulted_at')->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('request_printed_at')->nullable()->after('result_attachment_path');
            $table->timestamp('result_printed_at')->nullable()->after('request_printed_at');
        });
    }

    public function down(): void
    {
        Schema::table('laboratory_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('resulted_by_user_id');
            $table->dropConstrainedForeignId('reviewed_by_user_id');
            $table->dropColumn([
                'request_printed_at',
                'result_printed_at',
            ]);
        });
    }
};
