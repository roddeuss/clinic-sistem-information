<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissionNames = [
            'view doctor management',
            'create doctor management',
            'edit doctor management',
            'delete doctor management',
        ];

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($permissionNames as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        foreach (config('csi_access.roles') as $roleName => $roleDefinition) {
            $role = Role::query()->where('name', $roleName)->first();

            if (! $role) {
                continue;
            }

            $grantedPermissions = $roleDefinition['permissions'] === '*'
                ? $permissionNames
                : array_values(array_intersect($permissionNames, $roleDefinition['permissions']));

            if ($grantedPermissions !== []) {
                $role->givePermissionTo($grantedPermissions);
            }
        }

        $settingsCategoryId = DB::table('menu_categories')
            ->where('slug', 'settings')
            ->value('id');

        if (! $settingsCategoryId) {
            $settingsCategoryId = DB::table('menu_categories')->insertGetId([
                'name' => 'Settings',
                'slug' => 'settings',
                'sort_order' => 30,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menus')->updateOrInsert(
            ['slug' => 'doctors'],
            [
                'menu_category_id' => $settingsCategoryId,
                'parent_id' => null,
                'title' => 'Doctors',
                'route_name' => '/doctors',
                'icon' => 'user-profile',
                'permission_name' => null,
                'sort_order' => 35,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionNames = [
            'view doctor management',
            'create doctor management',
            'edit doctor management',
            'delete doctor management',
        ];

        DB::table('menus')->where('slug', 'doctors')->delete();

        $permissions = Permission::query()
            ->whereIn('name', $permissionNames)
            ->get();

        foreach ($permissions as $permission) {
            $permission->roles()->detach();
            $permission->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
