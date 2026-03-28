<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('permission.table_names.roles'), function (Blueprint $table): void {
            $table->index('created_at', 'roles_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table(config('permission.table_names.roles'), function (Blueprint $table): void {
            $table->dropIndex('roles_created_at_index');
        });
    }
};
