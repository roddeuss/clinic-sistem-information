<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('purchase_returns', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('stock_adjustments', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('stock_opnames', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('stock_adjustments', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('purchase_returns', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
