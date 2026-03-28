@php
    $modalOpen = $errors->any() && in_array(old('form_context'), ['interaction-create', 'interaction-update'], true);
    $modalMode = old('form_context') === 'interaction-update' ? 'update' : 'create';
    $modalForm = [
        'id' => old('entity_id'),
        'code' => old('code', ''),
        'left_operand_type' => old('left_operand_type', 'ingredient'),
        'left_operand_value' => old('left_operand_value', ''),
        'right_operand_type' => old('right_operand_type', 'ingredient'),
        'right_operand_value' => old('right_operand_value', ''),
        'severity' => old('severity', 'moderate'),
        'title' => old('title', ''),
        'clinical_effect' => old('clinical_effect', ''),
        'management_advice' => old('management_advice', ''),
        'is_active' => (int) old('is_active', 1) === 1,
    ];
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Drug Interactions" />

    <div x-data="{
        modalOpen: @js($modalOpen),
        modalMode: @js($modalMode),
        storeAction: @js(route('drug-interactions.store')),
        updateBase: @js(url('/drug-interactions')),
        form: @js($modalForm),
        parsePayload(payload) {
            try {
                return JSON.parse(payload || '{}');
            } catch (error) {
                return {};
            }
        },
        emptyForm() {
            return {
                id: null,
                code: '',
                left_operand_type: 'ingredient',
                left_operand_value: '',
                right_operand_type: 'ingredient',
                right_operand_value: '',
                severity: 'moderate',
                title: '',
                clinical_effect: '',
                management_advice: '',
                is_active: true,
            };
        },
        openCreate() {
            this.modalMode = 'create';
            this.form = this.emptyForm();
            this.modalOpen = true;
        },
        openEdit(payload) {
            this.modalMode = 'update';
            this.form = { ...this.emptyForm(), ...this.parsePayload(payload) };
            this.modalOpen = true;
        },
    }" @keydown.escape.window="modalOpen = false" class="space-y-6">
        @if (session('status'))
            <x-ui.alert variant="success" :message="session('status')" />
        @endif
        @if ($errors->any())
            <x-ui.alert variant="error" :message="$errors->first()" />
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Drug Interactions</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">Rule-based safety checker untuk ingredient, kelas terapi, dan generic name. Rule dipakai saat finalize prescription dan saat dispensing untuk mencegah kombinasi obat berisiko.</p>
                </div>
                @if ($abilities['create'])
                    <x-ui.button type="button" x-on:click="openCreate()">Tambah Rule</x-ui.button>
                @endif
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Active rules</div>
                <div class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">{{ number_format($metrics['active_rules']) }}</div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Major / blocked rules</div>
                <div class="mt-2 text-3xl font-semibold text-red-600 dark:text-red-300">{{ number_format($metrics['major_rules']) }}</div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Override events</div>
                <div class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">{{ number_format($metrics['override_events']) }}</div>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('drug-interactions') }}" class="grid gap-4 md:grid-cols-[1.2fr_200px_200px_200px_auto]">
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari code, title, operand, atau advice" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <select name="severity" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="">Semua severity</option>
                    @foreach ($severityOptions as $severityOption)
                        <option value="{{ $severityOption }}" @selected($filters['severity'] === $severityOption)>{{ strtoupper($severityOption) }}</option>
                    @endforeach
                </select>
                <select name="operand_type" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="">Semua operand</option>
                    @foreach ($operandTypes as $operandType)
                        <option value="{{ $operandType }}" @selected($filters['operand_type'] === $operandType)>{{ ucfirst($operandType) }}</option>
                    @endforeach
                </select>
                <select name="status" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="">Semua status</option>
                    <option value="active" @selected($filters['status'] === 'active')>Active</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                </select>
                <div class="flex gap-3">
                    <x-ui.button type="submit" variant="outline">Filter</x-ui.button>
                    <a href="{{ route('drug-interactions') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">Reset</a>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Interaction rule list</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan {{ $rules->firstItem() ?? 0 }} - {{ $rules->lastItem() ?? 0 }} dari {{ $rules->total() }} rule.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Rule</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Operands</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Clinical Guidance</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($rules as $rule)
                            @php
                                $payload = [
                                    'id' => $rule->id,
                                    'code' => $rule->code,
                                    'left_operand_type' => $rule->left_operand_type,
                                    'left_operand_value' => $rule->left_operand_value,
                                    'right_operand_type' => $rule->right_operand_type,
                                    'right_operand_value' => $rule->right_operand_value,
                                    'severity' => $rule->severity,
                                    'title' => $rule->title,
                                    'clinical_effect' => $rule->clinical_effect ?? '',
                                    'management_advice' => $rule->management_advice ?? '',
                                    'is_active' => $rule->is_active,
                                ];
                                $payloadJson = e(json_encode($payload));
                                $severityClasses = match ($rule->severity) {
                                    'contraindicated' => 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-300',
                                    'major' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
                                    'moderate' => 'bg-brand-100 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300',
                                    default => 'bg-gray-100 text-gray-600 dark:bg-white/[0.04] dark:text-gray-300',
                                };
                            @endphp
                            <tr class="align-top">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $rule->title }}</div>
                                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $rule->code }}</div>
                                    <div class="mt-2">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $severityClasses }}">{{ strtoupper($rule->severity) }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ ucfirst($rule->left_operand_type) }}</span>
                                        <span class="text-gray-400">:</span>
                                        <span>{{ $rule->left_operand_value }}</span>
                                    </div>
                                    <div class="mt-1 text-xs text-gray-400">berinteraksi dengan</div>
                                    <div class="mt-1">
                                        <span class="font-medium text-gray-900 dark:text-white">{{ ucfirst($rule->right_operand_type) }}</span>
                                        <span class="text-gray-400">:</span>
                                        <span>{{ $rule->right_operand_value }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $rule->clinical_effect ?: '-' }}</div>
                                    <div class="mt-2 rounded-xl bg-gray-50 px-3 py-2 text-xs text-gray-500 dark:bg-white/[0.03] dark:text-gray-300">
                                        {{ $rule->management_advice ?: 'Belum ada management advice khusus.' }}
                                    </div>
                                    <div class="mt-2 text-xs text-gray-400">Overrides {{ number_format($rule->overrides_count) }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $rule->is_active ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-white/[0.04] dark:text-gray-300' }}">
                                        {{ $rule->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        @if ($abilities['edit'])
                                            <x-ui.icon-button title="Edit rule" data-payload="{{ $payloadJson }}" x-on:click="openEdit($event.currentTarget.dataset.payload)">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                            </x-ui.icon-button>
                                        @endif
                                        @if ($abilities['delete'])
                                            <form method="POST" action="{{ route('drug-interactions.delete', $rule) }}" onsubmit="return confirm('Arsipkan interaction rule ini?')">
                                                @csrf
                                                <x-ui.icon-button type="submit" variant="danger" title="Arsipkan rule">
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 7H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 7L8 18.5C8.06066 19.3284 8.75104 19.9643 9.58166 19.9643H14.4183C15.249 19.9643 15.9393 19.3284 16 18.5L17 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9 7V5.75C9 5.05964 9.55964 4.5 10.25 4.5H13.75C14.4404 4.5 15 5.05964 15 5.75V7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                                </x-ui.icon-button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada drug interaction rule.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">{{ $rules->links() }}</div>
        </section>

        <x-ui.modal show="modalOpen" maxWidth="4xl">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="modalMode === 'create' ? 'Tambah Drug Interaction Rule' : 'Update Drug Interaction Rule'"></h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Gunakan operand ingredient, generic, atau therapeutic class. Severity major memerlukan override, sedangkan contraindicated akan memblok tindakan klinis.</p>
                </div>
                <x-ui.icon-button title="Tutup modal" x-on:click="modalOpen = false">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                </x-ui.icon-button>
            </div>

            <form method="POST" x-bind:action="modalMode === 'create' ? storeAction : `${updateBase}/${form.id}`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" x-bind:value="modalMode === 'create' ? 'interaction-create' : 'interaction-update'">
                <input type="hidden" name="entity_id" x-bind:value="form.id">

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Rule code</label>
                        <input x-model="form.code" type="text" name="code" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm uppercase text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Severity</label>
                        <select x-model="form.severity" name="severity" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            @foreach ($severityOptions as $severityOption)
                                <option value="{{ $severityOption }}">{{ strtoupper($severityOption) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Left operand type</label>
                        <select x-model="form.left_operand_type" name="left_operand_type" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            @foreach ($operandTypes as $operandType)
                                <option value="{{ $operandType }}">{{ ucfirst($operandType) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Left operand value</label>
                        <input x-model="form.left_operand_value" type="text" name="left_operand_value" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 lowercase dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Right operand type</label>
                        <select x-model="form.right_operand_type" name="right_operand_type" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            @foreach ($operandTypes as $operandType)
                                <option value="{{ $operandType }}">{{ ucfirst($operandType) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Right operand value</label>
                        <input x-model="form.right_operand_value" type="text" name="right_operand_value" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 lowercase dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Interaction title</label>
                    <input x-model="form.title" type="text" name="title" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Clinical effect</label>
                    <textarea x-model="form.clinical_effect" name="clinical_effect" rows="3" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Management advice</label>
                    <textarea x-model="form.management_advice" name="management_advice" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                </div>
                <div>
                    <input type="hidden" name="is_active" x-bind:value="form.is_active ? 1 : 0">
                    <label class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                        <input x-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20">
                        <span>Active rule</span>
                    </label>
                </div>

                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="modalOpen = false">Batal</x-ui.button>
                    <x-ui.button type="submit" x-text="modalMode === 'create' ? 'Simpan Rule' : 'Update Rule'"></x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    </div>
@endsection
