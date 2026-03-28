<section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
    <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Dispensing queue</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Item prescription yang perlu ditindaklanjuti farmasi, lengkap dengan alert klinis, preview stok, dan tindakan penutupan sisa fulfillment.</p>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
            <thead class="bg-gray-50 dark:bg-white/[0.02]">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Patient</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Item</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Fulfillment Preview</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</th>
                    <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                @forelse ($dispensingItems as $item)
                    @php
                        $itemPayload = [
                            'id' => $item->id,
                            'prescription_id' => (string) $item->prescription_id,
                            'item_type' => $item->item_type,
                            'medicine_id' => (string) ($item->medicine_id ?? ''),
                            'display_name' => $item->isCompound() ? $item->display_name : '',
                            'route' => $item->route ?? '',
                            'dose_amount' => (string) ($item->dose_amount ?? ''),
                            'dose_unit' => $item->dose_unit ?? '',
                            'frequency' => $item->frequency ?? '',
                            'duration_days' => (string) ($item->duration_days ?? ''),
                            'instruction' => $item->instruction ?? '',
                            'quantity_prescribed' => (string) $item->quantity_prescribed,
                            'dispense_unit' => $item->dispense_unit ?? '',
                            'weight_snapshot_kg' => (string) ($item->weight_snapshot_kg ?? ''),
                            'status' => $item->status,
                            'notes' => $item->notes ?? '',
                            'compound_ingredients' => $item->compoundIngredients->map(fn ($ingredient) => [
                                'medicine_id' => (string) $ingredient->medicine_id,
                                'quantity_required' => (string) $ingredient->quantity_required,
                                'unit' => $ingredient->unit ?? '',
                            ])->values()->all(),
                        ];
                        $dispensePayload = [
                            'id' => $item->id,
                            'display_name' => $item->display_name,
                            'patient' => $item->prescription?->visitRegistration?->patient?->full_name,
                            'visit_ref' => $item->prescription?->visitRegistration?->patientBranchRecord?->medical_record_no,
                            'remaining' => (float) $item->remaining_quantity,
                            'prescribed' => (float) $item->quantity_prescribed,
                            'dispensed' => (float) $item->dispensed_quantity,
                            'compound' => $item->isCompound(),
                            'preview' => $item->dispensing_preview,
                            'alerts' => $item->safety_alerts,
                        ];
                        $closePayload = [
                            'id' => $item->id,
                            'display_name' => $item->display_name,
                            'patient' => $item->prescription?->visitRegistration?->patient?->full_name,
                            'visit_ref' => $item->prescription?->visitRegistration?->patientBranchRecord?->medical_record_no,
                            'remaining' => (float) $item->remaining_quantity,
                            'dispensed' => (float) $item->dispensed_quantity,
                            'status' => $item->status,
                        ];
                        $pendingOverrideAlerts = collect($item->safety_alerts ?? [])
                            ->filter(fn (array $alert): bool => ($alert['requires_override'] ?? false) === true && ($alert['overridden'] ?? false) === false)
                            ->values();
                        $overridePayload = [
                            'id' => $item->id,
                            'display_name' => $item->display_name,
                            'patient' => $item->prescription?->visitRegistration?->patient?->full_name,
                            'visit_ref' => $item->prescription?->visitRegistration?->patientBranchRecord?->medical_record_no,
                            'alerts' => $pendingOverrideAlerts->all(),
                        ];
                        $itemPayloadJson = e(json_encode($itemPayload));
                        $dispensePayloadJson = e(json_encode($dispensePayload));
                        $closePayloadJson = e(json_encode($closePayload));
                        $overridePayloadJson = e(json_encode($overridePayload));
                        $latestDispense = $item->latest_dispense;
                        $statusClasses = match ($item->status) {
                            'dispensed' => 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300',
                            'partial' => 'bg-brand-100 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300',
                            'external', 'partial_external' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
                            'cancelled', 'partial_cancelled' => 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-300',
                            default => 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
                        };
                    @endphp
                    <tr class="align-top">
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                            <div class="font-medium text-gray-900 dark:text-white">{{ $item->prescription?->visitRegistration?->patient?->full_name }}</div>
                            <div class="mt-1 text-xs text-gray-400">{{ $item->prescription?->visitRegistration?->patientBranchRecord?->medical_record_no }}</div>
                            <div class="mt-1 text-xs text-gray-400">{{ $item->prescription?->visitRegistration?->branch?->code }} | {{ $item->prescription?->visitRegistration?->section?->name }}</div>
                            <div class="mt-1 text-xs text-gray-400">{{ $item->prescription?->visitRegistration?->doctor?->displayName() ?? 'Doctor belum dipilih' }}</div>
                            <div class="mt-1 text-xs text-gray-400">{{ $item->prescription?->visitRegistration?->doctorSchedule?->room_label ?: 'Room belum diatur' }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                            <div class="font-medium text-gray-900 dark:text-white">{{ $item->display_name }}</div>
                            <div class="mt-1 text-xs text-gray-400">{{ strtoupper($item->item_type) }} @if($item->medicine) | {{ $item->medicine->code }} @endif</div>
                            <div class="mt-2 text-xs text-gray-500">{{ $item->dose_amount ?: '-' }} {{ $item->dose_unit }} | {{ $item->frequency ?: '-' }} | {{ $item->duration_days ?: '-' }} hari</div>
                            <div class="mt-1 text-xs text-gray-500">Qty {{ number_format((float) $item->quantity_prescribed, 2) }} {{ $item->dispense_unit }}</div>
                            @if ($item->instruction)
                                <div class="mt-2 rounded-xl bg-gray-50 px-3 py-2 text-xs text-gray-500 dark:bg-white/[0.03] dark:text-gray-300">{{ $item->instruction }}</div>
                            @endif
                            @if (! empty($item->safety_alerts))
                                <div class="mt-3 space-y-2">
                                    @foreach ($item->safety_alerts as $alert)
                                        <div class="rounded-xl px-3 py-2 text-xs {{ $alert['level'] === 'danger' ? 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' }}">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="font-semibold uppercase tracking-[0.16em]">{{ strtoupper((string) ($alert['severity'] ?? 'warning')) }}</span>
                                                @if (($alert['requires_override'] ?? false) === true)
                                                    <span class="rounded-full border border-current/20 px-2 py-0.5 text-[10px] font-medium">{{ ($alert['overridden'] ?? false) ? 'OVERRIDDEN' : 'OVERRIDE REQUIRED' }}</span>
                                                @elseif (($alert['blocking'] ?? false) === true)
                                                    <span class="rounded-full border border-current/20 px-2 py-0.5 text-[10px] font-medium">BLOCKING</span>
                                                @endif
                                            </div>
                                            <div class="mt-1">{{ $alert['message'] }}</div>
                                            @if (! empty($alert['management_advice']))
                                                <div class="mt-1 text-[11px] opacity-80">Advice: {{ $alert['management_advice'] }}</div>
                                            @endif
                                            @if (! empty($alert['override_reason']))
                                                <div class="mt-1 text-[11px] opacity-80">Override reason: {{ $alert['override_reason'] }}</div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                            @if (($item->dispensing_preview['type'] ?? null) === 'external')
                                <div>{{ $item->dispensing_preview['summary'] }}</div>
                            @elseif (($item->dispensing_preview['type'] ?? null) === 'compound')
                                <div class="text-xs font-medium uppercase tracking-[0.16em] text-gray-400">Compound capsule</div>
                                <div class="mt-2 text-xs text-gray-500">{{ $item->dispensing_preview['summary'] }}</div>
                                <div class="mt-3 space-y-2">
                                    @foreach ($item->dispensing_preview['ingredients'] as $ingredient)
                                        <div class="rounded-xl border border-gray-200 px-3 py-2 dark:border-gray-800">
                                            <div class="font-medium text-gray-900 dark:text-white">{{ $ingredient['label'] }}</div>
                                            <div class="mt-1 text-xs text-gray-500">Need {{ number_format((float) $ingredient['required_for_remaining'], 2) }} | Available {{ number_format((float) $ingredient['available_quantity'], 2) }}</div>
                                            <div class="mt-1 text-xs text-gray-400">Batch {{ $ingredient['nearest_batch'] ?: '-' }} | Exp {{ $ingredient['nearest_expiry'] ?: '-' }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="font-medium text-gray-900 dark:text-white">{{ $item->dispensing_preview['summary'] }}</div>
                                <div class="mt-2 text-xs text-gray-500">Available {{ number_format((float) ($item->dispensing_preview['available_quantity'] ?? 0), 2) }} | Remaining {{ number_format((float) ($item->dispensing_preview['remaining_quantity'] ?? 0), 2) }}</div>
                                <div class="mt-1 text-xs text-gray-400">Batch {{ $item->dispensing_preview['nearest_batch'] ?: '-' }} | Exp {{ $item->dispensing_preview['nearest_expiry'] ?: '-' }}</div>
                                <div class="mt-1 text-xs text-gray-400">Selling price {{ isset($item->dispensing_preview['selling_price']) && $item->dispensing_preview['selling_price'] !== null ? 'Rp ' . number_format((float) $item->dispensing_preview['selling_price'], 0, ',', '.') : 'manual / belum diatur' }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClasses }}">{{ strtoupper(str_replace('_', ' ', $item->status)) }}</span>
                            <div class="mt-2 text-xs text-gray-400">Prescription {{ strtoupper($item->prescription?->status ?? '-') }}</div>
                            <div class="mt-1 text-xs text-gray-400">Dispensed {{ number_format((float) $item->dispensed_quantity, 2) }}</div>
                            <div class="mt-1 text-xs text-gray-400">Remaining {{ number_format((float) $item->remaining_quantity, 2) }}</div>
                            @if ($item->closed_remaining_reason)
                                <div class="mt-2 rounded-xl bg-gray-50 px-3 py-2 text-xs text-gray-500 dark:bg-white/[0.03] dark:text-gray-300">
                                    {{ $item->closed_remaining_status ? strtoupper($item->closed_remaining_status) . ': ' : '' }}{{ $item->closed_remaining_reason }}
                                </div>
                            @endif
                            @if ($latestDispense)
                                <div class="mt-2 text-xs text-gray-400">Last dispense {{ $latestDispense->dispensed_at?->format('d M Y H:i') }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex justify-end gap-2">
                                @if ($abilities['edit'] && $item->dispenses->isEmpty())
                                    <x-ui.icon-button title="Edit item" x-on:click="openEditItem($event.currentTarget.dataset.payload)" data-payload="{{ $itemPayloadJson }}">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                    </x-ui.icon-button>
                                @endif

                                @if ($abilities['dispense'] && $item->hasOpenFulfillment())
                                    <x-ui.icon-button variant="primary" title="Dispense item" x-on:click="openDispense($event.currentTarget.dataset.payload)" data-payload="{{ $dispensePayloadJson }}">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M7 7H17V10H7V7Z" stroke="currentColor" stroke-width="1.5"/><path d="M6 10H18L19 17H5L6 10Z" stroke="currentColor" stroke-width="1.5"/></svg>
                                    </x-ui.icon-button>
                                @endif

                                @if ($abilities['dispense'] && $item->hasOpenFulfillment() && (float) $item->remaining_quantity > 0)
                                    <x-ui.icon-button title="Tutup sisa fulfillment" x-on:click="openCloseRemaining($event.currentTarget.dataset.payload)" data-payload="{{ $closePayloadJson }}">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M7 12L10 15L17 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 4.5H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M6 19.5H18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                    </x-ui.icon-button>
                                @endif

                                @if ($abilities['override'] && $pendingOverrideAlerts->isNotEmpty())
                                    <x-ui.icon-button title="Override major interaction" x-on:click="openOverride($event.currentTarget.dataset.payload)" data-payload="{{ $overridePayloadJson }}">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 8V12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M12 16.01L12.01 15.9989" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M10.6159 4.89143L3.70229 17.3082C3.09092 18.4062 3.88487 19.75 5.08642 19.75H18.9136C20.1151 19.75 20.9091 18.4062 20.2977 17.3082L13.3841 4.89143C12.7834 3.81295 11.2166 3.81295 10.6159 4.89143Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                                    </x-ui.icon-button>
                                @endif

                                @if ($latestDispense)
                                    <a href="{{ route('prescription-dispenses.label', $latestDispense) }}" target="_blank" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-green-200 bg-green-50 text-green-700 transition hover:bg-green-100 dark:border-green-500/20 dark:bg-green-500/10 dark:text-green-300" title="Print label">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M7 8V5.75C7 5.05964 7.55964 4.5 8.25 4.5H15.75C16.4404 4.5 17 5.05964 17 5.75V8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 17H5.75C5.05964 17 4.5 16.4404 4.5 15.75V10.25C4.5 9.55964 5.05964 9 5.75 9H18.25C18.9404 9 19.5 9.55964 19.5 10.25V15.75C19.5 16.4404 18.9404 17 18.25 17H17" stroke="currentColor" stroke-width="1.5"/><path d="M8 14.5H16V19.5H8V14.5Z" stroke="currentColor" stroke-width="1.5"/><path d="M16 11.5H16.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                    </a>
                                @endif

                                @if ($abilities['delete'] && $item->dispenses->isEmpty())
                                    <form method="POST" action="{{ route('prescription-items.delete', $item) }}" onsubmit="return confirm('Batalkan item prescription ini?')">
                                        @csrf
                                        <x-ui.icon-button type="submit" variant="danger" title="Batalkan item">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 7H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 7L8 18.5C8.06066 19.3284 8.75104 19.9643 9.58166 19.9643H14.4183C15.249 19.9643 15.9393 19.3284 16 18.5L17 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9 7V5.75C9 5.05964 9.55964 4.5 10.25 4.5H13.75C14.4404 4.5 15 5.05964 15 5.75V7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                        </x-ui.icon-button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada item dispensing yang cocok dengan filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">{{ $dispensingItems->links() }}</div>
</section>
