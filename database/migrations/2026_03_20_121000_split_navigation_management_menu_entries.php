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
        $timestamp = now();
        $settingsCategoryId = DB::table('menu_categories')
            ->where('slug', 'settings')
            ->value('id');

        if (! $settingsCategoryId) {
            return;
        }

        $legacyMenuId = DB::table('menus')
            ->where('slug', 'navigation-settings')
            ->value('id');

        if ($legacyMenuId) {
            DB::table('menus')
                ->where('id', $legacyMenuId)
                ->update([
                    'slug' => 'menu-items',
                    'title' => 'Menu Items',
                    'route_name' => '/menus',
                    'icon' => 'tables',
                    'permission_name' => null,
                    'sort_order' => 30,
                    'is_active' => true,
                ]);
        } else {
            DB::table('menus')->updateOrInsert(
                ['slug' => 'menu-items'],
                [
                    'menu_category_id' => $settingsCategoryId,
                    'parent_id' => null,
                    'title' => 'Menu Items',
                    'route_name' => '/menus',
                    'icon' => 'tables',
                    'permission_name' => null,
                    'sort_order' => 30,
                    'is_active' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
            );
        }

        DB::table('menus')->updateOrInsert(
            ['slug' => 'menu-categories'],
            [
                'menu_category_id' => $settingsCategoryId,
                'parent_id' => null,
                'title' => 'Menu Categories',
                'route_name' => '/menu-categories',
                'icon' => 'forms',
                'permission_name' => null,
                'sort_order' => 20,
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('menus')
            ->where('slug', 'menu-categories')
            ->delete();

        $menuItemsId = DB::table('menus')
            ->where('slug', 'menu-items')
            ->value('id');

        if (! $menuItemsId) {
            return;
        }

        DB::table('menus')
            ->where('id', $menuItemsId)
            ->update([
                'slug' => 'navigation-settings',
                'title' => 'Menu Management',
                'route_name' => 'menus',
                'icon' => 'tables',
                'permission_name' => 'view menu management',
                'sort_order' => 20,
                'is_active' => true,
            ]);
    }
};
