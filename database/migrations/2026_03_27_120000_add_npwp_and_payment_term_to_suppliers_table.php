<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->string('npwp', 40)->nullable()->after('email');
            $table->unsignedSmallInteger('payment_term_days')->nullable()->after('npwp');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn([
                'npwp',
                'payment_term_days',
            ]);
        });
    }
};
