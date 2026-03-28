<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_registrations', function (Blueprint $table) {
            $table->string('care_stage')->default('scheduled')->after('registration_status');
            $table->string('vital_status')->default('pending')->after('care_stage');
        });

        DB::table('visit_registrations')
            ->orderBy('id')
            ->get()
            ->each(function (object $registration): void {
                $careStage = match (true) {
                    $registration->registration_status === 'cancelled' => 'cancelled',
                    $registration->visit_type === 'booking' && $registration->queued_at === null => 'scheduled',
                    $registration->visit_type === 'emergency' => 'waiting_doctor',
                    default => 'waiting_nurse',
                };

                DB::table('visit_registrations')
                    ->where('id', $registration->id)
                    ->update([
                        'care_stage' => $careStage,
                        'vital_status' => 'pending',
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('visit_registrations', function (Blueprint $table) {
            $table->dropColumn(['care_stage', 'vital_status']);
        });
    }
};
