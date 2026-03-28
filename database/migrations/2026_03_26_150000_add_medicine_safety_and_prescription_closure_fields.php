<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table): void {
            $table->text('active_ingredients')->nullable()->after('generic_name');
            $table->text('allergy_keywords')->nullable()->after('active_ingredients');
            $table->string('therapeutic_class', 120)->nullable()->after('dosage_form');
            $table->text('contraindication_notes')->nullable()->after('description');
        });

        Schema::table('prescription_items', function (Blueprint $table): void {
            $table->string('closed_remaining_status', 40)->nullable()->after('status');
            $table->text('closed_remaining_reason')->nullable()->after('closed_remaining_status');
            $table->timestamp('closed_remaining_at')->nullable()->after('closed_remaining_reason');
            $table->foreignId('closed_remaining_by_user_id')->nullable()->after('closed_remaining_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prescription_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('closed_remaining_by_user_id');
            $table->dropColumn([
                'closed_remaining_status',
                'closed_remaining_reason',
                'closed_remaining_at',
            ]);
        });

        Schema::table('medicines', function (Blueprint $table): void {
            $table->dropColumn([
                'active_ingredients',
                'allergy_keywords',
                'therapeutic_class',
                'contraindication_notes',
            ]);
        });
    }
};
