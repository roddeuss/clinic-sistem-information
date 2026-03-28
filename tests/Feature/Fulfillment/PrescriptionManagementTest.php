<?php

namespace Tests\Feature\Fulfillment;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Section;
use App\Models\User;
use App\Models\VisitRegistration;
use App\Services\PatientRecordService;
use Carbon\Carbon;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrescriptionManagementTest extends TestCase
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

    public function test_clinic_admin_can_dispense_partially_and_close_remaining_to_external(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();
        $counter = Counter::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Counter RX',
            'code' => 'CRX1',
            'location' => 'Lobby',
            'description' => 'Counter prescription',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $section = Section::query()->create([
            'branch_id' => $branch->id,
            'name' => 'General',
            'code' => 'GENERAL',
            'type' => 'regular',
            'queue_prefix' => 'GEN',
            'queue_number_padding' => 3,
            'allow_appointment' => true,
            'allow_walk_in' => true,
            'description' => 'General service',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $doctor = Doctor::query()->create([
            'full_name' => 'Maria Simanjuntak',
            'title_prefix' => 'dr.',
            'title_suffix' => 'Sp.PD',
            'specialization' => 'Internal Medicine',
            'consultation_fee' => 200000,
            'str_number' => 'STR-RX-001',
            'str_expired_at' => '2029-12-31',
            'sip_number' => 'SIP-RX-001',
            'sip_expired_at' => '2028-12-31',
            'phone' => '081234562221',
            'email' => 'rx-maria@example.com',
            'address' => 'Jakarta',
            'is_active' => true,
        ]);

        $patient = Patient::query()->create([
            'full_name' => 'Kevin Hutabarat',
            'gender' => 'male',
            'date_of_birth' => '2018-02-11',
            'nik' => '3174011102180001',
            'phone' => '081234562222',
            'email' => null,
            'province_code' => '31',
            'province_name' => 'DKI Jakarta',
            'city_code' => '3173',
            'city_name' => 'Kota Jakarta Barat',
            'district_code' => '317304',
            'district_name' => 'Palmerah',
            'village_code' => '3173041001',
            'village_name' => 'Slipi',
            'address_line' => 'Jl. Tomang',
            'allergy_notes' => 'Alergi penicillin',
            'is_active' => true,
        ]);

        $patientRecord = app(PatientRecordService::class)->ensureBranchRecord($patient, $branch);

        $visit = VisitRegistration::query()->create([
            'patient_id' => $patient->id,
            'patient_branch_record_id' => $patientRecord->id,
            'branch_id' => $branch->id,
            'counter_id' => $counter->id,
            'section_id' => $section->id,
            'doctor_id' => $doctor->id,
            'doctor_schedule_id' => null,
            'visit_date' => Carbon::today()->toDateString(),
            'visit_type' => 'same_day',
            'registration_status' => 'queued',
            'care_stage' => 'waiting_doctor',
            'vital_status' => 'completed',
            'booking_code' => null,
            'slot_start_time' => '08:00:00',
            'slot_end_time' => '08:15:00',
            'notes' => 'Prescription workflow test',
            'checked_in_at' => now(),
            'queued_at' => now(),
        ]);

        MedicalRecord::query()->create([
            'visit_registration_id' => $visit->id,
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'section_id' => $section->id,
            'doctor_id' => $doctor->id,
            'subjective' => 'Demam dan nyeri tenggorokan.',
            'objective' => 'Suhu 38 C, tenggorokan hiperemis.',
            'assessment' => 'Faringitis akut.',
            'plan' => 'Obat simptomatik dan monitoring.',
            'diagnosis_notes' => null,
            'status' => 'final',
            'finalized_at' => now(),
            'finalized_by_user_id' => $user->id,
        ]);

        $medicine = Medicine::query()->create([
            'code' => 'AMX500',
            'name' => 'Amoxicillin',
            'generic_name' => 'Amoxicillin',
            'active_ingredients' => 'Amoxicillin',
            'allergy_keywords' => 'amoxicillin, penicillin, beta-lactam',
            'dosage_form' => 'capsule',
            'therapeutic_class' => 'beta-lactam antibiotic',
            'strength' => '500 mg',
            'base_unit' => 'capsule',
            'description' => 'Antibiotic test item',
            'contraindication_notes' => 'Avoid in patients with known penicillin allergy.',
            'is_compoundable' => true,
            'is_active' => true,
        ]);

        $medicine->branchPrices()->create([
            'branch_id' => $branch->id,
            'selling_price' => 2500,
            'is_active' => true,
        ]);

        MedicineBatch::query()->create([
            'branch_id' => $branch->id,
            'medicine_id' => $medicine->id,
            'batch_number' => 'AMX500-A1',
            'received_at' => Carbon::today()->toDateString(),
            'expired_at' => Carbon::today()->addMonths(12)->toDateString(),
            'quantity_received' => 12,
            'quantity_available' => 12,
            'purchase_cost' => 1200,
            'supplier_name' => 'PT Demo Farmasi',
            'notes' => 'Seed batch',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->from(route('prescriptions'))
            ->post(route('prescriptions.store'), [
                'visit_registration_id' => $visit->id,
                'notes' => 'Header prescription',
            ])
            ->assertRedirect(route('prescriptions'));

        $prescription = Prescription::query()->where('visit_registration_id', $visit->id)->firstOrFail();

        $this->actingAs($user)
            ->from(route('prescriptions'))
            ->post(route('prescription-items.store'), [
                'prescription_id' => $prescription->id,
                'item_type' => 'in_house',
                'medicine_id' => $medicine->id,
                'route' => 'oral',
                'dose_amount' => 1,
                'dose_unit' => 'capsule',
                'frequency' => '3x sehari',
                'duration_days' => 3,
                'instruction' => 'Sesudah makan.',
                'quantity_prescribed' => 9,
                'dispense_unit' => 'capsule',
                'weight_snapshot_kg' => 18.5,
                'status' => 'pending',
                'notes' => 'Pediatric antibiotic.',
            ])
            ->assertRedirect(route('prescriptions'));

        $item = PrescriptionItem::query()->where('prescription_id', $prescription->id)->firstOrFail();
        $dispensingItem = app(\App\Modules\Prescriptions\Services\PrescriptionService::class)
            ->getIndexData(['date' => Carbon::today()->toDateString()])['dispensingItems']
            ->getCollection()
            ->firstWhere('id', $item->id);

        $this->assertNotNull($dispensingItem);
        $alertMessages = collect($dispensingItem->getAttribute('safety_alerts') ?? [])->pluck('message');
        $this->assertTrue(
            $alertMessages->contains(fn (string $message): bool => str_contains(strtolower($message), 'penicillin')),
            'Alerts: ' . json_encode($alertMessages->all())
        );
        $this->assertTrue(
            $alertMessages->contains(fn (string $message): bool => str_contains($message, 'Contraindication / caution')),
            'Alerts: ' . json_encode($alertMessages->all())
        );

        $this->actingAs($user)
            ->from(route('prescriptions'))
            ->post(route('prescriptions.finalize', $prescription), [
                'notes' => 'Finalize prescription.',
            ])
            ->assertRedirect(route('prescriptions'));

        $visit->refresh();
        $this->assertSame('awaiting_fulfillment', $visit->care_stage);

        $this->actingAs($user)
            ->from(route('prescriptions'))
            ->post(route('prescription-items.dispense', $item), [
                'quantity_dispensed' => 4,
                'notes' => 'Stok hanya cukup sebagian.',
            ])
            ->assertRedirect(route('prescriptions'));

        $item->refresh();
        $this->assertSame('partial', $item->status);

        $this->actingAs($user)
            ->from(route('prescriptions'))
            ->post(route('prescription-items.close-remaining', $item), [
                'closure_status' => 'external',
                'closure_reason' => 'Sisa dibeli di apotek luar.',
            ])
            ->assertRedirect(route('prescriptions'));

        $item->refresh();
        $prescription->refresh();
        $visit->refresh();
        $invoice = $visit->invoice()->with('items')->first();

        $this->assertSame('partial_external', $item->status);
        $this->assertSame('external', $item->closed_remaining_status);
        $this->assertSame('Sisa dibeli di apotek luar.', $item->closed_remaining_reason);
        $this->assertSame('dispensed', $prescription->status);
        $this->assertSame('ready_for_checkout', $visit->care_stage);
        $this->assertNotNull($invoice);
        $this->assertCount(2, $invoice->items);
        $this->assertTrue($invoice->items->contains(fn ($invoiceItem) => $invoiceItem->item_type === 'consultation'));
        $this->assertTrue($invoice->items->contains(fn ($invoiceItem) => $invoiceItem->item_type === 'medication' && (float) $invoiceItem->quantity === 4.0));
    }
}
