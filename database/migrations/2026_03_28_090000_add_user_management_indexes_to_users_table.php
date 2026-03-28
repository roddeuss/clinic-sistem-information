<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->index('is_active', 'users_is_active_index');
            $table->index('last_login_at', 'users_last_login_at_index');
            $table->index(['is_active', 'name'], 'users_is_active_name_index');
            $table->index('created_at', 'users_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_is_active_index');
            $table->dropIndex('users_last_login_at_index');
            $table->dropIndex('users_is_active_name_index');
            $table->dropIndex('users_created_at_index');
        });
    }
};
