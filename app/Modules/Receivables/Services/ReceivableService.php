<?php

namespace App\Modules\Receivables\Services;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Receivable;
use App\Services\AuditLogService;
use App\Services\CashierShiftSessionService;
use App\Services\ClinicalBillingService;
use App\Services\NotificationCenterService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceivableService
{
    private const DEFAULT_DUE_DAYS = 7;

    public function __construct(
        private readonly ClinicalBillingService $clinicalBillingService,
        private readonly CashierShiftSessionService $cashierShiftSessionService,
        private readonly AuditLogService $auditLogService,
        private readonly NotificationCenterService $notificationCenterService,
    ) {
    }

    public function getIndexData(array $filters): array
    {
        $this->syncStatuses();

        $filters = [
            'search' => trim((string) ($filters['search'] ?? '')),
            'branch' => filled($filters['branch'] ?? null) ? (string) ($filters['branch']) : '',
            'status' => (string) ($filters['status'] ?? ''),
            'date' => (string) ($filters['date'] ?? ''),
        ];

        return [
            'filters' => $filters,
            'receivables' => $this->table($filters),
            'branchOptions' => Branch::query()->orderBy('name')->get(['id', 'name', 'code']),
            'paymentMethodOptions' => PaymentMethod::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'type', 'is_cash']),
            'activeShift' => $this->cashierShiftSessionService->activeShift(),
            'defaultDueDate' => now()->addDays(self::DEFAULT_DUE_DAYS)->toDateString(),
            'defaultPayerType' => 'self_pay',
            'abilities' => [
                'view' => auth()->check(),
                'extend' => auth()->check(),
                'settle' => auth()->check(),
                'cancel' => auth()->user()?->hasAnyRole(['super-admin', 'clinic-admin']) ?? false,
            ],
        ];
    }

    public function openFromInvoice(Invoice $invoice, array $payload = []): Receivable
    {
        $invoice->loadMissing(['patient', 'patientBranchRecord', 'branch', 'receivable']);

        if (! in_array($invoice->status, ['unpaid', 'partial_paid'], true)) {
            throw ValidationException::withMessages([
                'invoice' => 'Hanya invoice dengan outstanding aktif yang bisa dijadikan receivable tempo.',
            ]);
        }

        if ($invoice->receivable && in_array($invoice->receivable->status, ['open', 'overdue'], true)) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoice ini sudah memiliki receivable aktif.',
            ]);
        }

        $dueDate = Carbon::parse($payload['due_date'] ?? now()->addDays(self::DEFAULT_DUE_DAYS)->toDateString())->toDateString();

        return DB::transaction(function () use ($invoice, $payload, $dueDate): Receivable {
            $receivable = $invoice->receivable ?? new Receivable([
                'invoice_id' => $invoice->id,
            ]);

            $payerType = $payload['payer_type'] ?? 'self_pay';
            $payerName = $payerType === 'corporate'
                ? trim((string) ($payload['payer_name'] ?? ''))
                : null;

            $receivable->fill([
                'branch_id' => $invoice->branch_id,
                'patient_id' => $invoice->patient_id,
                'patient_branch_record_id' => $invoice->patient_branch_record_id,
                'status' => $this->statusForDueDate($dueDate),
                'due_date' => $dueDate,
                'opened_at' => now(),
                'opened_by_user_id' => auth()->id(),
                'extended_at' => null,
                'extended_by_user_id' => null,
                'extension_reason' => null,
                'settled_at' => null,
                'settled_by_user_id' => null,
                'cancelled_at' => null,
                'cancelled_by_user_id' => null,
                'cancel_reason' => null,
                'notes' => $payload['notes'] ?? null,
            ]);
            $receivable->save();

            $invoice->update([
                'payer_type' => $payerType,
                'payer_name' => $payerName,
                'payer_meta' => $payerType === 'corporate'
                    ? [
                        'company_name' => $payerName,
                        'company_contact_person' => $payload['payer_contact_person'] ?? null,
                        'company_phone' => $payload['payer_phone'] ?? null,
                    ]
                    : null,
                'notes' => $payload['notes'] ?? $invoice->notes,
            ]);

            $fresh = $receivable->fresh($this->relations());

            $this->auditLogService->log(
                'receivables',
                'opened',
                $fresh,
                'Invoice diubah menjadi receivable tempo.',
                [],
                $fresh->toArray(),
                [
                    'invoice_id' => $invoice->id,
                    'branch_id' => $invoice->branch_id,
                ],
            );

            $this->notificationCenterService->notifyRoles(
                ['super-admin', 'clinic-admin', 'cashier', 'front-office'],
                [
                    'title' => 'Receivable opened',
                    'message' => sprintf(
                        'Invoice %s dibuka sebagai tempo %s sampai %s.',
                        $invoice->invoice_no,
                        $payerType === 'corporate' ? 'perusahaan' : 'self-pay',
                        Carbon::parse($dueDate)->format('d M Y')
                    ),
                    'action_url' => route('receivables') . '?search=' . urlencode($invoice->invoice_no),
                    'action_label' => 'Open receivables',
                    'module' => 'receivables',
                    'level' => 'warning',
                    'meta' => [
                        'invoice_id' => $invoice->id,
                        'receivable_id' => $receivable->id,
                        'branch_id' => $invoice->branch_id,
                    ],
                ],
            );

            return $fresh;
        });
    }

    public function extend(Receivable $receivable, array $payload): Receivable
    {
        $this->ensureEditable($receivable);
        $before = $receivable->fresh($this->relations())?->toArray() ?? [];
        $dueDate = Carbon::parse($payload['due_date'])->toDateString();

        $receivable->update([
            'status' => $this->statusForDueDate($dueDate),
            'due_date' => $dueDate,
            'extended_at' => now(),
            'extended_by_user_id' => auth()->id(),
            'extension_reason' => $payload['extension_reason'],
            'notes' => $payload['notes'] ?? $receivable->notes,
        ]);

        $fresh = $receivable->fresh($this->relations());

        $this->auditLogService->log(
            'receivables',
            'extended',
            $fresh,
            'Receivable diperpanjang jatuh temponya.',
            $before,
            $fresh->toArray(),
        );

        $this->notificationCenterService->notifyUsers(
            [$fresh->openedBy, auth()->user()],
            [
                'title' => 'Receivable extended',
                'message' => sprintf('Receivable untuk invoice %s diperpanjang sampai %s.', $fresh->invoice?->invoice_no, $fresh->due_date?->format('d M Y')),
                'action_url' => route('receivables') . '?search=' . urlencode($fresh->invoice?->invoice_no ?? ''),
                'action_label' => 'Open receivables',
                'module' => 'receivables',
                'level' => 'info',
                'meta' => [
                    'receivable_id' => $fresh->id,
                    'branch_id' => $fresh->branch_id,
                ],
            ],
        );

        return $fresh;
    }

    public function settle(Receivable $receivable, array $payload): Invoice
    {
        $this->ensureEditable($receivable);
        $before = $receivable->fresh($this->relations())?->toArray() ?? [];
        $shift = $this->cashierShiftSessionService->requireActiveShift();

        $invoice = $this->clinicalBillingService->pay($receivable->invoice()->firstOrFail(), [
            ...$payload,
            'cashier_shift_id' => $shift->id,
        ]);

        $freshReceivable = $receivable->fresh($this->relations());

        $this->auditLogService->log(
            'receivables',
            $invoice->status === 'paid' ? 'settled' : 'payment_recorded',
            $freshReceivable,
            $invoice->status === 'paid'
                ? 'Receivable dilunasi penuh melalui kasir.'
                : 'Pembayaran cicilan receivable berhasil dicatat.',
            $before,
            $freshReceivable?->toArray() ?? [],
            [
                'invoice_id' => $invoice->id,
                'cashier_shift_id' => $shift->id,
                'amount' => $payload['amount'] ?? null,
            ],
        );

        $this->notificationCenterService->notifyUsers(
            [$freshReceivable?->openedBy, auth()->user()],
            [
                'title' => 'Receivable settled',
                'message' => $invoice->status === 'paid'
                    ? sprintf('Receivable untuk invoice %s sudah lunas.', $invoice->invoice_no)
                    : sprintf('Pembayaran cicilan untuk invoice %s berhasil dicatat.', $invoice->invoice_no),
                'action_url' => route('receivables') . '?search=' . urlencode($invoice->invoice_no),
                'action_label' => 'Open receivables',
                'module' => 'receivables',
                'level' => $invoice->status === 'paid' ? 'success' : 'info',
                'meta' => [
                    'receivable_id' => $receivable->id,
                    'invoice_id' => $invoice->id,
                    'branch_id' => $invoice->branch_id,
                ],
            ],
        );

        return $invoice->fresh(['receivable', 'paymentMethod', 'cashierShift.counter', 'payments.paymentMethod', 'payments.cashierShift.counter', 'payments.paidBy']);
    }

    public function cancel(Receivable $receivable, array $payload): Receivable
    {
        $this->ensureEditable($receivable);
        $before = $receivable->fresh($this->relations())?->toArray() ?? [];

        $receivable->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by_user_id' => auth()->id(),
            'cancel_reason' => $payload['cancel_reason'],
            'notes' => $payload['notes'] ?? $receivable->notes,
        ]);

        $fresh = $receivable->fresh($this->relations());

        $this->auditLogService->log(
            'receivables',
            'cancelled',
            $fresh,
            'Receivable dibatalkan oleh admin.',
            $before,
            $fresh->toArray(),
        );

        $this->notificationCenterService->notifyUsers(
            [$fresh->openedBy],
            [
                'title' => 'Receivable cancelled',
                'message' => sprintf('Receivable untuk invoice %s dibatalkan oleh admin.', $fresh->invoice?->invoice_no),
                'action_url' => route('receivables') . '?search=' . urlencode($fresh->invoice?->invoice_no ?? ''),
                'action_label' => 'Open receivables',
                'module' => 'receivables',
                'level' => 'danger',
                'meta' => [
                    'receivable_id' => $fresh->id,
                    'branch_id' => $fresh->branch_id,
                ],
            ],
        );

        return $fresh;
    }

    public function syncStatuses(): void
    {
        Receivable::query()
            ->whereIn('status', ['open', 'overdue'])
            ->get()
            ->each(function (Receivable $receivable): void {
                $expectedStatus = $this->statusForDueDate($receivable->due_date?->toDateString() ?? now()->toDateString());

                if ($receivable->status !== $expectedStatus) {
                    $receivable->update([
                        'status' => $expectedStatus,
                    ]);
                }
            });
    }

    private function table(array $filters): LengthAwarePaginator
    {
        return Receivable::query()
            ->with($this->relations())
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->whereHas('invoice', fn (Builder $invoiceQuery) => $invoiceQuery->where('invoice_no', 'like', '%' . $filters['search'] . '%'))
                        ->orWhereHas('invoice', fn (Builder $invoiceQuery) => $invoiceQuery->where('payer_name', 'like', '%' . $filters['search'] . '%'))
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
            ->when($filters['date'] !== '', fn (Builder $query) => $query->whereDate('due_date', $filters['date']))
            ->orderByRaw("CASE status WHEN 'overdue' THEN 1 WHEN 'open' THEN 2 WHEN 'settled' THEN 3 WHEN 'cancelled' THEN 4 ELSE 5 END")
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();
    }

    /**
     * @return list<string>
     */
    private function relations(): array
    {
        return [
            'invoice:id,invoice_no,status,payer_type,payer_name,payer_meta,total_amount,paid_amount,paid_at,payment_method_id,cashier_shift_id',
            'invoice.payments:id,invoice_id,payment_method_id,cashier_shift_id,amount,payment_date,paid_by_user_id,payment_reference,notes',
            'invoice.payments.paymentMethod:id,name,code,type,is_cash',
            'invoice.payments.cashierShift:id,shift_code,counter_id',
            'invoice.payments.cashierShift.counter:id,name,code',
            'invoice.payments.paidBy:id,name',
            'invoice.paymentMethod:id,name,code,type,is_cash',
            'invoice.cashierShift:id,shift_code,counter_id',
            'invoice.cashierShift.counter:id,name,code',
            'branch:id,name,code',
            'patient:id,full_name,phone',
            'patientBranchRecord:id,medical_record_no',
            'openedBy:id,name',
            'extendedBy:id,name',
            'settledBy:id,name',
            'cancelledBy:id,name',
        ];
    }

    private function ensureEditable(Receivable $receivable): void
    {
        if (! in_array($receivable->status, ['open', 'overdue'], true)) {
            throw ValidationException::withMessages([
                'receivable' => 'Hanya receivable open atau overdue yang bisa diproses.',
            ]);
        }
    }

    private function statusForDueDate(string $dueDate): string
    {
        return Carbon::parse($dueDate)->toDateString() < now()->toDateString()
            ? 'overdue'
            : 'open';
    }
}
