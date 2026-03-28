@php
    $defaultVisitId = (string) ($visitOptions->first()?->id ?? '');
    $defaultTestId = (string) ($testOptions->first()?->id ?? '');
    $testModalOpen = $errors->any() && in_array(old('form_context'), ['lab-test-create', 'lab-test-update'], true);
    $testModalMode = old('form_context') === 'lab-test-update' ? 'update' : 'create';
    $orderModalOpen = $errors->any() && in_array(old('form_context'), ['lab-order-create', 'lab-order-update'], true);
    $orderModalMode = old('form_context') === 'lab-order-update' ? 'update' : 'create';
    $testModalForm = [
        'id' => old('entity_id'),
        'code' => old('code', ''),
        'name' => old('name', ''),
        'diagnostic_category' => old('diagnostic_category', 'laboratory'),
        'sample_type' => old('sample_type', ''),
        'default_provider_type' => old('default_provider_type', 'internal'),
        'result_entry_mode' => old('result_entry_mode', 'structured'),
        'description' => old('description', ''),
        'parameter_lines' => old('parameter_lines', ''),
        'internal_prices' => $branchOptions->mapWithKeys(fn ($branch) => [(string) $branch->id => old("internal_prices.{$branch->id}", '')])->all(),
        'external_prices' => $branchOptions->mapWithKeys(fn ($branch) => [(string) $branch->id => old("external_prices.{$branch->id}", '')])->all(),
        'is_active' => (int) old('is_active', 1) === 1,
    ];
    $orderModalForm = [
        'id' => old('entity_id'),
        'visit_registration_id' => (string) old('visit_registration_id', $defaultVisitId),
        'laboratory_test_id' => (string) old('laboratory_test_id', $defaultTestId),
        'provider_type' => old('provider_type', 'internal'),
        'status' => old('status', 'ordered'),
        'partner_name' => old('partner_name', ''),
        'external_reference_no' => old('external_reference_no', ''),
        'result_attachment_path' => old('result_attachment_path', ''),
        'result_summary' => old('result_summary', ''),
        'result_impression' => old('result_impression', ''),
        'result_lines' => old('result_lines', ''),
        'notes' => old('notes', ''),
    ];
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Diagnostics" />

    <div x-data="{
        testModalOpen: @js($testModalOpen),
        orderModalOpen: @js($orderModalOpen),
        testMode: @js($testModalMode),
        orderMode: @js($orderModalMode),
        testStoreAction: @js(route('laboratory-tests.store')),
        testUpdateBase: @js(url('/laboratory/tests')),
        orderStoreAction: @js(route('laboratory-orders.store')),
        orderUpdateBase: @js(url('/laboratory/orders')),
        testForm: @js($testModalForm),
        orderForm: @js($orderModalForm),
        emptyTest() { return { id:null, code:'', name:'', diagnostic_category:'laboratory', sample_type:'', default_provider_type:'internal', result_entry_mode:'structured', description:'', parameter_lines:'', internal_prices:@js($branchOptions->mapWithKeys(fn ($branch) => [(string) $branch->id => ''])->all()), external_prices:@js($branchOptions->mapWithKeys(fn ($branch) => [(string) $branch->id => ''])->all()), is_active:true }; },
        emptyOrder() { return { id:null, visit_registration_id:@js($defaultVisitId), laboratory_test_id:@js($defaultTestId), provider_type:'internal', status:'ordered', partner_name:'', external_reference_no:'', result_attachment_path:'', result_summary:'', result_impression:'', result_lines:'', notes:'' }; },
        openCreateTest() { this.testMode = 'create'; this.testForm = this.emptyTest(); this.testModalOpen = true; },
        openEditTest(payload) { this.testMode = 'update'; this.testForm = payload; this.testModalOpen = true; },
        openCreateOrder() { this.orderMode = 'create'; this.orderForm = this.emptyOrder(); this.orderModalOpen = true; },
        openEditOrder(payload) { this.orderMode = 'update'; this.orderForm = payload; this.orderModalOpen = true; },
    }" @keydown.escape.window="testModalOpen = false; orderModalOpen = false" class="space-y-6">
        @if (session('status'))
            <x-ui.alert variant="success" :message="session('status')" />
        @endif
        @if ($errors->any())
            <x-ui.alert variant="error" :message="$errors->first()" />
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Diagnostics / Penunjang</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">Satu modul untuk laboratory, radiology, dan support diagnostics dengan hasil structured, narrative, attachment, serta tarif per branch yang langsung nyambung ke invoice.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    @if ($abilities['create'])
                        <x-ui.button type="button" x-on:click="openCreateTest()">Tambah Test</x-ui.button>
                        <button type="button" x-on:click="openCreateOrder()" @disabled($visitOptions->isEmpty() || $testOptions->isEmpty()) class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">Tambah Order</button>
                    @endif
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('laboratory') }}" class="grid gap-4 md:grid-cols-[1.1fr_220px_180px_180px_180px_180px_auto]">
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari patient, test, partner, reference" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <select name="branch" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Semua branch</option>@foreach ($branchOptions as $branchOption)<option value="{{ $branchOption->id }}" @selected($filters['branch'] === (string) $branchOption->id)>{{ $branchOption->code }} - {{ $branchOption->name }}</option>@endforeach</select>
                <select name="provider_type" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Semua provider</option><option value="internal" @selected($filters['provider_type'] === 'internal')>Internal</option><option value="external" @selected($filters['provider_type'] === 'external')>External</option></select>
                <select name="status" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Semua order</option><option value="ordered" @selected($filters['status'] === 'ordered')>Ordered</option><option value="sample_collected" @selected($filters['status'] === 'sample_collected')>Sample collected</option><option value="processing" @selected($filters['status'] === 'processing')>Processing</option><option value="sent_to_partner" @selected($filters['status'] === 'sent_to_partner')>Sent to partner</option><option value="resulted" @selected($filters['status'] === 'resulted')>Resulted</option><option value="reviewed" @selected($filters['status'] === 'reviewed')>Reviewed</option><option value="cancelled" @selected($filters['status'] === 'cancelled')>Cancelled</option></select>
                <select name="test_status" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Semua master</option><option value="active" @selected($filters['test_status'] === 'active')>Active</option><option value="inactive" @selected($filters['test_status'] === 'inactive')>Inactive</option></select>
                <input type="date" name="date" value="{{ $filters['date'] }}" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <div class="flex gap-3"><x-ui.button type="submit" variant="outline">Filter</x-ui.button><a href="{{ route('laboratory') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">Reset</a></div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">Laboratory tests</h2><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan {{ $tests->firstItem() ?? 0 }} - {{ $tests->lastItem() ?? 0 }} dari {{ $tests->total() }} test.</p></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]"><tr><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Test</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Parameters</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Branch pricing</th><th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th></tr></thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($tests as $test)
                            @php
                                $parameterText = $test->parameters->map(fn ($parameter) => implode('|', array_filter([$parameter->code, $parameter->name, $parameter->unit, $parameter->reference_range], fn ($value) => $value !== null && $value !== '')))->implode("\n");
                                $testPayload = [
                                    'id' => $test->id,
                                    'code' => $test->code,
                                    'name' => $test->name,
                                    'diagnostic_category' => $test->diagnostic_category,
                                    'sample_type' => $test->sample_type ?? '',
                                    'default_provider_type' => $test->default_provider_type,
                                    'result_entry_mode' => $test->result_entry_mode,
                                    'description' => $test->description ?? '',
                                    'parameter_lines' => $parameterText,
                                    'internal_prices' => $branchOptions->mapWithKeys(fn ($branch) => [(string) $branch->id => (string) ($test->branchPrices->firstWhere('branch_id', $branch->id)?->internal_price ?? '')])->all(),
                                    'external_prices' => $branchOptions->mapWithKeys(fn ($branch) => [(string) $branch->id => (string) ($test->branchPrices->firstWhere('branch_id', $branch->id)?->external_price ?? '')])->all(),
                                    'is_active' => $test->is_active,
                                ];
                            @endphp
                            <tr class="align-top">
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><div class="font-medium text-gray-900 dark:text-white">{{ $test->name }}</div><div class="mt-1">{{ $test->code }} | {{ $test->diagnostic_category }} | {{ $test->default_provider_type }}</div><div class="mt-1 text-xs text-gray-400">{{ $test->sample_type ?: 'Sample type belum diisi' }} | {{ $test->result_entry_mode }}</div></td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $test->parameters->pluck('name')->implode(', ') ?: ($test->result_entry_mode === 'narrative' ? 'Narrative result' : '-') }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">@foreach ($branchOptions as $branchOption) @php $price = $test->branchPrices->firstWhere('branch_id', $branchOption->id); @endphp <div class="flex items-center justify-between gap-3"><span>{{ $branchOption->code }}</span><span>I {{ $price && $price->internal_price !== null ? number_format((float) $price->internal_price, 0, ',', '.') : '-' }} / E {{ $price && $price->external_price !== null ? number_format((float) $price->external_price, 0, ',', '.') : '-' }}</span></div> @endforeach</td>
                                <td class="px-6 py-4"><div class="flex justify-end gap-2">@if ($abilities['edit'])<x-ui.icon-button title="Edit test" data-payload='@json($testPayload)' x-on:click='openEditTest(JSON.parse($el.dataset.payload))'><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button>@endif @if ($abilities['delete'])<form method="POST" action="{{ route('laboratory-tests.delete', $test) }}" onsubmit="return confirm('Hapus master test ini?')">@csrf<x-ui.icon-button type="submit" variant="danger" title="Hapus test"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 7H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 7L8 18.5C8.06066 19.3284 8.75104 19.9643 9.58166 19.9643H14.4183C15.249 19.9643 15.9393 19.3284 16 18.5L17 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9 7V5.75C9 5.05964 9.55964 4.5 10.25 4.5H13.75C14.4404 4.5 15 5.05964 15 5.75V7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></form>@endif</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada lab test.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">{{ $tests->links() }}</div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">Laboratory orders</h2><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan {{ $orders->firstItem() ?? 0 }} - {{ $orders->lastItem() ?? 0 }} dari {{ $orders->total() }} order.</p></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]"><tr><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Visit</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Test</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Result</th><th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th></tr></thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($orders as $order)
                            @php
                                $resultText = $order->results->map(fn ($result) => implode('|', array_filter([$result->parameter_code, $result->parameter_name, $result->value, $result->unit, $result->reference_range, $result->result_flag, $result->notes], fn ($value) => $value !== null && $value !== '')))->implode("\n");
                                $orderPayload = [
                                    'id' => $order->id,
                                    'visit_registration_id' => (string) $order->visit_registration_id,
                                    'laboratory_test_id' => (string) $order->laboratory_test_id,
                                    'provider_type' => $order->provider_type,
                                    'status' => $order->status,
                                    'partner_name' => $order->partner_name ?? '',
                                    'external_reference_no' => $order->external_reference_no ?? '',
                                    'result_attachment_path' => $order->result_attachment_path ?? '',
                                    'result_summary' => $order->result_summary ?? '',
                                    'result_impression' => $order->result_impression ?? '',
                                    'result_lines' => $resultText,
                                    'notes' => $order->notes ?? '',
                                ];
                            @endphp
                            <tr class="align-top">
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $order->visitRegistration?->patient?->full_name }}<div class="mt-1 text-xs text-gray-400">{{ $order->visitRegistration?->patientBranchRecord?->medical_record_no }} | {{ $order->branch?->code }}</div></td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $order->laboratoryTest?->name }}<div class="mt-1 text-xs text-gray-400">{{ $order->laboratoryTest?->diagnostic_category }} | {{ $order->provider_type }} | Rp {{ number_format((float) $order->unit_price, 0, ',', '.') }}</div>@if($order->partner_name)<div class="mt-1 text-xs text-gray-400">{{ $order->partner_name }}</div>@endif</td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><div>{{ $order->status }}</div><div class="mt-1 text-xs text-gray-400">{{ $order->results->count() }} structured result | {{ $order->laboratoryTest?->result_entry_mode }}</div>@if($order->result_summary)<div class="mt-1 text-xs text-gray-400">{{ \Illuminate\Support\Str::limit($order->result_summary, 80) }}</div>@endif @if($order->resultedBy)<div class="mt-1 text-xs text-gray-400">Resulted by {{ $order->resultedBy->name }}</div>@endif @if($order->reviewedBy)<div class="mt-1 text-xs text-gray-400">Reviewed by {{ $order->reviewedBy->name }}</div>@endif @if($order->external_reference_no)<div class="mt-1 text-xs text-gray-400">Ref {{ $order->external_reference_no }}</div>@endif</td>
                                <td class="px-6 py-4"><div class="flex justify-end gap-2">@if ($abilities['edit'])<x-ui.icon-button title="Edit order" data-payload='@json($orderPayload)' x-on:click='openEditOrder(JSON.parse($el.dataset.payload))'><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button>@endif @if ($abilities['print'] && $order->status !== 'cancelled')<a href="{{ route('laboratory-orders.request-print', $order) }}" target="_blank" rel="noopener" title="Print lab request" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M7 8V4.75C7 4.33579 7.33579 4 7.75 4H16.25C16.6642 4 17 4.33579 17 4.75V8" stroke="currentColor" stroke-width="1.5"/><path d="M6.5 18.5H17.5C18.3284 18.5 19 17.8284 19 17V12C19 11.1716 18.3284 10.5 17.5 10.5H6.5C5.67157 10.5 5 11.1716 5 12V17C5 17.8284 5.67157 18.5 6.5 18.5Z" stroke="currentColor" stroke-width="1.5"/><path d="M8 14.5H16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M8 17H13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></a>@endif @if ($abilities['print'] && $order->status === 'reviewed')<a href="{{ route('laboratory-orders.result-print', $order) }}" target="_blank" rel="noopener" title="Print lab result" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-emerald-200 bg-white text-emerald-600 transition hover:bg-emerald-50 hover:text-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-emerald-500/20 dark:bg-gray-900 dark:text-emerald-300 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-200"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M7 8V4.75C7 4.33579 7.33579 4 7.75 4H16.25C16.6642 4 17 4.33579 17 4.75V8" stroke="currentColor" stroke-width="1.5"/><path d="M6.5 18.5H17.5C18.3284 18.5 19 17.8284 19 17V12C19 11.1716 18.3284 10.5 17.5 10.5H6.5C5.67157 10.5 5 11.1716 5 12V17C5 17.8284 5.67157 18.5 6.5 18.5Z" stroke="currentColor" stroke-width="1.5"/><path d="M8 14.5H16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M8 17H11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></a>@endif @if ($abilities['delete'])<form method="POST" action="{{ route('laboratory-orders.delete', $order) }}" onsubmit="return confirm('Hapus order lab ini?')">@csrf<x-ui.icon-button type="submit" variant="danger" title="Hapus order"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 7H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 7L8 18.5C8.06066 19.3284 8.75104 19.9643 9.58166 19.9643H14.4183C15.249 19.9643 15.9393 19.3284 16 18.5L17 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9 7V5.75C9 5.05964 9.55964 4.5 10.25 4.5H13.75C14.4404 4.5 15 5.05964 15 5.75V7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></form>@endif</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada order lab.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">{{ $orders->links() }}</div>
        </section>

        <x-ui.modal show="testModalOpen" maxWidth="4xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800 flex items-start justify-between gap-4"><div><h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="testMode === 'create' ? 'Tambah Diagnostic Test' : 'Update Diagnostic Test'"></h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Parameter ditulis satu baris satu parameter dengan format CODE|NAME|UNIT|RANGE. Untuk radiology atau support narrative, parameter bisa dikosongkan.</p></div><x-ui.icon-button title="Tutup modal" x-on:click="testModalOpen = false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></div>
            <form method="POST" x-bind:action="testMode === 'create' ? testStoreAction : `${testUpdateBase}/${testForm.id}`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" x-bind:value="testMode === 'create' ? 'lab-test-create' : 'lab-test-update'">
                <input type="hidden" name="entity_id" x-bind:value="testForm.id">
                <div class="grid gap-5 md:grid-cols-2">
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Code</label><input x-model="testForm.code" type="text" name="code" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label><input x-model="testForm.name" type="text" name="name" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label><select x-model="testForm.diagnostic_category" name="diagnostic_category" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="laboratory">laboratory</option><option value="radiology">radiology</option><option value="other_support">other_support</option></select></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Sample type</label><input x-model="testForm.sample_type" type="text" name="sample_type" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Default provider</label><select x-model="testForm.default_provider_type" name="default_provider_type" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="internal">internal</option><option value="external">external</option></select></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Result mode</label><select x-model="testForm.result_entry_mode" name="result_entry_mode" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="structured">structured</option><option value="narrative">narrative</option><option value="hybrid">hybrid</option></select></div>
                </div>
                <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label><textarea x-model="testForm.description" name="description" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div>
                <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Parameter lines</label><textarea x-model="testForm.parameter_lines" name="parameter_lines" rows="6" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 font-mono text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea><div class="mt-1 text-xs text-gray-400">Kosongkan untuk test dengan hasil narrative penuh seperti radiology report.</div></div>
                <div class="grid gap-4 md:grid-cols-2">@foreach ($branchOptions as $branchOption)<div class="grid gap-4 md:grid-cols-2"><div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $branchOption->code }} internal</label><input x-model="testForm.internal_prices['{{ $branchOption->id }}']" type="number" step="0.01" min="0" name="internal_prices[{{ $branchOption->id }}]" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div><div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $branchOption->code }} external</label><input x-model="testForm.external_prices['{{ $branchOption->id }}']" type="number" step="0.01" min="0" name="external_prices[{{ $branchOption->id }}]" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div></div>@endforeach</div>
                <div><input type="hidden" name="is_active" x-bind:value="testForm.is_active ? 1 : 0"><label class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300"><input x-model="testForm.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20"><span>Active</span></label></div>
                <div class="flex justify-end gap-3"><x-ui.button type="button" variant="outline" x-on:click="testModalOpen = false">Batal</x-ui.button><x-ui.button type="submit" x-text="testMode === 'create' ? 'Simpan Test' : 'Update Test'"></x-ui.button></div>
            </form>
        </x-ui.modal>

        <x-ui.modal show="orderModalOpen" maxWidth="4xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800 flex items-start justify-between gap-4"><div><h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="orderMode === 'create' ? 'Tambah Diagnostic Order' : 'Update Diagnostic Order'"></h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Hasil bisa structured per parameter, narrative summary, atau attachment hasil partner.</p></div><x-ui.icon-button title="Tutup modal" x-on:click="orderModalOpen = false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></div>
            <form method="POST" x-bind:action="orderMode === 'create' ? orderStoreAction : `${orderUpdateBase}/${orderForm.id}`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" x-bind:value="orderMode === 'create' ? 'lab-order-create' : 'lab-order-update'">
                <input type="hidden" name="entity_id" x-bind:value="orderForm.id">
                <div class="grid gap-5 md:grid-cols-2">
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Visit</label><select x-model="orderForm.visit_registration_id" name="visit_registration_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">@foreach ($visitOptions as $visitOption)<option value="{{ $visitOption->id }}">{{ $visitOption->patient?->full_name }} | {{ $visitOption->patientBranchRecord?->medical_record_no }} | {{ $visitOption->branch?->code }}</option>@endforeach</select></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Test</label><select x-model="orderForm.laboratory_test_id" name="laboratory_test_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">@foreach ($testOptions as $testOption)<option value="{{ $testOption->id }}">{{ $testOption->code }} - {{ $testOption->name }}</option>@endforeach</select></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Provider type</label><select x-model="orderForm.provider_type" name="provider_type" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="internal">internal</option><option value="external">external</option></select></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label><select x-model="orderForm.status" name="status" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="ordered">ordered</option><option value="sample_collected">sample_collected</option><option value="processing">processing</option><option value="sent_to_partner">sent_to_partner</option><option value="resulted">resulted</option><option value="reviewed">reviewed</option><option value="cancelled">cancelled</option></select></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Partner name</label><input x-model="orderForm.partner_name" type="text" name="partner_name" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">External reference</label><input x-model="orderForm.external_reference_no" type="text" name="external_reference_no" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                </div>
                <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Attachment path / reference</label><input x-model="orderForm.result_attachment_path" type="text" name="result_attachment_path" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                <div class="grid gap-5 md:grid-cols-2">
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Result summary</label><textarea x-model="orderForm.result_summary" name="result_summary" rows="5" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Impression</label><textarea x-model="orderForm.result_impression" name="result_impression" rows="5" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div>
                </div>
                <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Result lines (structured)</label><textarea x-model="orderForm.result_lines" name="result_lines" rows="6" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 font-mono text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div>
                <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label><textarea x-model="orderForm.notes" name="notes" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div>
                <div class="flex justify-end gap-3"><x-ui.button type="button" variant="outline" x-on:click="orderModalOpen = false">Batal</x-ui.button><x-ui.button type="submit" x-text="orderMode === 'create' ? 'Simpan Order' : 'Update Order'"></x-ui.button></div>
            </form>
        </x-ui.modal>
    </div>
@endsection

