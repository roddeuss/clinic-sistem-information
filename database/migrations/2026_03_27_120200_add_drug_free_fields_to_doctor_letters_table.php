<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctor_letters', function (Blueprint $table): void {
            $table->date('drug_test_date')->nullable()->after('control_notes');
            $table->string('drug_test_method', 120)->nullable()->after('drug_test_date');
            $table->string('drug_test_result', 120)->nullable()->after('drug_test_method');
            $table->text('drug_free_statement')->nullable()->after('drug_test_result');
        });
    }

    public function down(): void
    {
        Schema::table('doctor_letters', function (Blueprint $table): void {
            $table->dropColumn([
                'drug_test_date',
                'drug_test_method',
                'drug_test_result',
                'drug_free_statement',
            ]);
        });
    }
};
