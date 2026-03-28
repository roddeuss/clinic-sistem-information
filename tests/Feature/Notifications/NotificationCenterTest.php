<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Notifications\SystemAlertNotification;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            AccessControlSeeder::class,
            ClinicSettingsSeeder::class,
            NavigationSeeder::class,
        ]);
    }

    public function test_user_can_view_and_open_notification_center_items(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');
        $user->notify(new SystemAlertNotification([
            'title' => 'Test notification',
            'message' => 'Notification center works.',
            'action_url' => route('dashboard'),
            'module' => 'system',
            'level' => 'info',
        ]));

        $notification = $user->notifications()->firstOrFail();

        $this->actingAs($user)
            ->get(route('notifications'))
            ->assertOk()
            ->assertSee('Notifications')
            ->assertSee('Test notification');

        $this->actingAs($user)
            ->get(route('notifications.open', $notification))
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($notification->fresh()->read_at);
    }
}
