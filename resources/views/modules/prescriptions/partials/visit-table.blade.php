<section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
    <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Visit prescriptions</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan {{ $visits->firstItem() ?? 0 }} - {{ $visits->lastItem() ?? 0 }} dari {{ $visits->total() }} visit.</p>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
            <thead class="bg-gray-50 dark:bg-white/[0.02]">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Visit</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Doctor</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Prescription</th>
                    <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                @forelse ($visits as $visit)
                    @php
                        $prescriptionPayload = $visit->prescription ? [
                            'id' => $visit->prescription->id,
                            'visit_registration_id' => (string) $visit->id,
                            'notes' => $visit->prescription->notes ?? '',
                        ] : null;
                        $prescriptionPayloadJson = $prescriptionPayload ? e(json_encode($prescriptionPayload)) : null;
                    @endphp
                    <tr class="align-top">
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                            <div class="font-medium text-gray-900 dark:text-white">{{ $visit->patient?->full_name }}</div>
                            <div class="mt-1 text-xs text-gray-400">{{ $visit->patientBranchRecord?->medical_record_no }} | {{ $visit->branch?->code }} | {{ $visit->section?->name }}</div>
                            <div class="mt-1 text-xs text-gray-400">{{ $visit->visit_date?->format('d M Y') }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                            <div>{{ $visit->medicalRecord?->doctor?->displayName() ?? '-' }}</div>
                            <div class="mt-1 text-xs text-gray-400">Consultation {{ $visit->medicalRecord?->doctor?->consultation_fee ? 'Rp ' . number_format((float) $visit->medicalRecord?->doctor?->consultation_fee, 0, ',', '.') : '-' }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                            @if ($visit->prescription)
                                <div class="font-medium text-gray-900 dark:text-white">{{ strtoupper($visit->prescription->status) }}</div>
                                <div class="mt-1 text-xs text-gray-400">{{ $visit->prescription->items->count() }} item</div>
                                @foreach ($visit->prescription->items->take(3) as $prescriptionItem)
                                    <div class="mt-1 text-xs text-gray-500">{{ $prescriptionItem->display_name }} | {{ $prescriptionItem->item_type }} | {{ $prescriptionItem->status }}</div>
                                @endforeach
                            @else
                                <div>-</div>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex justify-end gap-2">
                                @if (! $visit->prescription && $abilities['create'])
                                    <x-ui.icon-button title="Buat prescription" x-on:click="openCreatePrescription('{{ $visit->id }}')">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 5V19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M5 12H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                    </x-ui.icon-button>
                                @endif

                                @if ($visit->prescription && $abilities['edit'])
                                    <x-ui.icon-button title="Edit prescription" x-on:click="openEditPrescription($event.currentTarget.dataset.payload)" data-payload="{{ $prescriptionPayloadJson }}">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                    </x-ui.icon-button>
                                    <x-ui.icon-button title="Tambah item" x-on:click="openCreateItem('{{ $visit->prescription->id }}')">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 5V19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M5 12H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                    </x-ui.icon-button>
                                @endif

                                @if ($visit->prescription && $visit->prescription->status === 'draft' && $abilities['finalize'])
                                    <form method="POST" action="{{ route('prescriptions.finalize', $visit->prescription) }}">
                                        @csrf
                                        <x-ui.icon-button type="submit" variant="primary" title="Finalize prescription">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 12.5L9.5 17L19 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </x-ui.icon-button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada visit dengan medical record yang cocok dengan filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">{{ $visits->links() }}</div>
</section>
