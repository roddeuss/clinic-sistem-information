<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->index(['is_active', 'full_name'], 'patients_is_active_full_name_index');
            $table->index('created_at', 'patients_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->dropIndex('patients_is_active_full_name_index');
            $table->dropIndex('patients_created_at_index');
        });
    }
};
