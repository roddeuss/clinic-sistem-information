<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laboratory_tests', function (Blueprint $table): void {
            $table->string('diagnostic_category')->default('laboratory')->after('name');
            $table->string('result_entry_mode')->default('structured')->after('default_provider_type');
        });

        Schema::table('laboratory_orders', function (Blueprint $table): void {
            $table->text('result_summary')->nullable()->after('result_attachment_path');
            $table->text('result_impression')->nullable()->after('result_summary');
        });

        DB::table('laboratory_tests')->update([
            'diagnostic_category' => 'laboratory',
            'result_entry_mode' => 'structured',
        ]);
    }

    public function down(): void
    {
        Schema::table('laboratory_orders', function (Blueprint $table): void {
            $table->dropColumn(['result_summary', 'result_impression']);
        });

        Schema::table('laboratory_tests', function (Blueprint $table): void {
            $table->dropColumn(['diagnostic_category', 'result_entry_mode']);
        });
    }
};
