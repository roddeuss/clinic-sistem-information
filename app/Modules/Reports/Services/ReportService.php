<?php

namespace App\Modules\Reports\Services;

use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\LaboratoryOrder;
use App\Models\MedicineBatch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Receivable;
use App\Models\VisitRegistration;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function getIndexData(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $range = $this->dateRange($filters);
        $branchId = $filters['branch'] !== '' ? (int) $filters['branch'] : null;
        $detailDataset = $this->detailDataset($filters['dataset'], $range['from'], $range['to'], $branchId);

        return [
            'filters' => $filters,
            'datasetOptions' => $this->datasetOptions(),
            'branchOptions' => Branch::query()->orderBy('name')->get(['id', 'code', 'name']),
            'summary' => $this->summary($range['from'], $range['to'], $branchId),
            'visitByBranch' => $this->visitByBranch($range['from'], $range['to'], $branchId),
            'sectionVisitBreakdown' => $this->sectionVisitBreakdown($range['from'], $range['to'], $branchId),
            'doctorVisitBreakdown' => $this->doctorVisitBreakdown($range['from'], $range['to'], $branchId),
            'invoiceStatusSummary' => $this->invoiceStatusSummary($range['from'], $range['to'], $branchId),
            'cashierShiftSummary' => $this->cashierShiftSummary($range['from'], $range['to'], $branchId),
            'inventorySummary' => $this->inventorySummary($branchId),
            'stockSnapshot' => $this->stockSnapshot($branchId),
            'procurementSummary' => $this->procurementSummary($range['from'], $range['to'], $branchId),
            'purchaseOrderStatusSummary' => $this->purchaseOrderStatusSummary($range['from'], $range['to'], $branchId),
            'detailDataset' => $detailDataset,
        ];
    }

    public function exportData(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $range = $this->dateRange($filters);
        $branchId = $filters['branch'] !== '' ? (int) $filters['branch'] : null;
        $detailDataset = $this->detailDataset($filters['dataset'], $range['from'], $range['to'], $branchId);
        $branch = $branchId ? Branch::query()->find($branchId, ['code', 'name']) : null;

        return [
            'filters' => $filters,
            'dataset' => $detailDataset,
            'exported_at' => now(),
            'branch_label' => $branch ? trim($branch->code . ' - ' . $branch->name) : 'Semua branch',
        ];
    }

    private function normalizeFilters(array $filters): array
    {
        $today = CarbonImmutable::now();
        $dateFrom = filled($filters['date_from'] ?? null)
            ? (string) $filters['date_from']
            : $today->startOfMonth()->toDateString();
        $dateTo = filled($filters['date_to'] ?? null)
            ? (string) $filters['date_to']
            : $today->toDateString();

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $dataset = (string) ($filters['dataset'] ?? 'visits');

        if (! array_key_exists($dataset, $this->datasetOptions())) {
            $dataset = 'visits';
        }

        return [
            'branch' => filled($filters['branch'] ?? null) ? (string) $filters['branch'] : '',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'dataset' => $dataset,
        ];
    }

    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable}
     */
    private function dateRange(array $filters): array
    {
        return [
            'from' => CarbonImmutable::parse($filters['date_from'])->startOfDay(),
            'to' => CarbonImmutable::parse($filters['date_to'])->endOfDay(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function datasetOptions(): array
    {
        return [
            'visits' => 'Visit details',
            'invoices' => 'Invoice details',
            'receivables' => 'Receivable details',
            'inventory' => 'Inventory details',
            'procurement' => 'Procurement details',
            'diagnostics' => 'Diagnostics details',
        ];
    }

    /**
     * @return array{key:string,title:string,description:string,columns:array<int, string>,rows:Collection<int, array<string, mixed>>}
     */
    private function detailDataset(string $dataset, CarbonImmutable $from, CarbonImmutable $to, ?int $branchId): array
    {
        return match ($dataset) {
            'invoices' => [
                'key' => 'invoices',
                'title' => 'Invoice Details',
                'description' => 'Daftar invoice, status settlement, payment method, dan nilai tagihan per visit.',
                'columns' => ['Invoice No', 'Issued Date', 'Branch', 'Payer Type', 'Payer', 'MR No', 'Status', 'Payment Method', 'Total', 'Paid', 'Outstanding', 'Receivable'],
                'rows' => $this->invoiceDetails($from, $to, $branchId),
            ],
            'receivables' => [
                'key' => 'receivables',
                'title' => 'Receivable Details',
                'description' => 'Piutang self-pay tempo, jatuh tempo, dan status pelunasannya.',
                'columns' => ['Invoice No', 'Branch', 'Payer Type', 'Payer', 'MR No', 'Due Date', 'Status', 'Outstanding', 'Opened By', 'Settled/Cancelled'],
                'rows' => $this->receivableDetails($from, $to, $branchId),
            ],
            'inventory' => [
                'key' => 'inventory',
                'title' => 'Inventory Details',
                'description' => 'Detail batch aktif, stok tersedia, nilai stok, supplier, dan status expiry.',
                'columns' => ['Medicine', 'Branch', 'Batch', 'Expiry', 'Qty Available', 'Base Unit', 'Purchase Cost', 'Stock Value', 'Supplier', 'Status'],
                'rows' => $this->inventoryDetails($branchId),
            ],
            'procurement' => [
                'key' => 'procurement',
                'title' => 'Procurement Details',
                'description' => 'Ringkasan purchase order, penerimaan, progress, dan supplier pada periode laporan.',
                'columns' => ['PO No', 'Order Date', 'Branch', 'Supplier', 'Status', 'Total PO', 'Received Value', 'Receipt Count', 'Approved At'],
                'rows' => $this->procurementDetails($from, $to, $branchId),
            ],
            'diagnostics' => [
                'key' => 'diagnostics',
                'title' => 'Diagnostics Details',
                'description' => 'Order diagnostics/lab/radiology, provider type, review status, dan hasil ringkas.',
                'columns' => ['Order ID', 'Visit Date', 'Branch', 'Patient', 'MR No', 'Diagnostic', 'Category', 'Provider', 'Status', 'Summary'],
                'rows' => $this->diagnosticDetails($from, $to, $branchId),
            ],
            default => [
                'key' => 'visits',
                'title' => 'Visit Details',
                'description' => 'Detail kunjungan per patient, branch, section, doctor, dan tahap care stage.',
                'columns' => ['Visit Date', 'Branch', 'Section', 'Patient', 'MR No', 'Doctor', 'Visit Type', 'Registration Status', 'Care Stage', 'Queue No'],
                'rows' => $this->visitDetails($from, $to, $branchId),
            ],
        };
    }

    /**
     * @return array<string, float|int>
     */
    private function summary(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId): array
    {
        $visitBase = VisitRegistration::query()
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween('visit_date', [$from->toDateString(), $to->toDateString()]);

        $paidRevenue = (float) Invoice::query()
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('paid_amount');

        $unpaidInvoices = Invoice::query()
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->whereIn('status', ['unpaid', 'partial_paid'])
            ->whereBetween(DB::raw('COALESCE(issued_at, created_at)'), [$from, $to])
            ->count();

        $unpaidAmount = (float) Invoice::query()
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->whereIn('status', ['unpaid', 'partial_paid'])
            ->whereBetween(DB::raw('COALESCE(issued_at, created_at)'), [$from, $to])
            ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as outstanding_total')
            ->value('outstanding_total');

        $stockValue = (float) MedicineBatch::query()
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->where('is_active', true)
            ->where('quantity_available', '>', 0)
            ->selectRaw('COALESCE(SUM(quantity_available * purchase_cost), 0) as total_value')
            ->value('total_value');

        return [
            'visits_total' => (int) (clone $visitBase)->count(),
            'same_day_visits' => (int) (clone $visitBase)->where('visit_type', 'same_day')->count(),
            'booking_visits' => (int) (clone $visitBase)->where('visit_type', 'booking')->count(),
            'emergency_visits' => (int) (clone $visitBase)->where('visit_type', 'emergency')->count(),
            'paid_revenue' => $paidRevenue,
            'unpaid_invoices' => $unpaidInvoices,
            'unpaid_amount' => $unpaidAmount,
            'stock_value' => $stockValue,
            'pending_po_approval' => PurchaseOrder::query()
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->where('status', 'submitted')
                ->count(),
        ];
    }

    private function visitByBranch(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId): Collection
    {
        return VisitRegistration::query()
            ->join('branches', 'branches.id', '=', 'visit_registrations.branch_id')
            ->when($branchId !== null, fn ($query) => $query->where('visit_registrations.branch_id', $branchId))
            ->whereBetween('visit_registrations.visit_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('branches.id', 'branches.code', 'branches.name')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get([
                'branches.code as branch_code',
                'branches.name as branch_name',
                DB::raw('COUNT(*) as total_visits'),
            ]);
    }

    private function sectionVisitBreakdown(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId): Collection
    {
        return VisitRegistration::query()
            ->join('sections', 'sections.id', '=', 'visit_registrations.section_id')
            ->when($branchId !== null, fn ($query) => $query->where('visit_registrations.branch_id', $branchId))
            ->whereBetween('visit_registrations.visit_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('sections.id', 'sections.name', 'sections.code')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(8)
            ->get([
                'sections.name as section_name',
                'sections.code as section_code',
                DB::raw('COUNT(*) as total_visits'),
            ]);
    }

    private function doctorVisitBreakdown(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId): Collection
    {
        return VisitRegistration::query()
            ->join('doctors', 'doctors.id', '=', 'visit_registrations.doctor_id')
            ->when($branchId !== null, fn ($query) => $query->where('visit_registrations.branch_id', $branchId))
            ->whereBetween('visit_registrations.visit_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('doctors.id', 'doctors.full_name')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(8)
            ->get([
                'doctors.full_name as doctor_name',
                DB::raw('COUNT(*) as total_visits'),
            ]);
    }

    private function invoiceStatusSummary(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId): Collection
    {
        return Invoice::query()
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween(DB::raw('COALESCE(issued_at, created_at)'), [$from, $to])
            ->groupBy('status')
            ->orderBy('status')
            ->get([
                'status',
                DB::raw('COUNT(*) as total_invoices'),
                DB::raw('COALESCE(SUM(total_amount), 0) as total_amount'),
            ]);
    }

    private function cashierShiftSummary(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId): Collection
    {
        return CashierShift::query()
            ->with(['branch:id,code,name', 'counter:id,code,name', 'user:id,name'])
            ->withCount('invoices')
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween('opened_at', [$from, $to])
            ->orderByDesc('opened_at')
            ->limit(8)
            ->get();
    }

    /**
     * @return array<string, float|int>
     */
    private function inventorySummary(?int $branchId): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $base = MedicineBatch::query()
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->where('is_active', true)
            ->where('quantity_available', '>', 0);

        return [
            'active_batches' => (int) (clone $base)->count(),
            'expired_batches' => (int) (clone $base)->whereNotNull('expired_at')->whereDate('expired_at', '<', $today)->count(),
            'expiring_30_days' => (int) (clone $base)->whereBetween('expired_at', [$today->toDateString(), $today->addDays(30)->toDateString()])->count(),
            'quarantined_batches' => (int) (clone $base)->whereNotNull('quarantined_at')->count(),
            'stock_quantity' => (float) (clone $base)->sum('quantity_available'),
        ];
    }

    private function stockSnapshot(?int $branchId): Collection
    {
        return MedicineBatch::query()
            ->join('medicines', 'medicines.id', '=', 'medicine_batches.medicine_id')
            ->when($branchId !== null, fn ($query) => $query->where('medicine_batches.branch_id', $branchId))
            ->where('medicine_batches.is_active', true)
            ->where('medicine_batches.quantity_available', '>', 0)
            ->groupBy('medicines.id', 'medicines.code', 'medicines.name', 'medicines.base_unit')
            ->orderByRaw('SUM(medicine_batches.quantity_available) ASC')
            ->limit(8)
            ->get([
                'medicines.code as medicine_code',
                'medicines.name as medicine_name',
                'medicines.base_unit',
                DB::raw('SUM(medicine_batches.quantity_available) as total_available'),
                DB::raw('MIN(medicine_batches.expired_at) as nearest_expired_at'),
                DB::raw('COALESCE(SUM(medicine_batches.quantity_available * medicine_batches.purchase_cost), 0) as stock_value'),
            ]);
    }

    /**
     * @return array<string, float|int>
     */
    private function procurementSummary(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId): array
    {
        return [
            'purchase_orders_total' => (int) PurchaseOrder::query()
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()])
                ->count(),
            'purchase_orders_value' => (float) PurchaseOrder::query()
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()])
                ->sum('total_amount'),
            'goods_receipts_total' => (int) GoodsReceipt::query()
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->where('status', '!=', 'cancelled')
                ->whereBetween('received_at', [$from->toDateString(), $to->toDateString()])
                ->count(),
            'goods_receipts_value' => (float) GoodsReceipt::query()
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->where('status', '!=', 'cancelled')
                ->whereBetween('received_at', [$from->toDateString(), $to->toDateString()])
                ->sum('total_amount'),
            'purchase_returns_total' => (int) PurchaseReturn::query()
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->where('status', 'completed')
                ->whereBetween('return_date', [$from->toDateString(), $to->toDateString()])
                ->count(),
            'purchase_returns_value' => (float) PurchaseReturn::query()
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->where('status', 'completed')
                ->whereBetween('return_date', [$from->toDateString(), $to->toDateString()])
                ->sum('total_amount'),
        ];
    }

    private function purchaseOrderStatusSummary(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId): Collection
    {
        return PurchaseOrder::query()
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('status')
            ->orderBy('status')
            ->get([
                'status',
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('COALESCE(SUM(total_amount), 0) as total_amount'),
            ]);
    }

    private function visitDetails(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId): Collection
    {
        return VisitRegistration::query()
            ->with(['branch:id,code,name', 'section:id,name', 'patient:id,full_name', 'patientBranchRecord:id,medical_record_no', 'doctor:id,full_name', 'queueTicket:id,visit_registration_id,queue_number'])
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween('visit_date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn (VisitRegistration $visit) => [
                'Visit Date' => $visit->visit_date?->format('Y-m-d') ?? '-',
                'Branch' => trim(($visit->branch?->code ?? '-') . ' - ' . ($visit->branch?->name ?? '-')),
                'Section' => $visit->section?->name ?? '-',
                'Patient' => $visit->patient?->full_name ?? '-',
                'MR No' => $visit->patientBranchRecord?->medical_record_no ?? '-',
                'Doctor' => $visit->doctor?->full_name ?? '-',
                'Visit Type' => $visit->visit_type,
                'Registration Status' => $visit->registration_status,
                'Care Stage' => $visit->care_stage,
                'Queue No' => $visit->queueTicket?->queue_number ?? '-',
            ]);
    }

    private function invoiceDetails(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId): Collection
    {
        return Invoice::query()
            ->with(['branch:id,code,name', 'patient:id,full_name', 'patientBranchRecord:id,medical_record_no', 'paymentMethod:id,name', 'receivable'])
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween(DB::raw('COALESCE(issued_at, created_at)'), [$from, $to])
            ->orderByDesc(DB::raw('COALESCE(issued_at, created_at)'))
            ->limit(500)
            ->get()
            ->map(fn (Invoice $invoice) => [
                'Invoice No' => $invoice->invoice_no,
                'Issued Date' => optional($invoice->issued_at)->format('Y-m-d H:i') ?? '-',
                'Branch' => trim(($invoice->branch?->code ?? '-') . ' - ' . ($invoice->branch?->name ?? '-')),
                'Payer Type' => $invoice->payer_type,
                'Payer' => $invoice->payer_type === 'corporate'
                    ? ($invoice->payer_name ?? '-')
                    : ($invoice->patient?->full_name ?? '-'),
                'MR No' => $invoice->patientBranchRecord?->medical_record_no ?? '-',
                'Status' => $invoice->status,
                'Payment Method' => $invoice->paymentMethod?->name ?? '-',
                'Total' => (float) $invoice->total_amount,
                'Paid' => (float) $invoice->paid_amount,
                'Outstanding' => max(0, (float) $invoice->total_amount - (float) $invoice->paid_amount),
                'Receivable' => $invoice->receivable?->status ?? '-',
            ]);
    }

    private function receivableDetails(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId): Collection
    {
        return Receivable::query()
            ->with(['invoice:id,invoice_no,total_amount,paid_amount,payer_type,payer_name', 'branch:id,code,name', 'patient:id,full_name', 'patientBranchRecord:id,medical_record_no', 'openedBy:id,name', 'settledBy:id,name', 'cancelledBy:id,name'])
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween(DB::raw('COALESCE(opened_at, created_at)'), [$from, $to])
            ->orderByDesc(DB::raw('COALESCE(opened_at, created_at)'))
            ->limit(500)
            ->get()
            ->map(fn (Receivable $receivable) => [
                'Invoice No' => $receivable->invoice?->invoice_no ?? '-',
                'Branch' => trim(($receivable->branch?->code ?? '-') . ' - ' . ($receivable->branch?->name ?? '-')),
                'Payer Type' => $receivable->invoice?->payer_type ?? 'self_pay',
                'Payer' => $receivable->invoice?->payer_type === 'corporate'
                    ? ($receivable->invoice?->payer_name ?? '-')
                    : ($receivable->patient?->full_name ?? '-'),
                'MR No' => $receivable->patientBranchRecord?->medical_record_no ?? '-',
                'Due Date' => $receivable->due_date?->format('Y-m-d') ?? '-',
                'Status' => $receivable->status,
                'Outstanding' => max(0, (float) ($receivable->invoice?->total_amount ?? 0) - (float) ($receivable->invoice?->paid_amount ?? 0)),
                'Opened By' => $receivable->openedBy?->name ?? '-',
                'Settled/Cancelled' => $receivable->settledBy?->name ?? $receivable->cancelledBy?->name ?? '-',
            ]);
    }

    private function inventoryDetails(?int $branchId): Collection
    {
        return MedicineBatch::query()
            ->with(['medicine:id,code,name,base_unit', 'branch:id,code,name', 'supplier:id,name'])
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->where('is_active', true)
            ->orderBy('expired_at')
            ->orderBy('medicine_id')
            ->limit(500)
            ->get()
            ->map(function (MedicineBatch $batch): array {
                $status = 'active';

                if ($batch->expired_at && $batch->expired_at->isPast()) {
                    $status = 'expired';
                } elseif ($batch->quarantined_at) {
                    $status = 'quarantined';
                } elseif ((float) $batch->quantity_available <= 0) {
                    $status = 'empty';
                }

                return [
                    'Medicine' => trim(($batch->medicine?->code ?? '-') . ' - ' . ($batch->medicine?->name ?? '-')),
                    'Branch' => trim(($batch->branch?->code ?? '-') . ' - ' . ($batch->branch?->name ?? '-')),
                    'Batch' => $batch->batch_number,
                    'Expiry' => $batch->expired_at?->format('Y-m-d') ?? '-',
                    'Qty Available' => (float) $batch->quantity_available,
                    'Base Unit' => $batch->medicine?->base_unit ?? '-',
                    'Purchase Cost' => (float) ($batch->purchase_cost ?? 0),
                    'Stock Value' => round((float) $batch->quantity_available * (float) ($batch->purchase_cost ?? 0), 2),
                    'Supplier' => $batch->supplier?->name ?? $batch->supplier_name ?? '-',
                    'Status' => $status,
                ];
            });
    }

    private function procurementDetails(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId): Collection
    {
        return PurchaseOrder::query()
            ->with(['branch:id,code,name', 'supplier:id,code,name', 'goodsReceipts:id,purchase_order_id,total_amount,status', 'approvedBy:id,name'])
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn (PurchaseOrder $purchaseOrder) => [
                'PO No' => $purchaseOrder->po_no,
                'Order Date' => $purchaseOrder->order_date?->format('Y-m-d') ?? '-',
                'Branch' => trim(($purchaseOrder->branch?->code ?? '-') . ' - ' . ($purchaseOrder->branch?->name ?? '-')),
                'Supplier' => trim(($purchaseOrder->supplier?->code ?? '-') . ' - ' . ($purchaseOrder->supplier?->name ?? '-')),
                'Status' => $purchaseOrder->status,
                'Total PO' => (float) $purchaseOrder->total_amount,
                'Received Value' => (float) $purchaseOrder->goodsReceipts->where('status', '!=', 'cancelled')->sum('total_amount'),
                'Receipt Count' => (int) $purchaseOrder->goodsReceipts->where('status', '!=', 'cancelled')->count(),
                'Approved At' => optional($purchaseOrder->approved_at)->format('Y-m-d H:i') ?? '-',
            ]);
    }

    private function diagnosticDetails(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId): Collection
    {
        return LaboratoryOrder::query()
            ->with(['branch:id,code,name', 'visitRegistration.patient:id,full_name', 'visitRegistration.patientBranchRecord:id,medical_record_no', 'laboratoryTest:id,name,diagnostic_category', 'results:id,laboratory_order_id,parameter_name'])
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween('ordered_at', [$from, $to])
            ->orderByDesc('ordered_at')
            ->limit(500)
            ->get()
            ->map(fn (LaboratoryOrder $order) => [
                'Order ID' => $order->id,
                'Visit Date' => $order->visitRegistration?->visit_date?->format('Y-m-d') ?? '-',
                'Branch' => trim(($order->branch?->code ?? '-') . ' - ' . ($order->branch?->name ?? '-')),
                'Patient' => $order->visitRegistration?->patient?->full_name ?? '-',
                'MR No' => $order->visitRegistration?->patientBranchRecord?->medical_record_no ?? '-',
                'Diagnostic' => $order->laboratoryTest?->name ?? '-',
                'Category' => $order->laboratoryTest?->diagnostic_category ?? 'laboratory',
                'Provider' => $order->provider_type,
                'Status' => $order->status,
                'Summary' => $order->result_summary ?: ($order->results->first()?->parameter_name ?? '-'),
            ]);
    }
}
