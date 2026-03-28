<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->syncRouteNames([
            'access.users.index' => 'users',
            'access.roles.index' => 'roles',
            'clinic-settings.edit' => 'clinic',
            'navigation.index' => 'menus',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->syncRouteNames([
            'users' => 'access.users.index',
            'roles' => 'access.roles.index',
            'clinic' => 'clinic-settings.edit',
            'menus' => 'navigation.index',
        ]);
    }

    private function syncRouteNames(array $mapping): void
    {
        foreach ($mapping as $from => $to) {
            DB::table('menus')
                ->where('route_name', $from)
                ->update(['route_name' => $to]);
        }
    }
};
