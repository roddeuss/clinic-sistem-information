<?php

namespace Tests\Feature\MedicalRecords;

use App\Models\MedicalRecord;
use App\Models\User;
use App\Models\VisitRegistration;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicalRecordManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_doctor_can_create_medical_record_and_admin_can_approve_reopen(): void
    {
        $doctorUser = User::query()->where('email', 'doctor.maria@csi.local')->firstOrFail();
        $adminUser = User::query()->where('email', 'clinicadmin@csi.local')->firstOrFail();

        $visit = VisitRegistration::query()
            ->whereDate('visit_date', now()->toDateString())
            ->whereDoesntHave('medicalRecord')
            ->whereHas('section.doctors')
            ->firstOrFail();

        $doctorId = $visit->section->doctors()->orderBy('doctors.id')->value('doctors.id');
        $primaryCodeId = \App\Models\Icd10Code::query()->where('code', 'J06.9')->value('id');
        $secondaryCodeId = \App\Models\Icd10Code::query()->where('code', 'R05')->value('id');

        $this->actingAs($doctorUser)
            ->get(route('medical-records'))
            ->assertOk()
            ->assertSee('Medical records');

        $this->actingAs($doctorUser)
            ->post(route('medical-records.store'), [
                'visit_registration_id' => $visit->id,
                'doctor_id' => $doctorId,
                'subjective' => 'Batuk pilek sejak dua hari.',
                'objective' => 'Keadaan umum baik. Vital terbaru mendukung observasi rawat jalan.',
                'assessment' => 'ISPA akut non komplikata.',
                'plan' => 'Terapi simptomatik dan edukasi.',
                'diagnosis_notes' => 'Catatan testing.',
                'primary_icd10_id' => $primaryCodeId,
                'secondary_icd10_ids' => [$secondaryCodeId],
                'submit_action' => 'final',
            ])
            ->assertRedirect(route('medical-records'));

        $record = MedicalRecord::query()->where('visit_registration_id', $visit->id)->firstOrFail();

        $this->assertSame('final', $record->status);
        $this->assertSame('ready_for_checkout', $visit->fresh()->care_stage);

        $this->actingAs($doctorUser)
            ->post(route('medical-records.request-reopen', $record), [
                'reason' => 'Perlu koreksi plan.',
            ])
            ->assertRedirect(route('medical-records'));

        $record->refresh();
        $this->assertSame('reopen_requested', $record->status);

        $this->actingAs($adminUser)
            ->post(route('medical-records.approve-reopen', $record), [
                'reason' => 'Disetujui untuk revisi SOAP.',
            ])
            ->assertRedirect(route('medical-records'));

        $record->refresh();

        $this->assertSame('reopened', $record->status);
        $this->assertSame('in_consultation', $visit->fresh()->care_stage);
    }
}
