<?php

namespace Tests\Feature\Finance;

use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\Clinic;
use App\Models\Counter;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientBranchRecord;
use App\Models\PaymentMethod;
use App\Models\Section;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VisitRegistration;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceManagementTest extends TestCase
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

    public function test_clinic_admin_can_open_finance_and_master_pages(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $this->actingAs($user)->get(route('billing'))->assertOk()->assertSee('Sales Invoices');
        $this->actingAs($user)->get(route('payment-methods'))->assertOk()->assertSee('Payment Methods');
        $this->actingAs($user)->get(route('cashier-shifts'))->assertOk()->assertSee('Cashier Shifts');
        $this->actingAs($user)->get(route('product-categories'))->assertOk()->assertSee('Product Categories');
        $this->actingAs($user)->get(route('suppliers'))->assertOk()->assertSee('Suppliers');
    }

    public function test_clinic_admin_can_create_supplier_with_npwp_and_payment_term(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $this->actingAs($user)
            ->post(route('suppliers.store'), [
                'code' => 'SUP-TAX-001',
                'name' => 'PT Supplier Pajak',
                'contact_person' => 'Nadia',
                'phone' => '021-888000',
                'email' => 'tax@supplier.local',
                'npwp' => '09.111.222.3-444.000',
                'payment_term_days' => 30,
                'address' => 'Jakarta',
                'notes' => 'Supplier with tax data',
                'is_active' => true,
            ])
            ->assertRedirect();

        $supplier = Supplier::query()->where('code', 'SUP-TAX-001')->firstOrFail();

        $this->assertSame('09.111.222.3-444.000', $supplier->npwp);
        $this->assertSame(30, $supplier->payment_term_days);
    }

    public function test_cashier_can_pay_invoice_with_active_shift_and_payment_method(): void
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

        $shift = CashierShift::query()->create([
            'user_id' => $user->id,
            'counter_id' => $scenario['counter']->id,
            'branch_id' => $scenario['branch']->id,
            'shift_code' => 'SHIFT-TEST-0001',
            'status' => 'open',
            'opening_balance' => 100000,
            'opened_at' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->post(route('billing.pay', $scenario['invoice']), [
                'payment_method_id' => $paymentMethod->id,
                'payment_reference' => 'TEST-REF-001',
                'notes' => 'Paid during automated test.',
            ])
            ->assertRedirect();

        $invoice = $scenario['invoice']->fresh();

        $this->assertSame('paid', $invoice->status);
        $this->assertSame($paymentMethod->id, $invoice->payment_method_id);
        $this->assertSame($shift->id, $invoice->cashier_shift_id);
        $this->assertSame('TEST-REF-001', $invoice->payment_reference);
    }

    public function test_clinic_admin_can_print_and_void_invoice(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $scenario = $this->createInvoiceScenario();

        $this->actingAs($user)
            ->get(route('billing.print', $scenario['invoice']))
            ->assertOk()
            ->assertSee($scenario['invoice']->invoice_no)
            ->assertSee('Print / Save PDF');

        $this->assertNotNull($scenario['invoice']->fresh()->printed_at);

        $this->actingAs($user)
            ->post(route('billing.void', $scenario['invoice']), [
                'void_reason' => 'Administrative correction',
                'notes' => 'Voided by clinic admin in test.',
            ])
            ->assertRedirect();

        $this->assertSame('voided', $scenario['invoice']->fresh()->status);
        $this->assertSame('Administrative correction', $scenario['invoice']->fresh()->void_reason);
    }

    public function test_clinic_admin_can_open_paid_thermal_receipt(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $scenario = $this->createInvoiceScenario();
        $paymentMethod = PaymentMethod::query()->create([
            'code' => 'QRIS',
            'name' => 'QRIS',
            'type' => 'digital',
            'description' => 'QRIS payment',
            'is_cash' => false,
            'is_active' => true,
            'sort_order' => 20,
        ]);

        $scenario['invoice']->update([
            'status' => 'paid',
            'payment_method_id' => $paymentMethod->id,
            'paid_amount' => $scenario['invoice']->total_amount,
            'paid_at' => now()->subMinutes(5),
            'paid_by_user_id' => $user->id,
            'payment_reference' => 'QRIS-TEST-001',
        ]);

        $this->actingAs($user)
            ->get(route('billing.receipt', $scenario['invoice']))
            ->assertOk()
            ->assertSee('Receipt Thermal')
            ->assertSee($scenario['invoice']->invoice_no)
            ->assertSee('QRIS');

        $this->assertNotNull($scenario['invoice']->fresh()->printed_at);
    }

    public function test_audit_logs_are_visible_to_clinic_admin_but_forbidden_for_cashier(): void
    {
        $clinicAdmin = User::factory()->create();
        $clinicAdmin->assignRole('clinic-admin');

        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $this->actingAs($clinicAdmin)
            ->get(route('audit-logs'))
            ->assertOk()
            ->assertSee('Audit Logs');

        $this->actingAs($cashier)
            ->get(route('audit-logs'))
            ->assertForbidden();
    }

    /**
     * @return array{clinic: Clinic, branch: Branch, counter: Counter, section: Section, patient: Patient, branchRecord: PatientBranchRecord, visit: VisitRegistration, invoice: Invoice}
     */
    private function createInvoiceScenario(): array
    {
        $clinic = Clinic::query()->firstOrFail();
        $branch = Branch::query()->firstOrFail();

        $counter = Counter::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Counter Test',
            'code' => 'CTR-TST',
            'location' => 'Front desk',
            'description' => 'Counter for finance testing',
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
            'description' => 'Finance testing section',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $patient = Patient::query()->create([
            'full_name' => 'Patient Finance Test',
            'gender' => 'female',
            'date_of_birth' => '1995-04-12',
            'nik' => '3174011234567890',
            'phone' => '081234567899',
            'email' => 'finance.patient@example.com',
            'is_active' => true,
        ]);

        $branchRecord = PatientBranchRecord::query()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'medical_record_no' => 'P-00001',
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
            'invoice_no' => 'INV-TEST-0001',
            'payer_type' => 'self_pay',
            'status' => 'unpaid',
            'subtotal' => 150000,
            'discount_amount' => 0,
            'total_amount' => 150000,
            'issued_at' => now()->subMinutes(20),
        ]);

        $invoice->items()->create([
            'item_type' => 'consultation',
            'description' => 'Consultation - Test Doctor',
            'quantity' => 1,
            'unit_price' => 150000,
            'subtotal' => 150000,
        ]);

        return compact('clinic', 'branch', 'counter', 'section', 'patient', 'branchRecord', 'visit', 'invoice');
    }
}
