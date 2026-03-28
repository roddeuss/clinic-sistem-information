<?php

namespace Tests\Feature\Navigation;

use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            AccessControlSeeder::class,
            NavigationSeeder::class,
        ]);
    }

    public function test_clinic_admin_can_open_navigation_pages(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $this->actingAs($user)
            ->get(route('menu-categories'))
            ->assertOk()
            ->assertSee('Menu Categories');

        $this->actingAs($user)
            ->get(route('menus'))
            ->assertOk()
            ->assertSee('Menu Items');
    }

    public function test_clinic_admin_can_create_category_and_menu(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $this->actingAs($user)
            ->from(route('menu-categories'))
            ->post(route('menu-categories.store'), [
                'name' => 'Reports',
                'sort_order' => 40,
                'is_active' => 1,
            ])
            ->assertRedirect(route('menu-categories'));

        $this->assertDatabaseHas('menu_categories', [
            'name' => 'Reports',
            'slug' => 'reports',
        ]);

        $categoryId = \App\Models\MenuCategory::query()->where('slug', 'reports')->value('id');

        $this->actingAs($user)
            ->from(route('menus'))
            ->post(route('menu-items.store'), [
                'menu_category_id' => $categoryId,
                'parent_id' => null,
                'title' => 'Monthly Report',
                'route_name' => '/dashboard',
                'icon' => 'box',
                'sort_order' => 10,
                'is_active' => 1,
            ])
            ->assertRedirect(route('menus'));

        $this->assertDatabaseHas('menus', [
            'title' => 'Monthly Report',
            'slug' => 'monthly-report',
            'route_name' => '/dashboard',
        ]);
    }

    public function test_sidebar_respects_database_menu_permissions(): void
    {
        $clinicAdmin = User::factory()->create();
        $clinicAdmin->assignRole('clinic-admin');

        $this->actingAs($clinicAdmin)
            ->get(route('dashboard'))
            ->assertSeeHtml('href="' . route('counters', absolute: false) . '"')
            ->assertSeeHtml('href="' . route('doctors', absolute: false) . '"')
            ->assertSeeHtml('href="' . route('doctor-schedules', absolute: false) . '"')
            ->assertSeeHtml('href="' . route('sections', absolute: false) . '"')
            ->assertSeeHtml('href="' . route('menu-categories', absolute: false) . '"')
            ->assertSeeHtml('href="' . route('menus', absolute: false) . '"');

        auth()->logout();

        $frontOffice = User::factory()->create();
        $frontOffice->assignRole('front-office');

        $this->actingAs($frontOffice)
            ->get(route('dashboard'))
            ->assertDontSeeHtml('href="' . route('counters', absolute: false) . '"')
            ->assertDontSeeHtml('href="' . route('doctors', absolute: false) . '"')
            ->assertDontSeeHtml('href="' . route('doctor-schedules', absolute: false) . '"')
            ->assertDontSeeHtml('href="' . route('sections', absolute: false) . '"')
            ->assertDontSeeHtml('href="' . route('menu-categories', absolute: false) . '"')
            ->assertDontSeeHtml('href="' . route('menus', absolute: false) . '"');
    }
}
