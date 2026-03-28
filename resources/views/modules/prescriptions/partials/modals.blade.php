<x-ui.modal show="prescriptionModalOpen" maxWidth="2xl">
    <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800">
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="prescriptionMode === 'create' ? 'Tambah Prescription' : 'Update Prescription'"></h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Satu visit punya satu header prescription, lalu item resep ditambahkan di bawahnya.</p>
        </div>
        <x-ui.icon-button title="Tutup modal" x-on:click="prescriptionModalOpen = false">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        </x-ui.icon-button>
    </div>

    <form method="POST" x-bind:action="prescriptionMode === 'create' ? prescriptionStoreAction : `${prescriptionUpdateBase}/${prescriptionForm.id}`" class="space-y-5 p-6">
        @csrf
        <input type="hidden" name="form_context" x-bind:value="prescriptionMode === 'create' ? 'prescription-create' : 'prescription-update'">
        <input type="hidden" name="entity_id" x-bind:value="prescriptionForm.id">

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Visit</label>
            <select x-model="prescriptionForm.visit_registration_id" name="visit_registration_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                @foreach ($visitOptions as $visitOption)
                    <option value="{{ $visitOption->id }}">{{ $visitOption->patient?->full_name }} | {{ $visitOption->patientBranchRecord?->medical_record_no }} | {{ $visitOption->branch?->code }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
            <textarea x-model="prescriptionForm.notes" name="notes" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
        </div>

        <div class="flex justify-end gap-3">
            <x-ui.button type="button" variant="outline" x-on:click="prescriptionModalOpen = false">Batal</x-ui.button>
            <x-ui.button type="submit" x-text="prescriptionMode === 'create' ? 'Simpan Prescription' : 'Update Prescription'"></x-ui.button>
        </div>
    </form>
</x-ui.modal>

<x-ui.modal show="itemModalOpen" maxWidth="4xl">
    <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800">
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="itemMode === 'create' ? 'Tambah Item Prescription' : 'Update Item Prescription'"></h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Support in-house, resep luar, dan racikan kapsul dengan snapshot dosis klinis.</p>
        </div>
        <x-ui.icon-button title="Tutup modal" x-on:click="itemModalOpen = false">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        </x-ui.icon-button>
    </div>

    <form method="POST" x-bind:action="itemMode === 'create' ? itemStoreAction : `${itemUpdateBase}/${itemForm.id}`" class="space-y-5 p-6">
        @csrf
        <input type="hidden" name="form_context" x-bind:value="itemMode === 'create' ? 'prescription-item-create' : 'prescription-item-update'">
        <input type="hidden" name="entity_id" x-bind:value="itemForm.id">

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Prescription</label>
                <select x-model="itemForm.prescription_id" name="prescription_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    @foreach ($prescriptionOptions as $visitOption)
                        <option value="{{ $visitOption->prescription->id }}">{{ $visitOption->patient?->full_name }} | {{ $visitOption->prescription->status }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Item type</label>
                <select x-model="itemForm.item_type" name="item_type" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="in_house">in_house</option>
                    <option value="external">external</option>
                    <option value="compound">compound</option>
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Medicine</label>
                <select x-model="itemForm.medicine_id" name="medicine_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="">Pilih medicine</option>
                    @foreach ($medicineOptions as $medicineOption)
                        <option value="{{ $medicineOption->id }}">{{ $medicineOption->code }} - {{ $medicineOption->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Compound display name</label>
                <input x-model="itemForm.display_name" type="text" name="display_name" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Route</label>
                <input x-model="itemForm.route" type="text" name="route" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Frequency</label>
                <input x-model="itemForm.frequency" type="text" name="frequency" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Dose amount</label>
                <input x-model="itemForm.dose_amount" type="number" step="0.01" min="0" name="dose_amount" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Dose unit</label>
                <input x-model="itemForm.dose_unit" type="text" name="dose_unit" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity prescribed</label>
                <input x-model="itemForm.quantity_prescribed" type="number" step="0.01" min="0.01" name="quantity_prescribed" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Dispense unit</label>
                <input x-model="itemForm.dispense_unit" type="text" name="dispense_unit" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Duration days</label>
                <input x-model="itemForm.duration_days" type="number" min="1" name="duration_days" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Weight snapshot kg</label>
                <input x-model="itemForm.weight_snapshot_kg" type="number" step="0.01" min="0" name="weight_snapshot_kg" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                <select x-model="itemForm.status" name="status" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="pending">pending</option>
                    <option value="cancelled">cancelled</option>
                    <option value="external">external</option>
                </select>
            </div>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Instruction / signa</label>
            <textarea x-model="itemForm.instruction" name="instruction" rows="3" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Item notes</label>
            <textarea x-model="itemForm.notes" name="notes" rows="3" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
        </div>

        <div>
            <div class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Compound ingredients</div>
            <div class="space-y-3">
                <template x-for="(ingredient, index) in itemForm.compound_ingredients" :key="index">
                    <div class="grid gap-4 md:grid-cols-[1fr_140px_120px]">
                        <select x-model="ingredient.medicine_id" x-bind:name="`compound_ingredients[${index}][medicine_id]`" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="">Ingredient</option>
                            @foreach ($medicineOptions as $medicineOption)
                                <option value="{{ $medicineOption->id }}">{{ $medicineOption->code }} - {{ $medicineOption->name }}</option>
                            @endforeach
                        </select>
                        <input x-model="ingredient.quantity_required" x-bind:name="`compound_ingredients[${index}][quantity_required]`" type="number" step="0.01" min="0" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <input x-model="ingredient.unit" x-bind:name="`compound_ingredients[${index}][unit]`" type="text" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                </template>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <x-ui.button type="button" variant="outline" x-on:click="itemModalOpen = false">Batal</x-ui.button>
            <x-ui.button type="submit" x-text="itemMode === 'create' ? 'Simpan Item' : 'Update Item'"></x-ui.button>
        </div>
    </form>
</x-ui.modal>

<x-ui.modal show="dispenseModalOpen" maxWidth="2xl">
    <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800">
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Dispense item</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">FEFO allocation dijalankan saat dispense disimpan. Untuk racikan kapsul, stok ingredient dikurangi proporsional terhadap quantity yang keluar.</p>
        </div>
        <x-ui.icon-button title="Tutup modal" x-on:click="dispenseModalOpen = false">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        </x-ui.icon-button>
    </div>

    <form method="POST" x-bind:action="`${itemActionBase}/${dispenseForm.id}/dispense`" class="space-y-5 p-6">
        @csrf
        <input type="hidden" name="form_context" value="prescription-dispense">
        <input type="hidden" name="entity_id" x-bind:value="dispenseForm.id">

        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="font-medium text-gray-900 dark:text-white" x-text="dispenseMeta?.display_name"></div>
            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="`${dispenseMeta?.patient ?? '-'} | ${dispenseMeta?.visit_ref ?? '-'}`"></div>
            <div class="mt-2 text-xs text-gray-400" x-text="`Prescribed ${dispenseMeta?.prescribed ?? 0} | Sudah dispensed ${dispenseMeta?.dispensed ?? 0} | Sisa ${dispenseMeta?.remaining ?? 0}`"></div>
            <template x-if="dispenseMeta?.preview?.type === 'in_house'">
                <div class="mt-2 text-xs text-gray-400" x-text="`Available ${dispenseMeta?.preview?.available_quantity ?? 0} | Batch ${dispenseMeta?.preview?.nearest_batch ?? '-'} | Exp ${dispenseMeta?.preview?.nearest_expiry ?? '-'}`"></div>
            </template>
            <template x-if="dispenseMeta?.compound">
                <div class="mt-2 text-xs text-amber-600 dark:text-amber-300">Compound capsule memerlukan harga manual saat dispensing.</div>
            </template>
        </div>

        <template x-if="(dispenseMeta?.alerts ?? []).length">
            <div class="space-y-2">
                <template x-for="(alert, index) in (dispenseMeta?.alerts ?? [])" :key="index">
                    <div class="rounded-xl px-3 py-2 text-xs" x-bind:class="alert.level === 'danger' ? 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'">
                        <div class="font-medium uppercase tracking-[0.16em]" x-text="alert.severity ?? 'warning'"></div>
                        <div class="mt-1" x-text="alert.message"></div>
                        <template x-if="alert.management_advice">
                            <div class="mt-1 text-[11px] opacity-80" x-text="`Advice: ${alert.management_advice}`"></div>
                        </template>
                        <template x-if="alert.override_reason">
                            <div class="mt-1 text-[11px] opacity-80" x-text="`Override reason: ${alert.override_reason}`"></div>
                        </template>
                    </div>
                </template>
            </div>
        </template>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity dispensed</label>
            <input x-model="dispenseForm.quantity_dispensed" type="number" step="0.01" min="0.01" name="quantity_dispensed" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Manual unit price</label>
            <input x-model="dispenseForm.unit_price" type="number" step="0.01" min="0" name="unit_price" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
            <textarea x-model="dispenseForm.notes" name="notes" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
        </div>

        <div class="flex justify-end gap-3">
            <x-ui.button type="button" variant="outline" x-on:click="dispenseModalOpen = false">Batal</x-ui.button>
            <x-ui.button type="submit">Simpan Dispense</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<x-ui.modal show="closeModalOpen" maxWidth="2xl">
    <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800">
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Tutup sisa fulfillment</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Gunakan saat sisa item akan dibeli di luar atau dihentikan, supaya visit tidak terus tertahan di farmasi.</p>
        </div>
        <x-ui.icon-button title="Tutup modal" x-on:click="closeModalOpen = false">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        </x-ui.icon-button>
    </div>

    <form method="POST" x-bind:action="`${itemActionBase}/${closeForm.id}/close-remaining`" class="space-y-5 p-6">
        @csrf
        <input type="hidden" name="form_context" value="prescription-close-remaining">
        <input type="hidden" name="entity_id" x-bind:value="closeForm.id">

        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="font-medium text-gray-900 dark:text-white" x-text="closeMeta?.display_name"></div>
            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="`${closeMeta?.patient ?? '-'} | ${closeMeta?.visit_ref ?? '-'}`"></div>
            <div class="mt-2 text-xs text-gray-400" x-text="`Sudah dispensed ${closeMeta?.dispensed ?? 0} | Sisa ${closeMeta?.remaining ?? 0}`"></div>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Tutup sebagai</label>
            <select x-model="closeForm.closure_status" name="closure_status" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <option value="external">External / beli di luar</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Reason</label>
            <textarea x-model="closeForm.closure_reason" name="closure_reason" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
        </div>

        <div class="flex justify-end gap-3">
            <x-ui.button type="button" variant="outline" x-on:click="closeModalOpen = false">Batal</x-ui.button>
            <x-ui.button type="submit">Simpan Penutupan</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<x-ui.modal show="overrideModalOpen" maxWidth="2xl">
    <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800">
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Override major interaction</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Gunakan hanya bila dokter memutuskan manfaat terapi lebih besar dari risiko. Alasan override wajib dicatat untuk audit klinis.</p>
        </div>
        <x-ui.icon-button title="Tutup modal" x-on:click="overrideModalOpen = false">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        </x-ui.icon-button>
    </div>

    <form method="POST" x-bind:action="`${itemActionBase}/${overrideForm.id}/override-interactions`" class="space-y-5 p-6">
        @csrf
        <input type="hidden" name="form_context" value="prescription-override">
        <input type="hidden" name="entity_id" x-bind:value="overrideForm.id">

        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="font-medium text-gray-900 dark:text-white" x-text="overrideMeta?.display_name"></div>
            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="`${overrideMeta?.patient ?? '-'} | ${overrideMeta?.visit_ref ?? '-'}`"></div>
            <div class="mt-3 space-y-2" x-show="(overrideMeta?.alerts ?? []).length > 0">
                <template x-for="(alert, index) in (overrideMeta?.alerts ?? [])" :key="index">
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                        <div class="font-medium" x-text="alert.title || 'Major interaction'"></div>
                        <div class="mt-1" x-text="alert.message"></div>
                        <template x-if="alert.management_advice">
                            <div class="mt-1 text-[11px] opacity-80" x-text="`Advice: ${alert.management_advice}`"></div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Override reason</label>
            <textarea x-model="overrideForm.override_reason" name="override_reason" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
        </div>

        <div class="flex justify-end gap-3">
            <x-ui.button type="button" variant="outline" x-on:click="overrideModalOpen = false">Batal</x-ui.button>
            <x-ui.button type="submit">Simpan Override</x-ui.button>
        </div>
    </form>
</x-ui.modal>
