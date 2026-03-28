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
            'view patient management',
            'create patient management',
            'edit patient management',
            'delete patient management',
            'view visit registration',
            'create visit registration',
            'edit visit registration',
            'delete visit registration',
            'view queue management',
            'create queue management',
            'edit queue management',
            'delete queue management',
        ];

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $clinicAdmin = Role::query()->where('name', 'clinic-admin')->first();
        if ($clinicAdmin) {
            $clinicAdmin->givePermissionTo($permissions);
        }

        $frontOffice = Role::query()->where('name', 'front-office')->first();
        if ($frontOffice) {
            $frontOffice->givePermissionTo([
                'view patient management',
                'create patient management',
                'edit patient management',
                'view visit registration',
                'create visit registration',
                'edit visit registration',
                'delete visit registration',
                'view queue management',
                'edit queue management',
            ]);
        }

        $cashier = Role::query()->where('name', 'cashier')->first();
        if ($cashier) {
            $cashier->givePermissionTo([
                'view patient management',
                'create patient management',
                'edit patient management',
                'view visit registration',
                'create visit registration',
                'edit visit registration',
                'delete visit registration',
                'view queue management',
                'edit queue management',
            ]);
        }

        foreach (['doctor', 'nurse'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo(['view queue management']);
            }
        }

        $settings = MenuCategory::query()->firstOrCreate(
            ['slug' => 'settings'],
            ['name' => 'Settings', 'sort_order' => 30, 'is_active' => true],
        );

        $service = MenuCategory::query()->firstOrCreate(
            ['slug' => 'service-desk'],
            ['name' => 'Service Desk', 'sort_order' => 25, 'is_active' => true],
        );

        $menus = [
            [
                'slug' => 'patients',
                'menu_category_id' => $service->id,
                'title' => 'Patients',
                'route_name' => '/patients',
                'icon' => 'user-profile',
                'permission_name' => 'view patient management',
                'sort_order' => 10,
            ],
            [
                'slug' => 'visit-registrations',
                'menu_category_id' => $service->id,
                'title' => 'Visit Registrations',
                'route_name' => '/visit-registrations',
                'icon' => 'forms',
                'permission_name' => 'view visit registration',
                'sort_order' => 20,
            ],
            [
                'slug' => 'queues',
                'menu_category_id' => $service->id,
                'title' => 'Queues',
                'route_name' => '/queues',
                'icon' => 'tables',
                'permission_name' => 'view queue management',
                'sort_order' => 30,
            ],
        ];

        foreach ($menus as $menu) {
            Menu::query()->updateOrCreate(
                ['slug' => $menu['slug']],
                $menu + ['parent_id' => null, 'is_active' => true],
            );
        }

        $pathPermissions = [
            '/dashboard' => 'view dashboard',
            '/users' => 'view user management',
            '/roles' => 'view role permission',
            '/clinic' => 'view clinic settings',
            '/counters' => 'view counter management',
            '/sections' => 'view section management',
            '/doctors' => 'view doctor management',
            '/doctor-schedules' => 'view doctor schedule',
            '/patients' => 'view patient management',
            '/visit-registrations' => 'view visit registration',
            '/queues' => 'view queue management',
            '/menu-categories' => 'view menu management',
            '/menus' => 'view menu management',
        ];

        foreach ($pathPermissions as $path => $permissionName) {
            DB::table('menus')
                ->where('route_name', $path)
                ->update(['permission_name' => $permissionName]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('menus')
            ->whereIn('slug', ['patients', 'visit-registrations', 'queues'])
            ->delete();

        DB::table('menu_categories')
            ->where('slug', 'service-desk')
            ->delete();
    }
};
