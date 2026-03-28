<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctor_schedules', function (Blueprint $table): void {
            $table->string('room_label', 80)
                ->nullable()
                ->after('max_patients');
        });
    }

    public function down(): void
    {
        Schema::table('doctor_schedules', function (Blueprint $table): void {
            $table->dropColumn('room_label');
        });
    }
};
