<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\LaboratoryOrder;
use App\Models\MedicalRecord;
use App\Models\PaymentMethod;
use App\Models\PrescriptionDispense;
use App\Models\VisitProcedure;
use App\Models\VisitMedicalService;
use App\Models\VisitRegistration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClinicalBillingService
{
    public function __construct(
        private readonly ClinicalWorkflowService $clinicalWorkflowService,
    ) {
    }

    public function syncVisit(VisitRegistration $visit): Invoice
    {
        return DB::transaction(function () use ($visit): Invoice {
            $visit->loadMissing([
                'patient',
                'patientBranchRecord',
                'medicalRecord.doctor',
                'prescription.items.dispenses',
                'visitMedicalServices.medicalService',
                'visitProcedures.procedureMaster',
                'laboratoryOrders.laboratoryTest',
            ]);

            $invoice = $this->ensureInvoice($visit);
            $desiredItems = $this->desiredItems($visit);

            $existingItems = $invoice->items()->get()->keyBy(fn (InvoiceItem $item): string => $this->key($item->source_type, $item->source_id));

            foreach ($desiredItems as $itemKey => $payload) {
                $existing = $existingItems->get($itemKey);

                if ($existing) {
                    $existing->update($payload);
                    continue;
                }

                $invoice->items()->create($payload);
            }

            $desiredKeys = $desiredItems->keys()->all();

            $invoice->items()
                ->whereNotIn(DB::raw("COALESCE(source_type, '') || ':' || COALESCE(CAST(source_id AS TEXT), '')"), $desiredKeys)
                ->delete();

            $invoice->refresh();
            $subtotal = (float) $invoice->items()->sum('subtotal');
            $discount = (float) $invoice->discount_amount;
            $total = max(0, $subtotal - $discount);
            $settlement = $this->determineSettlementState($invoice, $total);

            $invoice->update([
                'status' => $settlement['status'],
                'subtotal' => $subtotal,
                'total_amount' => $total,
                'paid_amount' => $settlement['paid_amount'],
                'paid_at' => $settlement['status'] === 'paid'
                    ? ($invoice->paid_at ?? now())
                    : null,
                'issued_at' => $invoice->issued_at ?? now(),
            ]);

            $this->clinicalWorkflowService->refreshVisit($visit->fresh(['invoice', 'medicalRecord', 'prescription.items', 'visitProcedures', 'laboratoryOrders']));

            return $invoice->fresh(['items']);
        });
    }

    public function pay(Invoice $invoice, array $payload = []): Invoice
    {
        return DB::transaction(function () use ($invoice, $payload): Invoice {
            $invoice->loadMissing(['receivable', 'payments']);

            if ($invoice->status === 'paid') {
                throw ValidationException::withMessages([
                    'invoice' => 'Invoice ini sudah dibayar.',
                ]);
            }

            if ($invoice->status === 'voided') {
                throw ValidationException::withMessages([
                    'invoice' => 'Invoice yang sudah di-void tidak bisa dibayar ulang.',
                ]);
            }

            if ((float) $invoice->total_amount <= 0) {
                throw ValidationException::withMessages([
                    'invoice' => 'Invoice tanpa item tidak bisa ditutup sebagai paid.',
                ]);
            }

            $amount = round((float) ($payload['amount'] ?? $this->outstandingAmount($invoice)), 2);
            $outstandingAmount = $this->outstandingAmount($invoice);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal pembayaran harus lebih besar dari nol.',
                ]);
            }

            if ($amount - $outstandingAmount > 0.0001) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal pembayaran melebihi outstanding invoice.',
                ]);
            }

            $paymentMethod = PaymentMethod::query()
                ->where('is_active', true)
                ->findOrFail($payload['payment_method_id']);

            if (isset($payload['cashier_shift_id']) && $payload['cashier_shift_id']) {
                $shift = \App\Models\CashierShift::query()->findOrFail($payload['cashier_shift_id']);

                if ((int) $shift->branch_id !== (int) $invoice->branch_id) {
                    throw ValidationException::withMessages([
                        'cashier_shift' => 'Cashier shift aktif berasal dari branch yang berbeda dengan invoice ini.',
                    ]);
                }
            }

            $payment = $invoice->payments()->create([
                'payment_method_id' => $paymentMethod->id,
                'cashier_shift_id' => $payload['cashier_shift_id'] ?? null,
                'amount' => $amount,
                'payment_date' => now(),
                'paid_by_user_id' => auth()->id(),
                'payment_reference' => $payload['payment_reference'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'meta' => [
                    'payer_type' => $invoice->payer_type,
                    'payer_name' => $invoice->payer_name,
                ],
            ]);

            $paymentTotal = (float) $invoice->payments()->sum('amount');
            $settlementStatus = $paymentTotal + 0.0001 >= (float) $invoice->total_amount
                ? 'paid'
                : 'partial_paid';

            $invoice->update([
                'status' => $settlementStatus,
                'payment_method_id' => $paymentMethod->id,
                'cashier_shift_id' => $payload['cashier_shift_id'] ?? null,
                'paid_amount' => min((float) $invoice->total_amount, $paymentTotal),
                'paid_at' => $settlementStatus === 'paid' ? now() : null,
                'paid_by_user_id' => $settlementStatus === 'paid' ? auth()->id() : null,
                'payment_reference' => $payload['payment_reference'] ?? null,
                'notes' => $payload['notes'] ?? $invoice->notes,
            ]);

            if ($settlementStatus === 'paid') {
                $invoice->receivable()
                    ->whereIn('status', ['open', 'overdue'])
                    ->update([
                        'status' => 'settled',
                        'settled_at' => now(),
                        'settled_by_user_id' => auth()->id(),
                    ]);
            } elseif ($invoice->receivable && in_array($invoice->receivable->status, ['open', 'overdue'], true)) {
                $invoice->receivable->update([
                    'status' => $this->statusForReceivable($invoice->receivable->due_date?->toDateString()),
                    'settled_at' => null,
                    'settled_by_user_id' => null,
                ]);
            }

            $this->clinicalWorkflowService->refreshVisit($invoice->visitRegistration()->firstOrFail());

            return $invoice->fresh(['items', 'visitRegistration', 'paymentMethod', 'cashierShift', 'receivable', 'payments.paymentMethod', 'payments.cashierShift.counter', 'payments.paidBy']);
        });
    }

    public function void(Invoice $invoice, array $payload = []): Invoice
    {
        return DB::transaction(function () use ($invoice, $payload): Invoice {
            if ($invoice->status === 'voided') {
                throw ValidationException::withMessages([
                    'invoice' => 'Invoice ini sudah di-void.',
                ]);
            }

            $invoice->update([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by_user_id' => auth()->id(),
                'void_reason' => $payload['void_reason'],
                'notes' => $payload['notes'] ?? $invoice->notes,
            ]);

            $invoice->receivable()
                ->whereIn('status', ['open', 'overdue'])
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_by_user_id' => auth()->id(),
                    'cancel_reason' => $payload['void_reason'],
                ]);

            $this->clinicalWorkflowService->refreshVisit($invoice->visitRegistration()->firstOrFail());

            return $invoice->fresh(['items', 'visitRegistration', 'paymentMethod', 'cashierShift', 'voidedBy', 'receivable']);
        });
    }

    public function ensureInvoice(VisitRegistration $visit): Invoice
    {
        $existing = $visit->invoice;

        if ($existing) {
            return $existing;
        }

        return Invoice::query()->create([
            'visit_registration_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'patient_branch_record_id' => $visit->patient_branch_record_id,
            'branch_id' => $visit->branch_id,
            'invoice_no' => $this->nextInvoiceNo(),
            'payer_type' => 'self_pay',
            'payer_name' => null,
            'status' => 'unpaid',
            'issued_at' => now(),
        ]);
    }

    /**
     * @return Collection<string, array<string, mixed>>
     */
    private function desiredItems(VisitRegistration $visit): Collection
    {
        $items = collect();

        $medicalRecord = $visit->medicalRecord;
        if ($medicalRecord && $medicalRecord->status === 'final') {
            $items->put(
                $this->key(MedicalRecord::class, $medicalRecord->id),
                [
                    'source_type' => MedicalRecord::class,
                    'source_id' => $medicalRecord->id,
                    'item_type' => 'consultation',
                    'description' => 'Consultation - ' . ($medicalRecord->doctor?->displayName() ?? 'Doctor'),
                    'quantity' => 1,
                    'unit_price' => $medicalRecord->doctor?->consultation_fee ?? 0,
                    'subtotal' => $medicalRecord->doctor?->consultation_fee ?? 0,
                    'meta' => [
                        'doctor_id' => $medicalRecord->doctor_id,
                        'medical_record_status' => $medicalRecord->status,
                    ],
                ],
            );
        }

        foreach ($visit->visitProcedures as $procedure) {
            if ($procedure->status !== 'completed') {
                continue;
            }

            $items->put(
                $this->key(VisitProcedure::class, $procedure->id),
                [
                    'source_type' => VisitProcedure::class,
                    'source_id' => $procedure->id,
                    'item_type' => 'procedure',
                    'description' => 'Procedure - ' . ($procedure->procedureMaster?->name ?? 'Procedure'),
                    'quantity' => $procedure->quantity,
                    'unit_price' => $procedure->unit_price,
                    'subtotal' => $procedure->subtotal,
                    'meta' => [
                        'procedure_code' => $procedure->procedureMaster?->code,
                        'performed_by_role' => $procedure->performed_by_role,
                    ],
                ],
            );
        }

        foreach ($visit->visitMedicalServices as $service) {
            if ($service->status !== 'completed') {
                continue;
            }

            $items->put(
                $this->key(VisitMedicalService::class, $service->id),
                [
                    'source_type' => VisitMedicalService::class,
                    'source_id' => $service->id,
                    'item_type' => 'service',
                    'description' => 'Service - ' . ($service->medicalService?->name ?? 'Medical Service'),
                    'quantity' => $service->quantity,
                    'unit_price' => $service->unit_price,
                    'subtotal' => $service->subtotal,
                    'meta' => [
                        'service_code' => $service->medicalService?->code,
                        'service_type' => $service->medicalService?->service_type,
                    ],
                ],
            );
        }

        foreach ($visit->laboratoryOrders as $order) {
            if (! $this->shouldBillLabOrder($order)) {
                continue;
            }

            $items->put(
                $this->key(LaboratoryOrder::class, $order->id),
                [
                    'source_type' => LaboratoryOrder::class,
                    'source_id' => $order->id,
                    'item_type' => 'laboratory',
                    'description' => sprintf(
                        'Laboratory - %s (%s)',
                        $order->laboratoryTest?->name ?? 'Test',
                        ucfirst($order->provider_type)
                    ),
                    'quantity' => 1,
                    'unit_price' => $order->unit_price,
                    'subtotal' => $order->unit_price,
                    'meta' => [
                        'provider_type' => $order->provider_type,
                        'partner_name' => $order->partner_name,
                    ],
                ],
            );
        }

        $dispenses = $visit->prescription?->items
            ?->flatMap(fn ($item) => $item->dispenses) ?? collect();

        foreach ($dispenses as $dispense) {
            $items->put(
                $this->key(PrescriptionDispense::class, $dispense->id),
                [
                    'source_type' => PrescriptionDispense::class,
                    'source_id' => $dispense->id,
                    'item_type' => 'medication',
                    'description' => 'Prescription - ' . $dispense->prescriptionItem?->display_name,
                    'quantity' => $dispense->quantity_dispensed,
                    'unit_price' => $dispense->unit_price,
                    'subtotal' => $dispense->subtotal,
                    'meta' => [
                        'item_type' => $dispense->prescriptionItem?->item_type,
                    ],
                ],
            );
        }

        return $items;
    }

    private function shouldBillLabOrder(LaboratoryOrder $order): bool
    {
        if ($order->status === 'cancelled') {
            return false;
        }

        if ($order->provider_type === 'internal') {
            return $order->sample_collected_at !== null;
        }

        return $order->sent_to_partner_at !== null;
    }

    private function key(?string $sourceType, ?int $sourceId): string
    {
        return ($sourceType ?? '') . ':' . ($sourceId ?? '');
    }

    private function nextInvoiceNo(): string
    {
        $today = now()->format('Ymd');

        $lastInvoice = Invoice::query()
            ->where('invoice_no', 'like', 'INV-' . $today . '-%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $lastNumber = 0;

        if ($lastInvoice && preg_match('/(\d+)$/', $lastInvoice->invoice_no, $matches) === 1) {
            $lastNumber = (int) $matches[1];
        }

        return sprintf('INV-%s-%04d', $today, $lastNumber + 1);
    }

    private function determineSettlementState(Invoice $invoice, float $totalAmount): array
    {
        if ($invoice->status === 'voided') {
            return [
                'status' => 'voided',
                'paid_amount' => (float) $invoice->paid_amount,
            ];
        }

        $paymentTotal = $this->paymentTotal($invoice);

        if ($paymentTotal > 0 && $paymentTotal + 0.0001 >= $totalAmount && $totalAmount > 0) {
            return [
                'status' => 'paid',
                'paid_amount' => min($paymentTotal, $totalAmount),
            ];
        }

        if ($paymentTotal > 0) {
            return [
                'status' => 'partial_paid',
                'paid_amount' => min($paymentTotal, $totalAmount),
            ];
        }

        return [
            'status' => 'unpaid',
            'paid_amount' => 0,
        ];
    }

    private function outstandingAmount(Invoice $invoice): float
    {
        return max(0, round((float) $invoice->total_amount - $this->paymentTotal($invoice), 2));
    }

    private function paymentTotal(Invoice $invoice): float
    {
        $paymentTotal = $invoice->relationLoaded('payments')
            ? (float) $invoice->payments->sum('amount')
            : (float) $invoice->payments()->sum('amount');

        if ($paymentTotal <= 0) {
            return (float) $invoice->paid_amount;
        }

        return $paymentTotal;
    }

    private function statusForReceivable(?string $dueDate): string
    {
        if (! $dueDate) {
            return 'open';
        }

        return $dueDate < now()->toDateString() ? 'overdue' : 'open';
    }
}
