<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\CashierShift;
use App\Models\Counter;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\PaymentMethod;
use App\Models\ProductCategory;
use App\Models\Receivable;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\SystemAlertNotification;
use Illuminate\Database\Seeder;

class FinanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $categories = $this->seedProductCategories();
        $suppliers = $this->seedSuppliers();
        $paymentMethods = $this->seedPaymentMethods();

        $this->attachCategoriesAndSuppliers($categories, $suppliers);

        $shifts = $this->seedCashierShiftHistory();

        $this->attachPaidInvoiceSettlement($paymentMethods, $shifts);
        $this->seedReceivableDemo();
        $this->seedNotificationsDemo();
        $this->seedAuditLogs($paymentMethods, $categories, $suppliers, $shifts);
    }

    private function seedProductCategories(): array
    {
        $definitions = [
            ['code' => 'ANL', 'name' => 'Analgesic', 'description' => 'Pain relief and fever management products.', 'sort_order' => 10],
            ['code' => 'ABT', 'name' => 'Antibiotic', 'description' => 'Antibiotic and anti-infective items.', 'sort_order' => 20],
            ['code' => 'GAS', 'name' => 'Gastrointestinal', 'description' => 'Digestive and gastric therapy products.', 'sort_order' => 30],
            ['code' => 'RES', 'name' => 'Respiratory', 'description' => 'Respiratory symptom and airway support items.', 'sort_order' => 40],
            ['code' => 'CMP', 'name' => 'Compound Ingredient', 'description' => 'Materials commonly used for capsule compounds.', 'sort_order' => 50],
        ];

        $categories = [];

        foreach ($definitions as $definition) {
            $category = ProductCategory::query()->updateOrCreate(
                ['code' => $definition['code']],
                $definition + ['is_active' => true],
            );

            $categories[$definition['code']] = $category;
        }

        return $categories;
    }

    private function seedSuppliers(): array
    {
        $definitions = [
            [
                'code' => 'SUP-001',
                'name' => 'PT Demo Farmasi',
                'contact_person' => 'Rani Putri',
                'phone' => '021-8800001',
                'email' => 'supply@demofarmasi.local',
                'npwp' => '01.234.567.8-901.000',
                'payment_term_days' => 30,
                'address' => 'Jakarta Distribution Center',
                'notes' => 'Primary supplier for demo pharmacy batches.',
            ],
            [
                'code' => 'SUP-002',
                'name' => 'PT Sehat Nusantara',
                'contact_person' => 'Dimas Setiawan',
                'phone' => '021-8800002',
                'email' => 'sales@sehatnusantara.local',
                'npwp' => '02.345.678.9-012.000',
                'payment_term_days' => 21,
                'address' => 'Bandung Medical Hub',
                'notes' => 'Secondary supplier for branch replenishment.',
            ],
            [
                'code' => 'SUP-003',
                'name' => 'CV Medikal Mandiri',
                'contact_person' => 'Linda Sari',
                'phone' => '061-8800003',
                'email' => 'admin@medikalmandiri.local',
                'npwp' => '03.456.789.0-123.000',
                'payment_term_days' => 14,
                'address' => 'Medan Regional Warehouse',
                'notes' => 'Regional supplier for Medan branch.',
            ],
        ];

        $suppliers = [];

        foreach ($definitions as $definition) {
            $supplier = Supplier::query()->updateOrCreate(
                ['code' => $definition['code']],
                $definition + ['is_active' => true],
            );

            $suppliers[$definition['code']] = $supplier;
        }

        return $suppliers;
    }

    private function seedPaymentMethods(): array
    {
        $definitions = [
            ['code' => 'CASH', 'name' => 'Cash', 'type' => 'cash', 'description' => 'Pembayaran tunai di counter kasir.', 'sort_order' => 10, 'is_cash' => true],
            ['code' => 'TRF', 'name' => 'Bank Transfer', 'type' => 'bank_transfer', 'description' => 'Transfer bank manual atau virtual account.', 'sort_order' => 20, 'is_cash' => false],
            ['code' => 'CARD', 'name' => 'Debit / Credit Card', 'type' => 'debit_credit', 'description' => 'Pembayaran via EDC debit atau kartu kredit.', 'sort_order' => 30, 'is_cash' => false],
            ['code' => 'QRIS', 'name' => 'QRIS', 'type' => 'qris', 'description' => 'Pembayaran QRIS klinik.', 'sort_order' => 40, 'is_cash' => false],
        ];

        $paymentMethods = [];

        foreach ($definitions as $definition) {
            $method = PaymentMethod::query()->updateOrCreate(
                ['code' => $definition['code']],
                $definition + ['is_active' => true],
            );

            $paymentMethods[$definition['code']] = $method;
        }

        return $paymentMethods;
    }

    private function attachCategoriesAndSuppliers(array $categories, array $suppliers): void
    {
        $categoryMap = [
            'PCM500' => 'ANL',
            'AMX500' => 'ABT',
            'CTM4' => 'CMP',
            'OMZ20' => 'GAS',
            'ALB60' => 'RES',
        ];

        foreach ($categoryMap as $medicineCode => $categoryCode) {
            $medicine = Medicine::query()->where('code', $medicineCode)->first();

            if (! $medicine || ! isset($categories[$categoryCode])) {
                continue;
            }

            $medicine->update([
                'product_category_id' => $categories[$categoryCode]->id,
            ]);
        }

        $supplierCycle = array_values($suppliers);

        MedicineBatch::query()
            ->orderBy('id')
            ->get()
            ->each(function (MedicineBatch $batch, int $index) use ($supplierCycle): void {
                $supplier = $supplierCycle[$index % count($supplierCycle)] ?? null;

                if (! $supplier) {
                    return;
                }

                $batch->update([
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->name,
                ]);
            });
    }

    private function seedCashierShiftHistory(): array
    {
        $cashier = User::query()->where('email', 'cashier.main@csi.local')->first()
            ?? User::query()->orderBy('id')->first();
        $clinicAdmin = User::query()->where('email', 'clinicadmin@csi.local')->first()
            ?? $cashier;

        $counters = Counter::query()
            ->with('branch')
            ->orderBy('branch_id')
            ->orderBy('sort_order')
            ->get();

        if (! $cashier || $counters->isEmpty()) {
            return [];
        }

        $closedCounter = $counters->first();
        $openCounter = $counters->skip(1)->first() ?? $closedCounter;

        $closedShift = CashierShift::query()->updateOrCreate(
            ['shift_code' => 'SHIFT-DEMO-0001'],
            [
                'user_id' => $cashier->id,
                'counter_id' => $closedCounter->id,
                'branch_id' => $closedCounter->branch_id,
                'status' => 'closed',
                'opening_balance' => 200000,
                'opening_notes' => 'Seed closed shift for finance module preview.',
                'opened_at' => now()->subDay()->setTime(8, 0),
                'closed_at' => now()->subDay()->setTime(15, 30),
                'closing_balance' => 550000,
                'expected_cash_total' => 550000,
                'cash_variance' => 0,
                'closed_by_user_id' => $clinicAdmin?->id,
                'closing_notes' => 'Cashier handover closed without variance.',
            ],
        );

        $openShift = CashierShift::query()->updateOrCreate(
            ['shift_code' => 'SHIFT-DEMO-OPEN'],
            [
                'user_id' => $cashier->id,
                'counter_id' => $openCounter->id,
                'branch_id' => $openCounter->branch_id,
                'status' => 'open',
                'opening_balance' => 150000,
                'opening_notes' => 'Seed open shift for cashier payment testing.',
                'opened_at' => now()->subHours(2),
                'closed_at' => null,
                'closing_balance' => null,
                'expected_cash_total' => null,
                'cash_variance' => null,
                'closed_by_user_id' => null,
                'closing_notes' => null,
            ],
        );

        return [
            'closed' => $closedShift,
            'open' => $openShift,
        ];
    }

    private function attachPaidInvoiceSettlement(array $paymentMethods, array $shifts): void
    {
        $invoice = Invoice::query()
            ->where('status', 'paid')
            ->orderByDesc('id')
            ->first();

        $cashMethod = $paymentMethods['CASH'] ?? null;
        $closedShift = $shifts['closed'] ?? null;

        if (! $invoice || ! $cashMethod || ! $closedShift) {
            return;
        }

        $invoice->update([
            'payment_method_id' => $cashMethod->id,
            'cashier_shift_id' => $closedShift->id,
            'paid_by_user_id' => $closedShift->user_id,
            'payment_reference' => null,
            'printed_at' => now()->subMinutes(5),
        ]);

        InvoicePayment::query()->updateOrCreate(
            [
                'invoice_id' => $invoice->id,
                'payment_date' => $invoice->paid_at ?? now()->subMinutes(30),
            ],
            [
                'payment_method_id' => $cashMethod->id,
                'cashier_shift_id' => $closedShift->id,
                'amount' => $invoice->total_amount,
                'paid_by_user_id' => $closedShift->user_id,
                'payment_reference' => null,
                'notes' => 'Seed full payment record.',
                'meta' => [
                    'payer_type' => $invoice->payer_type,
                    'payer_name' => $invoice->payer_name,
                ],
            ],
        );
    }

    private function seedReceivableDemo(): void
    {
        $invoice = Invoice::query()
            ->where('status', 'unpaid')
            ->whereDoesntHave('receivable')
            ->latest('id')
            ->first();

        $openedBy = User::query()->where('email', 'cashier.main@csi.local')->first()
            ?? User::query()->orderBy('id')->first();

        if (! $invoice || ! $openedBy) {
            return;
        }

        $invoice->update([
            'payer_type' => 'corporate',
            'payer_name' => 'PT Tempo Demo',
            'payer_meta' => [
                'company_name' => 'PT Tempo Demo',
                'company_contact_person' => 'Bagian Finance',
                'company_phone' => '021-7000000',
            ],
            'status' => 'partial_paid',
            'paid_amount' => 50000,
            'payment_reference' => 'SEED-INSTALLMENT-001',
        ]);

        InvoicePayment::query()->updateOrCreate(
            [
                'invoice_id' => $invoice->id,
                'payment_reference' => 'SEED-INSTALLMENT-001',
            ],
            [
                'payment_method_id' => PaymentMethod::query()->where('code', 'TRF')->value('id'),
                'cashier_shift_id' => CashierShift::query()->where('status', 'open')->value('id'),
                'amount' => 50000,
                'payment_date' => now()->subHours(12),
                'paid_by_user_id' => $openedBy->id,
                'notes' => 'Seed installment payment.',
                'meta' => [
                    'payer_type' => 'corporate',
                    'payer_name' => 'PT Tempo Demo',
                ],
            ],
        );

        Receivable::query()->updateOrCreate(
            ['invoice_id' => $invoice->id],
            [
                'branch_id' => $invoice->branch_id,
                'patient_id' => $invoice->patient_id,
                'patient_branch_record_id' => $invoice->patient_branch_record_id,
                'status' => 'open',
                'due_date' => now()->addDays(5)->toDateString(),
                'opened_at' => now()->subDay(),
                'opened_by_user_id' => $openedBy->id,
                'notes' => 'Seed receivable for corporate installment preview.',
            ],
        );
    }

    private function seedNotificationsDemo(): void
    {
        $clinicAdmin = User::query()->where('email', 'clinicadmin@csi.local')->first();
        $cashier = User::query()->where('email', 'cashier.main@csi.local')->first();
        $receivable = Receivable::query()->latest('id')->first();

        if ($clinicAdmin) {
            $clinicAdmin->notify(new SystemAlertNotification([
                'title' => 'PO awaiting approval',
                'message' => 'Seed notification: ada purchase order bernilai tinggi yang menunggu approval.',
                'action_url' => route('purchase-orders'),
                'action_label' => 'Open purchase orders',
                'module' => 'purchase_orders',
                'level' => 'warning',
            ]));
        }

        if ($cashier && $receivable) {
            $cashier->notify(new SystemAlertNotification([
                'title' => 'Receivable opened',
                'message' => sprintf('Seed notification: invoice %s dibuka sebagai tempo.', $receivable->invoice?->invoice_no ?? '-'),
                'action_url' => route('receivables'),
                'action_label' => 'Open receivables',
                'module' => 'receivables',
                'level' => 'info',
            ]));
        }
    }

    private function seedAuditLogs(array $paymentMethods, array $categories, array $suppliers, array $shifts): void
    {
        if (AuditLog::query()->exists()) {
            return;
        }

        $admin = User::query()->where('email', 'clinicadmin@csi.local')->first();
        $invoice = Invoice::query()->where('status', 'paid')->latest('id')->first();
        $branchId = $invoice?->branch_id ?? ($shifts['closed']->branch_id ?? null);

        $entries = [
            [
                'module' => 'payment_methods',
                'action' => 'created',
                'description' => 'Default cash payment method seeded for clinic finance flow.',
                'auditable' => $paymentMethods['CASH'] ?? null,
                'before_data' => null,
                'after_data' => $paymentMethods['CASH']?->toArray(),
                'meta' => ['source' => 'seed'],
            ],
            [
                'module' => 'product_categories',
                'action' => 'created',
                'description' => 'Starter product category seeded for pharmacy grouping.',
                'auditable' => $categories['ANL'] ?? null,
                'before_data' => null,
                'after_data' => $categories['ANL']?->toArray(),
                'meta' => ['source' => 'seed'],
            ],
            [
                'module' => 'suppliers',
                'action' => 'created',
                'description' => 'Primary supplier seeded for inventory batch tracking.',
                'auditable' => $suppliers['SUP-001'] ?? null,
                'before_data' => null,
                'after_data' => $suppliers['SUP-001']?->toArray(),
                'meta' => ['source' => 'seed'],
            ],
            [
                'module' => 'cashier_shifts',
                'action' => 'closed',
                'description' => 'Seed cashier shift closed with zero variance.',
                'auditable' => $shifts['closed'] ?? null,
                'before_data' => null,
                'after_data' => $shifts['closed']?->toArray(),
                'meta' => ['source' => 'seed'],
            ],
            [
                'module' => 'sales_invoices',
                'action' => 'paid',
                'description' => 'Seed invoice marked paid for cashier and audit preview.',
                'auditable' => $invoice,
                'before_data' => null,
                'after_data' => $invoice?->toArray(),
                'meta' => ['source' => 'seed'],
            ],
        ];

        foreach ($entries as $offset => $entry) {
            AuditLog::query()->create([
                'user_id' => $admin?->id,
                'branch_id' => data_get($entry['auditable'], 'branch_id', $branchId),
                'auditable_type' => $entry['auditable']?->getMorphClass(),
                'auditable_id' => $entry['auditable']?->getKey(),
                'module' => $entry['module'],
                'action' => $entry['action'],
                'description' => $entry['description'],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Seeder/Demo',
                'before_data' => $entry['before_data'],
                'after_data' => $entry['after_data'],
                'meta' => $entry['meta'],
                'created_at' => now()->subMinutes(15 - ($offset * 2)),
                'updated_at' => now()->subMinutes(15 - ($offset * 2)),
            ]);
        }
    }
}
