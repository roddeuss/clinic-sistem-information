<?php

namespace Tests\Feature\Backups;

use App\Models\SystemBackup;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupManagementTest extends TestCase
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

        config([
            'system_backup.disk' => 'local',
            'system_backup.path' => 'backups/testing',
        ]);

        Storage::fake('local');
    }

    public function test_super_admin_can_create_download_and_restore_backup(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        $this->actingAs($user)
            ->get(route('backups'))
            ->assertOk()
            ->assertSee('Backup Center');

        $this->actingAs($user)
            ->post(route('backups.store'), [
                'notes' => 'Manual backup for feature test.',
            ])
            ->assertRedirect();

        $backup = SystemBackup::query()->firstOrFail();

        $this->assertSame('ready', $backup->status);
        $this->assertNotNull($backup->file_path);
        Storage::disk($backup->disk)->assertExists($backup->file_path);

        $downloadResponse = $this->actingAs($user)
            ->get(route('backups.download', $backup));

        $downloadResponse->assertOk();
        $this->assertStringContainsString('.zip', (string) $downloadResponse->headers->get('content-disposition'));

        $this->actingAs($user)
            ->post(route('backups.restore', $backup), [
                'restore_reason' => 'Rollback feature test.',
            ])
            ->assertRedirect();

        $this->assertSame('restored', $backup->fresh()->status);
        $this->assertNotNull($backup->fresh()->restored_at);
    }

    public function test_clinic_admin_cannot_access_backup_center(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $this->actingAs($user)
            ->get(route('backups'))
            ->assertForbidden();
    }
}
