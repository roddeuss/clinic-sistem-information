@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Reports" />

    <div class="space-y-6">
        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Operational Reports</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">Ringkasan kunjungan, revenue, cashier shift, stok aktif, batch expired, dan procurement untuk kontrol operasional klinik sehari-hari.</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-500 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300">
                    <div class="font-medium text-gray-900 dark:text-white">Cakupan laporan awal</div>
                    <div class="mt-1">Visits, sales invoices, cashier shifts, inventory snapshot, dan procurement summary.</div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('reports') }}" class="grid gap-4 md:grid-cols-[240px_220px_220px_220px_auto]">
                <select name="branch" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="">Semua branch</option>
                    @foreach ($branchOptions as $branchOption)
                        <option value="{{ $branchOption->id }}" @selected($filters['branch'] === (string) $branchOption->id)>{{ $branchOption->code }} - {{ $branchOption->name }}</option>
                    @endforeach
                </select>
                <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <select name="dataset" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    @foreach ($datasetOptions as $key => $label)
                        <option value="{{ $key }}" @selected($filters['dataset'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="flex gap-3">
                    <x-ui.button type="submit" variant="outline">Filter</x-ui.button>
                    <a href="{{ route('reports.export-excel', request()->query()) }}" class="inline-flex h-11 items-center rounded-xl border border-emerald-200 px-4 text-sm font-medium text-emerald-700 dark:border-emerald-500/20 dark:text-emerald-300">Export Excel</a>
                    <a href="{{ route('reports.export-pdf', request()->query()) }}" class="inline-flex h-11 items-center rounded-xl border border-brand-200 px-4 text-sm font-medium text-brand-700 dark:border-brand-500/20 dark:text-brand-300">Export PDF</a>
                    <a href="{{ route('reports') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">Reset</a>
                </div>
            </form>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Visits</div>
                <div class="mt-3 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($summary['visits_total']) }}</div>
                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">Same day {{ number_format($summary['same_day_visits']) }} | Booking {{ number_format($summary['booking_visits']) }} | Emergency {{ number_format($summary['emergency_visits']) }}</div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Paid Revenue</div>
                <div class="mt-3 text-2xl font-semibold text-gray-900 dark:text-white">Rp {{ number_format((float) $summary['paid_revenue'], 0, ',', '.') }}</div>
                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">Invoice unpaid {{ number_format($summary['unpaid_invoices']) }} | Outstanding Rp {{ number_format((float) $summary['unpaid_amount'], 0, ',', '.') }}</div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Stock Value</div>
                <div class="mt-3 text-2xl font-semibold text-gray-900 dark:text-white">Rp {{ number_format((float) $summary['stock_value'], 0, ',', '.') }}</div>
                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">Nilai stok aktif saat ini pada branch yang dipilih.</div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Pending PO Approval</div>
                <div class="mt-3 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($summary['pending_po_approval']) }}</div>
                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">PO submitted yang masih menunggu approval admin.</div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Visit By Branch</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Distribusi kunjungan per branch pada periode terpilih.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-white/[0.02]">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Branch</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Visits</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @forelse ($visitByBranch as $row)
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $row->branch_code }} - {{ $row->branch_name }}</td>
                                    <td class="px-6 py-4 text-right text-sm font-medium text-gray-900 dark:text-white">{{ number_format((int) $row->total_visits) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada data visit pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Invoice Status Summary</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ringkasan invoice berdasarkan status settlement.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-white/[0.02]">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Invoices</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @forelse ($invoiceStatusSummary as $row)
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ ucfirst($row->status) }}</td>
                                    <td class="px-6 py-4 text-right text-sm font-medium text-gray-900 dark:text-white">{{ number_format((int) $row->total_invoices) }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-700 dark:text-gray-300">Rp {{ number_format((float) $row->total_amount, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada data invoice pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Top Sections</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Section dengan volume kunjungan tertinggi.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-white/[0.02]">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Section</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Visits</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @forelse ($sectionVisitBreakdown as $row)
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $row->section_name }} <span class="text-xs text-gray-400">{{ $row->section_code }}</span></td>
                                    <td class="px-6 py-4 text-right text-sm font-medium text-gray-900 dark:text-white">{{ number_format((int) $row->total_visits) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada data section pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Top Doctors</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Dokter dengan jumlah visit tertinggi pada periode ini.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-white/[0.02]">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Doctor</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Visits</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @forelse ($doctorVisitBreakdown as $row)
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $row->doctor_name }}</td>
                                    <td class="px-6 py-4 text-right text-sm font-medium text-gray-900 dark:text-white">{{ number_format((int) $row->total_visits) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada data dokter pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Cashier Shift Summary</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Shift kasir yang dibuka dalam periode laporan.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-white/[0.02]">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Shift</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Cashier</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Invoices</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Variance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @forelse ($cashierShiftSummary as $shift)
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $shift->shift_code }}</div>
                                        <div class="mt-1 text-xs text-gray-400">{{ $shift->branch?->code }} | {{ $shift->counter?->code }} | {{ ucfirst($shift->status) }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                        <div>{{ $shift->user?->name }}</div>
                                        <div class="mt-1 text-xs text-gray-400">{{ $shift->opened_at?->format('d M Y H:i') }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm font-medium text-gray-900 dark:text-white">{{ number_format((int) $shift->invoices_count) }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-700 dark:text-gray-300">Rp {{ number_format((float) ($shift->cash_variance ?? 0), 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada cashier shift pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Inventory Snapshot</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Posisi stok aktif, expired batch, dan karantina saat ini.</p>
                </div>
                <div class="grid gap-4 p-6 md:grid-cols-2">
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]"><div class="text-xs uppercase tracking-[0.14em] text-gray-400">Active batches</div><div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ number_format((int) $inventorySummary['active_batches']) }}</div></div>
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]"><div class="text-xs uppercase tracking-[0.14em] text-gray-400">Expired batches</div><div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ number_format((int) $inventorySummary['expired_batches']) }}</div></div>
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]"><div class="text-xs uppercase tracking-[0.14em] text-gray-400">Expiring 30 days</div><div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ number_format((int) $inventorySummary['expiring_30_days']) }}</div></div>
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]"><div class="text-xs uppercase tracking-[0.14em] text-gray-400">Quarantined</div><div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ number_format((int) $inventorySummary['quarantined_batches']) }}</div></div>
                </div>
                <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-800">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total quantity available: <span class="font-medium text-gray-900 dark:text-white">{{ number_format((float) $inventorySummary['stock_quantity'], 2) }}</span></div>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Stock Snapshot</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Item stok aktif dengan quantity tersedia paling rendah lebih dulu.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-white/[0.02]">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Medicine</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Qty</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Value</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Nearest Exp.</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @forelse ($stockSnapshot as $row)
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $row->medicine_code }} - {{ $row->medicine_name }}</div>
                                        <div class="mt-1 text-xs text-gray-400">{{ $row->base_unit ?: '-' }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm font-medium text-gray-900 dark:text-white">{{ number_format((float) $row->total_available, 2) }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-700 dark:text-gray-300">Rp {{ number_format((float) $row->stock_value, 0, ',', '.') }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-700 dark:text-gray-300">{{ $row->nearest_expired_at ? \Illuminate\Support\Carbon::parse($row->nearest_expired_at)->format('d M Y') : '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada stok aktif.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Procurement Summary</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ringkasan purchase order, goods receipt, dan purchase return.</p>
                </div>
                <div class="grid gap-4 p-6 md:grid-cols-2">
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]"><div class="text-xs uppercase tracking-[0.14em] text-gray-400">PO</div><div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ number_format((int) $procurementSummary['purchase_orders_total']) }}</div><div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Rp {{ number_format((float) $procurementSummary['purchase_orders_value'], 0, ',', '.') }}</div></div>
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]"><div class="text-xs uppercase tracking-[0.14em] text-gray-400">Goods Receipt</div><div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ number_format((int) $procurementSummary['goods_receipts_total']) }}</div><div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Rp {{ number_format((float) $procurementSummary['goods_receipts_value'], 0, ',', '.') }}</div></div>
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03] md:col-span-2"><div class="text-xs uppercase tracking-[0.14em] text-gray-400">Purchase Return</div><div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ number_format((int) $procurementSummary['purchase_returns_total']) }}</div><div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Rp {{ number_format((float) $procurementSummary['purchase_returns_value'], 0, ',', '.') }}</div></div>
                </div>
                <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-800">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-white/[0.02]">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">PO Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Count</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                @forelse ($purchaseOrderStatusSummary as $row)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ str_replace('_', ' ', ucfirst($row->status)) }}</td>
                                        <td class="px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">{{ number_format((int) $row->total_orders) }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">Rp {{ number_format((float) $row->total_amount, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada purchase order pada periode ini.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $detailDataset['title'] }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $detailDataset['description'] }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            @foreach ($detailDataset['columns'] as $column)
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($detailDataset['rows'] as $row)
                            <tr>
                                @foreach ($detailDataset['columns'] as $column)
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $row[$column] ?? '-' }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($detailDataset['columns']) }}" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada data detail untuk dataset ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
