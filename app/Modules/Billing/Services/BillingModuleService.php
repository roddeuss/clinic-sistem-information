<?php

namespace App\Modules\Billing\Services;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Modules\Receivables\Services\ReceivableService;
use App\Services\AuditLogService;
use App\Services\CashierShiftSessionService;
use App\Services\ClinicalBillingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class BillingModuleService
{
    public function __construct(
        private readonly ClinicalBillingService $clinicalBillingService,
        private readonly CashierShiftSessionService $cashierShiftSessionService,
        private readonly AuditLogService $auditLogService,
        private readonly ReceivableService $receivableService,
    ) {
    }

    public function getIndexData(array $filters): array
    {
        $filters = [
            'search' => trim((string) ($filters['search'] ?? '')),
            'branch' => filled($filters['branch'] ?? null) ? (string) $filters['branch'] : '',
            'status' => (string) ($filters['status'] ?? ''),
            'date' => (string) ($filters['date'] ?? now()->toDateString()),
        ];

        return [
            'filters' => $filters,
            'invoices' => $this->invoiceTable($filters),
            'branchOptions' => Branch::query()->orderBy('name')->get(['id', 'name', 'code']),
            'paymentMethodOptions' => PaymentMethod::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'type', 'is_cash']),
            'activeShift' => $this->cashierShiftSessionService->activeShift(),
            'defaultReceivableDueDate' => now()->addDays(7)->toDateString(),
            'abilities' => [
                'pay' => auth()->check(),
                'refresh' => auth()->check(),
                'print' => auth()->check(),
                'receipt' => auth()->check(),
                'tempo' => auth()->user()?->hasAnyRole(['super-admin', 'clinic-admin', 'cashier', 'front-office']) ?? false,
                'void' => auth()->user()?->hasAnyRole(['super-admin', 'clinic-admin']) ?? false,
            ],
        ];
    }

    public function refresh(Invoice $invoice): Invoice
    {
        $before = $invoice->fresh(['items'])?->toArray() ?? [];
        $fresh = $this->clinicalBillingService->syncVisit(
            $invoice->visitRegistration()->firstOrFail()
        );

        $this->auditLogService->log(
            'sales_invoices',
            'refreshed',
            $fresh,
            'Invoice disinkronkan ulang dari visit.',
            $before,
            $fresh->fresh(['items'])->toArray(),
        );

        return $fresh;
    }

    public function pay(Invoice $invoice, array $payload = []): Invoice
    {
        $shift = $this->cashierShiftSessionService->requireActiveShift();
        $before = $invoice->fresh(['items', 'paymentMethod', 'cashierShift'])?->toArray() ?? [];

        $fresh = $this->clinicalBillingService->pay($invoice, [
            ...$payload,
            'cashier_shift_id' => $shift->id,
        ]);

        $this->auditLogService->log(
            'sales_invoices',
            'paid',
            $fresh,
            'Invoice dibayar melalui kasir.',
            $before,
            $fresh->fresh(['items', 'paymentMethod', 'cashierShift.counter'])->toArray(),
            [
                'cashier_shift_id' => $shift->id,
                'payment_method_id' => $payload['payment_method_id'] ?? null,
            ],
        );

        return $fresh;
    }

    public function openReceivable(Invoice $invoice, array $payload = []): Invoice
    {
        $before = $invoice->fresh(['items', 'receivable'])?->toArray() ?? [];
        $receivable = $this->receivableService->openFromInvoice($invoice, $payload);
        $fresh = $invoice->fresh(['items', 'receivable']);

        $this->auditLogService->log(
            'sales_invoices',
            'tempo_opened',
            $fresh,
            'Invoice dibuka sebagai receivable tempo.',
            $before,
            $fresh?->toArray() ?? [],
            [
                'receivable_id' => $receivable->id,
                'due_date' => $receivable->due_date?->toDateString(),
            ],
        );

        return $fresh;
    }

    public function void(Invoice $invoice, array $payload = []): Invoice
    {
        $before = $invoice->fresh(['items', 'paymentMethod', 'cashierShift'])?->toArray() ?? [];
        $fresh = $this->clinicalBillingService->void($invoice, $payload);

        $this->auditLogService->log(
            'sales_invoices',
            'voided',
            $fresh,
            'Invoice di-void oleh admin.',
            $before,
            $fresh->fresh(['items', 'paymentMethod', 'cashierShift.counter'])->toArray(),
            [
                'void_reason' => $payload['void_reason'] ?? null,
            ],
        );

        return $fresh;
    }

    public function markPrinted(Invoice $invoice): Invoice
    {
        return $this->markRenderableDocument($invoice, 'printed', 'Invoice dibuka untuk cetak A4.');
    }

    public function markReceiptPrinted(Invoice $invoice): Invoice
    {
        return $this->markRenderableDocument($invoice, 'printed_receipt', 'Receipt thermal dibuka untuk cetak kasir.');
    }

    private function markRenderableDocument(Invoice $invoice, string $action, string $description): Invoice
    {
        $before = $invoice->fresh(['items', 'paymentMethod', 'cashierShift.counter'])?->toArray() ?? [];
        $invoice->update([
            'printed_at' => now(),
        ]);

        $fresh = $invoice->fresh([
            'patient',
            'patientBranchRecord',
            'branch.clinic',
            'paymentMethod',
            'cashierShift.counter',
            'items',
            'visitRegistration.section',
            'paidBy',
            'voidedBy',
        ]);

        $this->auditLogService->log(
            'sales_invoices',
            $action,
            $fresh,
            $description,
            $before,
            $fresh->toArray(),
        );

        return $fresh;
    }

    private function invoiceTable(array $filters): LengthAwarePaginator
    {
        return Invoice::query()
            ->with([
                'patient:id,full_name,phone',
                'patientBranchRecord:id,medical_record_no',
                'branch:id,name,code',
                'visitRegistration.section:id,name,code',
                'paymentMethod:id,name,code,type,is_cash',
                'cashierShift:id,shift_code,counter_id',
                'cashierShift.counter:id,name,code',
                'paidBy:id,name',
                'voidedBy:id,name',
                'receivable',
                'items',
                'payments:id,invoice_id,payment_method_id,cashier_shift_id,amount,payment_date,paid_by_user_id,payment_reference,notes',
                'payments.paymentMethod:id,name,code,type,is_cash',
                'payments.cashierShift:id,shift_code,counter_id',
                'payments.cashierShift.counter:id,name,code',
                'payments.paidBy:id,name',
            ])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('invoice_no', 'like', '%' . $filters['search'] . '%')
                        ->orWhereHas('patient', function (Builder $patientQuery) use ($filters): void {
                            $patientQuery
                                ->where('full_name', 'like', '%' . $filters['search'] . '%')
                                ->orWhere('phone', 'like', '%' . $filters['search'] . '%');
                        })
                        ->orWhereHas('patientBranchRecord', fn (Builder $recordQuery) => $recordQuery->where('medical_record_no', 'like', '%' . $filters['search'] . '%'));
                });
            })
            ->when($filters['branch'] !== '', fn (Builder $query) => $query->where('branch_id', $filters['branch']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['date'] !== '', fn (Builder $query) => $query->whereDate('created_at', $filters['date']))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();
    }
}
