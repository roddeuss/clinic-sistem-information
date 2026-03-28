<?php

namespace Tests\Feature\VitalSigns;

use App\Models\User;
use App\Models\VisitRegistration;
use App\Models\VitalSignRecord;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VitalSignManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_nurse_can_create_and_update_vital_signs(): void
    {
        $user = User::query()->where('email', 'nurse.general@csi.local')->firstOrFail();
        $visit = VisitRegistration::query()
            ->whereDate('visit_date', now()->toDateString())
            ->where('visit_type', 'same_day')
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($user)
            ->get(route('vital-signs'))
            ->assertOk()
            ->assertSee('Vital signs');

        $this->actingAs($user)
            ->post(route('vital-signs.store'), [
                'visit_registration_id' => $visit->id,
                'systolic_bp' => 123,
                'diastolic_bp' => 81,
                'temperature_celsius' => 36.9,
                'pulse_rate' => 86,
                'respiratory_rate' => 20,
                'weight_kg' => 62.5,
                'height_cm' => 168,
                'spo2_percent' => 98,
                'notes' => 'Tambahan pengukuran dari feature test.',
                'recorded_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('vital-signs'));

        $record = VitalSignRecord::query()
            ->where('visit_registration_id', $visit->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(123, $record->systolic_bp);
        $this->assertSame('waiting_doctor', $visit->fresh()->care_stage);
        $this->assertSame('completed', $visit->fresh()->vital_status);

        $this->actingAs($user)
            ->post(route('vital-signs.update', $record), [
                'visit_registration_id' => $visit->id,
                'systolic_bp' => 125,
                'diastolic_bp' => 83,
                'temperature_celsius' => 37.0,
                'pulse_rate' => 88,
                'respiratory_rate' => 21,
                'weight_kg' => 63.0,
                'height_cm' => 168,
                'spo2_percent' => 99,
                'notes' => 'Koreksi pengukuran.',
                'recorded_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('vital-signs'));

        $record->refresh();

        $this->assertSame(125, $record->systolic_bp);
        $this->assertSame(88, $record->pulse_rate);
    }
}
