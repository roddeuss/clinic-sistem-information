<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_sessions_page_is_displayed(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'other-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Pest Browser',
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($user)
            ->get('/settings/sessions')
            ->assertOk()
            ->assertSee('Active sessions')
            ->assertSee('Pest Browser');
    }

    public function test_user_can_revoke_another_session(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'other-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Pest Browser',
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($user)
            ->delete('/settings/sessions/other-session')
            ->assertRedirect();

        $this->assertDatabaseMissing('sessions', [
            'id' => 'other-session',
        ]);
    }
}
