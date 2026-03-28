<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_doctor_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['visit_registration_id', 'released_at']);
        });

        DB::table('visit_registrations')
            ->whereNotNull('doctor_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $registration): void {
                DB::table('visit_doctor_assignments')->insert([
                    'visit_registration_id' => $registration->id,
                    'doctor_id' => $registration->doctor_id,
                    'assigned_by_user_id' => null,
                    'assigned_at' => $registration->created_at ?? now(),
                    'released_at' => null,
                    'notes' => 'Initial assignment migrated from visit registration.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_doctor_assignments');
    }
};
