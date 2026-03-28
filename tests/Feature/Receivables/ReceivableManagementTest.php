<?php

namespace Tests\Feature\Receivables;

use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\Counter;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientBranchRecord;
use App\Models\PaymentMethod;
use App\Models\Receivable;
use App\Models\Section;
use App\Models\User;
use App\Models\VisitRegistration;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivableManagementTest extends TestCase
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

    public function test_cashier_can_open_extend_and_settle_receivable(): void
    {
        $user = User::factory()->create();
        $user->assignRole('cashier');

        $scenario = $this->createInvoiceScenario();
        $paymentMethod = PaymentMethod::query()->create([
            'code' => 'CASH',
            'name' => 'Cash',
            'type' => 'cash',
            'description' => 'Cash payment',
            'is_cash' => true,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        CashierShift::query()->create([
            'user_id' => $user->id,
            'counter_id' => $scenario['counter']->id,
            'branch_id' => $scenario['branch']->id,
            'shift_code' => 'SHIFT-RCV-0001',
            'status' => 'open',
            'opening_balance' => 100000,
            'opened_at' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->post(route('billing.tempo', $scenario['invoice']), [
                'due_date' => now()->addDays(7)->toDateString(),
                'notes' => 'Tempo test',
            ])
            ->assertRedirect();

        $receivable = Receivable::query()->firstOrFail();
        $this->assertSame('open', $receivable->status);

        $this->actingAs($user)
            ->post(route('receivables.extend', $receivable), [
                'due_date' => now()->addDays(10)->toDateString(),
                'extension_reason' => 'Patient requested more time',
                'notes' => 'Extended in test',
            ])
            ->assertRedirect();

        $this->assertSame(now()->addDays(10)->toDateString(), $receivable->fresh()->due_date?->toDateString());

        $this->actingAs($user)
            ->post(route('receivables.settle', $receivable), [
                'payment_method_id' => $paymentMethod->id,
                'amount' => 175000,
                'payment_reference' => 'RCV-SETTLE-001',
                'notes' => 'Settled in test',
            ])
            ->assertRedirect();

        $this->assertSame('paid', $scenario['invoice']->fresh()->status);
        $this->assertSame('settled', $receivable->fresh()->status);
    }

    public function test_corporate_receivable_can_be_paid_in_installments(): void
    {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $scenario = $this->createInvoiceScenario();

        $paymentMethod = PaymentMethod::query()->create([
            'code' => 'TRF',
            'name' => 'Transfer',
            'type' => 'bank_transfer',
            'description' => 'Bank transfer',
            'is_cash' => false,
            'is_active' => true,
            'sort_order' => 20,
        ]);

        CashierShift::query()->create([
            'user_id' => $cashier->id,
            'counter_id' => $scenario['counter']->id,
            'branch_id' => $scenario['branch']->id,
            'shift_code' => 'SHIFT-RCV-0002',
            'status' => 'open',
            'opening_balance' => 100000,
            'opened_at' => now()->subHour(),
        ]);

        $this->actingAs($cashier)
            ->post(route('billing.tempo', $scenario['invoice']), [
                'due_date' => now()->addDays(7)->toDateString(),
                'payer_type' => 'corporate',
                'payer_name' => 'PT Tempo Sejahtera',
                'payer_contact_person' => 'Ardi Finance',
                'payer_phone' => '021-9999000',
                'notes' => 'Corporate receivable test',
            ])
            ->assertRedirect();

        $receivable = Receivable::query()->firstOrFail();

        $this->assertSame('corporate', $scenario['invoice']->fresh()->payer_type);
        $this->assertSame('PT Tempo Sejahtera', $scenario['invoice']->fresh()->payer_name);

        $this->actingAs($cashier)
            ->post(route('receivables.settle', $receivable), [
                'payment_method_id' => $paymentMethod->id,
                'amount' => 75000,
                'payment_reference' => 'RCV-INSTALL-001',
                'notes' => 'Installment 1',
            ])
            ->assertRedirect();

        $this->assertSame('partial_paid', $scenario['invoice']->fresh()->status);
        $this->assertSame('open', $receivable->fresh()->status);
        $this->assertEquals(75000.0, (float) $scenario['invoice']->fresh()->paid_amount);

        $this->actingAs($cashier)
            ->post(route('receivables.settle', $receivable), [
                'payment_method_id' => $paymentMethod->id,
                'amount' => 100000,
                'payment_reference' => 'RCV-INSTALL-002',
                'notes' => 'Installment 2',
            ])
            ->assertRedirect();

        $this->assertSame('paid', $scenario['invoice']->fresh()->status);
        $this->assertSame('settled', $receivable->fresh()->status);
        $this->assertSame(2, $scenario['invoice']->fresh()->payments()->count());
    }

    /**
     * @return array{branch: Branch, counter: Counter, section: Section, patient: Patient, branchRecord: PatientBranchRecord, visit: VisitRegistration, invoice: Invoice}
     */
    private function createInvoiceScenario(): array
    {
        $branch = Branch::query()->firstOrFail();

        $counter = Counter::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Counter Receivable',
            'code' => 'CRV1',
            'location' => 'Front desk',
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
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $patient = Patient::query()->create([
            'full_name' => 'Patient Receivable',
            'gender' => 'female',
            'date_of_birth' => '1990-01-01',
            'nik' => '3174011234567001',
            'phone' => '081234560001',
            'is_active' => true,
        ]);

        $branchRecord = PatientBranchRecord::query()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'medical_record_no' => 'P-90001',
        ]);

        $visit = VisitRegistration::query()->create([
            'patient_id' => $patient->id,
            'patient_branch_record_id' => $branchRecord->id,
            'branch_id' => $branch->id,
            'counter_id' => $counter->id,
            'section_id' => $section->id,
            'visit_date' => now()->toDateString(),
            'visit_type' => 'same_day',
            'registration_status' => 'registered',
            'care_stage' => 'ready_for_checkout',
            'vital_status' => 'completed',
        ]);

        $invoice = Invoice::query()->create([
            'visit_registration_id' => $visit->id,
            'patient_id' => $patient->id,
            'patient_branch_record_id' => $branchRecord->id,
            'branch_id' => $branch->id,
            'invoice_no' => 'INV-RCV-0001',
            'payer_type' => 'self_pay',
            'status' => 'unpaid',
            'subtotal' => 175000,
            'discount_amount' => 0,
            'total_amount' => 175000,
            'issued_at' => now(),
        ]);

        $invoice->items()->create([
            'item_type' => 'consultation',
            'description' => 'Consultation',
            'quantity' => 1,
            'unit_price' => 175000,
            'subtotal' => 175000,
        ]);

        return compact('branch', 'counter', 'section', 'patient', 'branchRecord', 'visit', 'invoice');
    }
}
