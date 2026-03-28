<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\MenuCategory;
use Illuminate\Database\Seeder;

class NavigationSeeder extends Seeder
{
    public function run(): void
    {
        $this->cleanupLegacyNavigation();

        $overview = MenuCategory::query()->updateOrCreate(
            ['slug' => 'overview'],
            [
                'name' => 'Overview',
                'sort_order' => 10,
                'is_active' => true,
            ],
        );

        $access = MenuCategory::query()->updateOrCreate(
            ['slug' => 'access'],
            [
                'name' => 'Access',
                'sort_order' => 20,
                'is_active' => true,
            ],
        );

        $settings = MenuCategory::query()->updateOrCreate(
            ['slug' => 'settings'],
            [
                'name' => 'Settings',
                'sort_order' => 35,
                'is_active' => true,
            ],
        );

        $finance = MenuCategory::query()->updateOrCreate(
            ['slug' => 'finance'],
            [
                'name' => 'Finance',
                'sort_order' => 30,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'dashboard'],
            [
                'menu_category_id' => $overview->id,
                'parent_id' => null,
                'title' => 'Dashboard',
                'route_name' => '/dashboard',
                'icon' => 'dashboard',
                'permission_name' => 'view dashboard',
                'sort_order' => 10,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'user-management'],
            [
                'menu_category_id' => $access->id,
                'parent_id' => null,
                'title' => 'User Management',
                'route_name' => '/users',
                'icon' => 'user-profile',
                'permission_name' => 'view user management',
                'sort_order' => 10,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'role-permission'],
            [
                'menu_category_id' => $access->id,
                'parent_id' => null,
                'title' => 'Role & Permission',
                'route_name' => '/roles',
                'icon' => 'authentication',
                'permission_name' => 'view role permission',
                'sort_order' => 20,
                'is_active' => true,
            ],
        );

        $serviceDesk = MenuCategory::query()->updateOrCreate(
            ['slug' => 'service-desk'],
            [
                'name' => 'Service Desk',
                'sort_order' => 25,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'patients'],
            [
                'menu_category_id' => $serviceDesk->id,
                'parent_id' => null,
                'title' => 'Patients',
                'route_name' => '/patients',
                'icon' => 'user-profile',
                'permission_name' => 'view patient management',
                'sort_order' => 10,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'visit-registrations'],
            [
                'menu_category_id' => $serviceDesk->id,
                'parent_id' => null,
                'title' => 'Visit Registrations',
                'route_name' => '/visit-registrations',
                'icon' => 'forms',
                'permission_name' => 'view visit registration',
                'sort_order' => 20,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'queues'],
            [
                'menu_category_id' => $serviceDesk->id,
                'parent_id' => null,
                'title' => 'Queues',
                'route_name' => '/queues',
                'icon' => 'tables',
                'permission_name' => 'view queue management',
                'sort_order' => 30,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'vital-signs'],
            [
                'menu_category_id' => $serviceDesk->id,
                'parent_id' => null,
                'title' => 'Vital Signs',
                'route_name' => '/vital-signs',
                'icon' => 'charts',
                'permission_name' => 'view vital sign management',
                'sort_order' => 40,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'medical-records'],
            [
                'menu_category_id' => $serviceDesk->id,
                'parent_id' => null,
                'title' => 'Medical Records',
                'route_name' => '/medical-records',
                'icon' => 'forms',
                'permission_name' => 'view medical record management',
                'sort_order' => 50,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'prescriptions'],
            [
                'menu_category_id' => $serviceDesk->id,
                'parent_id' => null,
                'title' => 'Prescription',
                'route_name' => '/prescriptions',
                'icon' => 'forms',
                'permission_name' => 'view prescription management',
                'sort_order' => 55,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'procedures'],
            [
                'menu_category_id' => $serviceDesk->id,
                'parent_id' => null,
                'title' => 'Procedures',
                'route_name' => '/procedures',
                'icon' => 'task',
                'permission_name' => 'view procedure management',
                'sort_order' => 56,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'medical-services'],
            [
                'menu_category_id' => $serviceDesk->id,
                'parent_id' => null,
                'title' => 'Medical Services',
                'route_name' => '/medical-services',
                'icon' => 'task',
                'permission_name' => 'view medical service management',
                'sort_order' => 57,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'laboratory'],
            [
                'menu_category_id' => $serviceDesk->id,
                'parent_id' => null,
                'title' => 'Diagnostics',
                'route_name' => '/laboratory',
                'icon' => 'tables',
                'permission_name' => 'view laboratory management',
                'sort_order' => 58,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'referrals'],
            [
                'menu_category_id' => $serviceDesk->id,
                'parent_id' => null,
                'title' => 'Referrals',
                'route_name' => '/referrals',
                'icon' => 'forms',
                'permission_name' => 'view referral management',
                'sort_order' => 59,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'doctor-letters'],
            [
                'menu_category_id' => $serviceDesk->id,
                'parent_id' => null,
                'title' => 'Doctor Letters',
                'route_name' => '/doctor-letters',
                'icon' => 'pages',
                'permission_name' => 'view doctor letter management',
                'sort_order' => 60,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'billing'],
            [
                'menu_category_id' => $finance->id,
                'parent_id' => null,
                'title' => 'Sales Invoices',
                'route_name' => '/billing',
                'icon' => 'pages',
                'permission_name' => 'view sales invoice management',
                'sort_order' => 20,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'cashier-shifts'],
            [
                'menu_category_id' => $finance->id,
                'parent_id' => null,
                'title' => 'Cashier Shifts',
                'route_name' => '/cashier-shifts',
                'icon' => 'task',
                'permission_name' => 'view cashier shift management',
                'sort_order' => 10,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'payment-methods'],
            [
                'menu_category_id' => $finance->id,
                'parent_id' => null,
                'title' => 'Payment Methods',
                'route_name' => '/payment-methods',
                'icon' => 'pages',
                'permission_name' => 'view payment method management',
                'sort_order' => 30,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'backups'],
            [
                'menu_category_id' => $settings->id,
                'parent_id' => null,
                'title' => 'Backup Center',
                'route_name' => '/backups',
                'icon' => 'download',
                'permission_name' => 'view backup management',
                'sort_order' => 80,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'receivables'],
            [
                'menu_category_id' => $finance->id,
                'parent_id' => null,
                'title' => 'Receivables',
                'route_name' => '/receivables',
                'icon' => 'pages',
                'permission_name' => 'view receivable management',
                'sort_order' => 35,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'reports'],
            [
                'menu_category_id' => $finance->id,
                'parent_id' => null,
                'title' => 'Reports',
                'route_name' => '/reports',
                'icon' => 'charts',
                'permission_name' => 'view report management',
                'sort_order' => 40,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'clinic-branch-settings'],
            [
                'menu_category_id' => $settings->id,
                'parent_id' => null,
                'title' => 'Clinic & Branch',
                'route_name' => '/clinic',
                'icon' => 'forms',
                'permission_name' => 'view clinic settings',
                'sort_order' => 10,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'icd10-master'],
            [
                'menu_category_id' => $settings->id,
                'parent_id' => null,
                'title' => 'ICD-10 Master',
                'route_name' => '/icd10',
                'icon' => 'pages',
                'permission_name' => 'view icd10 management',
                'sort_order' => 60,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'counters'],
            [
                'menu_category_id' => $settings->id,
                'parent_id' => null,
                'title' => 'Counters',
                'route_name' => '/counters',
                'icon' => 'task',
                'permission_name' => 'view counter management',
                'sort_order' => 20,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'sections'],
            [
                'menu_category_id' => $settings->id,
                'parent_id' => null,
                'title' => 'Sections',
                'route_name' => '/sections',
                'icon' => 'task',
                'permission_name' => 'view section management',
                'sort_order' => 30,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'doctors'],
            [
                'menu_category_id' => $settings->id,
                'parent_id' => null,
                'title' => 'Doctors',
                'route_name' => '/doctors',
                'icon' => 'user-profile',
                'permission_name' => 'view doctor management',
                'sort_order' => 35,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'doctor-schedules'],
            [
                'menu_category_id' => $settings->id,
                'parent_id' => null,
                'title' => 'Doctor Schedules',
                'route_name' => '/doctor-schedules',
                'icon' => 'calendar',
                'permission_name' => 'view doctor schedule',
                'sort_order' => 36,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'pharmacy'],
            [
                'menu_category_id' => $settings->id,
                'parent_id' => null,
                'title' => 'Pharmacy',
                'route_name' => '/pharmacy',
                'icon' => 'box',
                'permission_name' => 'view pharmacy management',
                'sort_order' => 37,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'reorder-points'],
            [
                'menu_category_id' => $settings->id,
                'parent_id' => null,
                'title' => 'Reorder Points',
                'route_name' => '/reorder-points',
                'icon' => 'charts',
                'permission_name' => 'view reorder point management',
                'sort_order' => 37,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'drug-interactions'],
            [
                'menu_category_id' => $settings->id,
                'parent_id' => null,
                'title' => 'Drug Interactions',
                'route_name' => '/drug-interactions',
                'icon' => 'support-ticket',
                'permission_name' => 'view drug interaction management',
                'sort_order' => 38,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'product-categories'],
            [
                'menu_category_id' => $settings->id,
                'parent_id' => null,
                'title' => 'Product Categories',
                'route_name' => '/product-categories',
                'icon' => 'box',
                'permission_name' => 'view product category management',
                'sort_order' => 39,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'suppliers'],
            [
                'menu_category_id' => $settings->id,
                'parent_id' => null,
                'title' => 'Suppliers',
                'route_name' => '/suppliers',
                'icon' => 'tables',
                'permission_name' => 'view supplier management',
                'sort_order' => 40,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'purchase-orders'],
            [
                'menu_category_id' => $settings->id,
                'parent_id' => null,
                'title' => 'Purchase Orders',
                'route_name' => '/purchase-orders',
                'icon' => 'tables',
                'permission_name' => 'view purchase order management',
                'sort_order' => 41,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'goods-receipts'],
            [
                'menu_category_id' => $settings->id,
                'parent_id' => null,
                'title' => 'Goods Receipts',
                'route_name' => '/goods-receipts',
                'icon' => 'box',
                'permission_name' => 'view goods receipt management',
                'sort_order' => 42,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'purchase-returns'],
            [
                'menu_category_id' => $settings->id,
                'parent_id' => null,
                'title' => 'Purchase Returns',
                'route_name' => '/purchase-returns',
                'icon' => 'task',
                'permission_name' => 'view purchase return management',
                'sort_order' => 43,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'stock-adjustments'],
            [
                'menu_category_id' => $settings->id,
                'parent_id' => null,
                'title' => 'Stock Adjustments',
                'route_name' => '/stock-adjustments',
                'icon' => 'charts',
                'permission_name' => 'view stock adjustment management',
                'sort_order' => 44,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'stock-opnames'],
            [
                'menu_category_id' => $settings->id,
                'parent_id' => null,
                'title' => 'Stock Opnames',
                'route_name' => '/stock-opnames',
                'icon' => 'tables',
                'permission_name' => 'view stock opname management',
                'sort_order' => 45,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'expiry-monitoring'],
            [
                'menu_category_id' => $settings->id,
                'parent_id' => null,
                'title' => 'Expiry Monitoring',
                'route_name' => '/expiry-monitoring',
                'icon' => 'time',
                'permission_name' => 'view expiry monitoring management',
                'sort_order' => 46,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'menu-categories'],
            [
                'menu_category_id' => $settings->id,
                'parent_id' => null,
                'title' => 'Menu Categories',
                'route_name' => '/menu-categories',
                'icon' => 'forms',
                'permission_name' => 'view menu management',
                'sort_order' => 50,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'menu-items'],
            [
                'menu_category_id' => $settings->id,
                'parent_id' => null,
                'title' => 'Menu Items',
                'route_name' => '/menus',
                'icon' => 'tables',
                'permission_name' => 'view menu management',
                'sort_order' => 60,
                'is_active' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['slug' => 'audit-logs'],
            [
                'menu_category_id' => $settings->id,
                'parent_id' => null,
                'title' => 'Audit Logs',
                'route_name' => '/audit-logs',
                'icon' => 'charts',
                'permission_name' => 'view audit log management',
                'sort_order' => 70,
                'is_active' => true,
            ],
        );
    }

    private function cleanupLegacyNavigation(): void
    {
        Menu::query()
            ->where('slug', 'patient')
            ->orWhere('route_name', '/patient')
            ->delete();

        MenuCategory::query()
            ->whereIn('slug', ['dashboard', 'master-data'])
            ->delete();
    }
}
