<?php

use App\Models\Menu;
use App\Models\MenuCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'view vital sign management',
            'create vital sign management',
            'edit vital sign management',
            'delete vital sign management',
            'view medical record management',
            'create medical record management',
            'edit medical record management',
            'delete medical record management',
            'view icd10 management',
            'create icd10 management',
            'edit icd10 management',
            'delete icd10 management',
        ];

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $rolePermissions = [
            'clinic-admin' => $permissions,
            'doctor' => [
                'view medical record management',
                'create medical record management',
                'edit medical record management',
                'view icd10 management',
            ],
            'nurse' => [
                'view vital sign management',
                'create vital sign management',
                'edit vital sign management',
                'view icd10 management',
            ],
        ];

        foreach ($rolePermissions as $roleName => $grants) {
            $role = Role::query()->where('name', $roleName)->first();

            if ($role) {
                $role->givePermissionTo($grants);
            }
        }

        $serviceDesk = MenuCategory::query()->firstOrCreate(
            ['slug' => 'service-desk'],
            ['name' => 'Service Desk', 'sort_order' => 25, 'is_active' => true],
        );

        $settings = MenuCategory::query()->firstOrCreate(
            ['slug' => 'settings'],
            ['name' => 'Settings', 'sort_order' => 30, 'is_active' => true],
        );

        $menus = [
            [
                'slug' => 'vital-signs',
                'menu_category_id' => $serviceDesk->id,
                'title' => 'Vital Signs',
                'route_name' => '/vital-signs',
                'icon' => 'charts',
                'permission_name' => 'view vital sign management',
                'sort_order' => 40,
            ],
            [
                'slug' => 'medical-records',
                'menu_category_id' => $serviceDesk->id,
                'title' => 'Medical Records',
                'route_name' => '/medical-records',
                'icon' => 'forms',
                'permission_name' => 'view medical record management',
                'sort_order' => 50,
            ],
            [
                'slug' => 'icd10-master',
                'menu_category_id' => $settings->id,
                'title' => 'ICD-10 Master',
                'route_name' => '/icd10',
                'icon' => 'pages',
                'permission_name' => 'view icd10 management',
                'sort_order' => 60,
            ],
        ];

        foreach ($menus as $menu) {
            Menu::query()->updateOrCreate(
                ['slug' => $menu['slug']],
                $menu + ['parent_id' => null, 'is_active' => true],
            );
        }

        foreach ([
            '/vital-signs' => 'view vital sign management',
            '/medical-records' => 'view medical record management',
            '/icd10' => 'view icd10 management',
        ] as $path => $permissionName) {
            DB::table('menus')
                ->where('route_name', $path)
                ->update(['permission_name' => $permissionName]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('menus')
            ->whereIn('slug', ['vital-signs', 'medical-records', 'icd10-master'])
            ->delete();
    }
};
