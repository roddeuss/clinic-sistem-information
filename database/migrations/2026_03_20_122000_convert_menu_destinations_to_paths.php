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
        $updates = [
            'dashboard' => ['/dashboard', null],
            'users' => ['/users', null],
            'roles' => ['/roles', null],
            'clinic' => ['/clinic', null],
            'menu-categories' => ['/menu-categories', null],
            'menus' => ['/menus', null],
        ];

        foreach ($updates as $oldDestination => [$newDestination, $permissionName]) {
            DB::table('menus')
                ->where('route_name', $oldDestination)
                ->update([
                    'route_name' => $newDestination,
                    'permission_name' => $permissionName,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $updates = [
            '/dashboard' => 'dashboard',
            '/users' => 'users',
            '/roles' => 'roles',
            '/clinic' => 'clinic',
            '/menu-categories' => 'menu-categories',
            '/menus' => 'menus',
        ];

        foreach ($updates as $oldDestination => $newDestination) {
            DB::table('menus')
                ->where('route_name', $oldDestination)
                ->update([
                    'route_name' => $newDestination,
                ]);
        }
    }
};
