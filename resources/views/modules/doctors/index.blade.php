@php
    $doctorModalOpen = $errors->any() && in_array(old('form_context'), ['doctor-create', 'doctor-update'], true);
    $doctorModalMode = old('form_context') === 'doctor-update' ? 'update' : 'create';
    $doctorModalForm = [
        'id' => old('entity_id'),
        'title_prefix' => old('title_prefix', ''),
        'full_name' => old('full_name', ''),
        'title_suffix' => old('title_suffix', ''),
        'specialization' => old('specialization', ''),
        'consultation_fee' => (string) old('consultation_fee', '0'),
        'str_number' => old('str_number', ''),
        'str_expired_at' => old('str_expired_at', ''),
        'sip_number' => old('sip_number', ''),
        'sip_expired_at' => old('sip_expired_at', ''),
        'phone' => old('phone', ''),
        'email' => old('email', ''),
        'address' => old('address', ''),
        'signature_path' => old('current_signature_path', ''),
        'sections' => collect(old('sections', []))
            ->map(fn ($value) => (string) $value)
            ->values()
            ->all(),
        'remove_signature' => (int) old('remove_signature', 0) === 1,
        'is_active' => (int) old('is_active', 1) === 1,
    ];
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Doctors" />

    <div
        x-data="{
            modalOpen: @js($doctorModalOpen),
            mode: @js($doctorModalMode),
            storeAction: @js(route('doctors.store')),
            updateBase: @js(url('/doctors')),
            form: @js($doctorModalForm),
            openCreate() {
                this.mode = 'create';
                this.form = {
                    id: null,
                    title_prefix: '',
                    full_name: '',
                    title_suffix: '',
                    specialization: '',
                    consultation_fee: '0',
                    str_number: '',
                    str_expired_at: '',
                    sip_number: '',
                    sip_expired_at: '',
                    phone: '',
                    email: '',
                    address: '',
                    signature_path: '',
                    sections: [],
                    remove_signature: false,
                    is_active: true,
                };
                this.modalOpen = true;
            },
            openEdit(doctor) {
                this.mode = 'update';
                this.form = {
                    id: doctor.id,
                    title_prefix: doctor.title_prefix,
                    full_name: doctor.full_name,
                    title_suffix: doctor.title_suffix,
                    specialization: doctor.specialization,
                    consultation_fee: doctor.consultation_fee,
                    str_number: doctor.str_number,
                    str_expired_at: doctor.str_expired_at,
                    sip_number: doctor.sip_number,
                    sip_expired_at: doctor.sip_expired_at,
                    phone: doctor.phone,
                    email: doctor.email,
                    address: doctor.address,
                    signature_path: doctor.signature_path,
                    sections: doctor.sections,
                    remove_signature: false,
                    is_active: doctor.is_active,
                };
                this.modalOpen = true;
            },
            closeModal() {
                this.modalOpen = false;
            },
        }"
        @keydown.escape.window="closeModal()"
        class="space-y-6"
    >
        @if (session('status'))
            <x-ui.alert variant="success" :message="session('status')" />
        @endif

        @if ($errors->any())
            <x-ui.alert variant="error" :message="$errors->first()" />
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Data table doctors</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">
                        Kelola master dokter untuk seluruh branch dan section. Modul ini menyimpan profil, STR, SIP, spesialisasi, tarif konsultasi, dan relasi dokter ke section sebelum kita masuk ke jadwal praktek.
                    </p>
                    @if ($sectionGroups->isEmpty())
                        <p class="mt-3 text-sm font-medium text-amber-600 dark:text-amber-300">
                            Belum ada section. Doctor tetap bisa dibuat sekarang dan relasi ke section bisa ditambahkan setelah section siap.
                        </p>
                    @endif
                </div>

                @if ($abilities['create'])
                    <x-ui.button type="button" x-on:click="openCreate()">
                        Tambah Doctor
                    </x-ui.button>
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('doctors') }}" class="grid gap-4 md:grid-cols-[1.4fr_220px_240px_180px_auto]">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari nama dokter, spesialisasi, STR, SIP, phone, email, atau section" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Branch</label>
                    <select name="branch" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Semua branch</option>
                        @foreach ($branchOptions as $branchOption)
                            <option value="{{ $branchOption->id }}" @selected($filters['branch'] === (string) $branchOption->id)>{{ $branchOption->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Section</label>
                    <select name="section" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Semua section</option>
                        @foreach ($sectionOptions as $sectionOption)
                            <option value="{{ $sectionOption->id }}" @selected($filters['section'] === (string) $sectionOption->id)>
                                {{ $sectionOption->branch?->name }} - {{ $sectionOption->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                    <select name="status" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Semua status</option>
                        <option value="active" @selected($filters['status'] === 'active')>Active</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                    </select>
                </div>

                <div class="flex items-end gap-3">
                    <x-ui.button type="submit" variant="outline">Filter</x-ui.button>
                    <a href="{{ route('doctors') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Doctor list</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Menampilkan {{ $doctors->firstItem() ?? 0 }} - {{ $doctors->lastItem() ?? 0 }} dari {{ $doctors->total() }} doctor.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Doctor</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Specialization</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Sections</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Licenses</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Contact</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Fee</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($doctors as $doctor)
                            @php
                                $editPayload = [
                                    'id' => $doctor->id,
                                    'title_prefix' => $doctor->title_prefix ?? '',
                                    'full_name' => $doctor->full_name,
                                    'title_suffix' => $doctor->title_suffix ?? '',
                                    'specialization' => $doctor->specialization,
                                    'consultation_fee' => number_format((float) $doctor->consultation_fee, 2, '.', ''),
                                    'str_number' => $doctor->str_number ?? '',
                                    'str_expired_at' => $doctor->str_expired_at?->format('Y-m-d') ?? '',
                                    'sip_number' => $doctor->sip_number ?? '',
                                    'sip_expired_at' => $doctor->sip_expired_at?->format('Y-m-d') ?? '',
                                    'phone' => $doctor->phone ?? '',
                                    'email' => $doctor->email ?? '',
                                    'address' => $doctor->address ?? '',
                                    'signature_path' => $doctor->signature_path ?? '',
                                    'sections' => $doctor->sections->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
                                    'is_active' => $doctor->is_active,
                                ];
                            @endphp

                            <tr class="align-top">
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $doctor->displayName() }}</p>
                                        @if ($doctor->email)
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $doctor->email }}</p>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $doctor->specialization }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex max-w-sm flex-wrap gap-2">
                                        @forelse ($doctor->sections as $section)
                                            <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 dark:bg-blue-500/10 dark:text-blue-300">
                                                {{ $section->branch?->code ?? 'BR' }} · {{ $section->name }}
                                            </span>
                                        @empty
                                            <span class="text-sm text-gray-400">Belum ada section</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>STR: {{ $doctor->str_number ?: '-' }}</div>
                                    <div class="mt-1 text-xs text-gray-400">
                                        Exp: {{ $doctor->str_expired_at?->format('d M Y') ?? '-' }}
                                    </div>
                                    <div class="mt-3">SIP: {{ $doctor->sip_number ?: '-' }}</div>
                                    <div class="mt-1 text-xs text-gray-400">
                                        Exp: {{ $doctor->sip_expired_at?->format('d M Y') ?? '-' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $doctor->phone ?: '-' }}</div>
                                    <div class="mt-1 text-xs text-gray-400">{{ $doctor->address ?: 'Alamat belum diisi' }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Rp {{ number_format((float) $doctor->consultation_fee, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $doctor->is_active ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-white/[0.04] dark:text-gray-300' }}">
                                        {{ $doctor->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        @if ($abilities['edit'])
                                            <button
                                                type="button"
                                                title="Edit doctor"
                                                aria-label="Edit doctor"
                                                data-payload='@json($editPayload)' x-on:click='openEdit(JSON.parse($el.dataset.payload))'
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white"
                                            >
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                    <path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                                    <path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                </svg>
                                            </button>
                                        @endif

                                        @if ($abilities['delete'])
                                            <form method="POST" action="{{ route('doctors.delete', $doctor) }}" onsubmit="return confirm('Hapus doctor ini?')">
                                                @csrf
                                                <x-ui.icon-button type="submit" variant="danger" title="Hapus doctor">
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                        <path d="M5 7H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                        <path d="M9.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                        <path d="M14.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                        <path d="M7 7L8 18.5C8.06066 19.3284 8.75104 19.9643 9.58166 19.9643H14.4183C15.249 19.9643 15.9393 19.3284 16 18.5L17 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                        <path d="M9 7V5.75C9 5.05964 9.55964 4.5 10.25 4.5H13.75C14.4404 4.5 15 5.05964 15 5.75V7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                    </svg>
                                                </x-ui.icon-button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Belum ada data doctor yang cocok dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                {{ $doctors->links() }}
            </div>
        </section>

        <x-ui.modal show="modalOpen" maxWidth="5xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="mode === 'create' ? 'Tambah Doctor' : 'Update Doctor'"></h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Simpan profil dokter beserta STR, SIP, tarif konsultasi, dan relasi ke section. Jadwal praktek akan kita sambungkan di modul berikutnya.
                        </p>
                    </div>

                    <x-ui.icon-button title="Tutup modal" x-on:click="closeModal()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </x-ui.icon-button>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" x-bind:action="mode === 'create' ? storeAction : `${updateBase}/${form.id}`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" x-bind:value="mode === 'create' ? 'doctor-create' : 'doctor-update'">
                <input type="hidden" name="entity_id" x-bind:value="form.id">
                <input type="hidden" name="current_signature_path" x-bind:value="form.signature_path">

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Prefix</label>
                        <input x-model="form.title_prefix" type="text" name="title_prefix" placeholder="dr." class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Suffix</label>
                        <input x-model="form.title_suffix" type="text" name="title_suffix" placeholder="Sp.A" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Doctor name</label>
                        <input x-model="form.full_name" type="text" name="full_name" placeholder="Nama lengkap dokter" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Specialization</label>
                        <input x-model="form.specialization" type="text" name="specialization" placeholder="mis. Pediatrics" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Consultation fee</label>
                        <input x-model="form.consultation_fee" type="number" step="0.01" min="0" name="consultation_fee" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label>
                        <input x-model="form.phone" type="text" name="phone" placeholder="0812..." class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                        <input x-model="form.email" type="email" name="email" placeholder="doctor@example.com" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Address</label>
                        <input x-model="form.address" type="text" name="address" placeholder="Alamat praktek atau domisili" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div class="md:col-span-2">
                        <div class="mb-1.5 flex items-center justify-between gap-3">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Doctor signature</label>
                            <span class="text-xs text-gray-500 dark:text-gray-400">Dipakai saat cetak referral dan doctor letter.</span>
                        </div>
                        <input type="file" name="signature_file" accept="image/png,image/jpeg,image/webp" class="block w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:file:bg-brand-500/10 dark:file:text-brand-300">
                        <div class="mt-3 rounded-xl border border-dashed border-gray-300 px-4 py-3 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <template x-if="form.signature_path">
                                <div class="space-y-2">
                                    <p>Signature dokter sudah tersimpan dan siap dipakai di dokumen resmi.</p>
                                    <label class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                                        <input x-model="form.remove_signature" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20">
                                        <span>Hapus signature saat ini</span>
                                    </label>
                                </div>
                            </template>
                            <template x-if="!form.signature_path">
                                <p>Belum ada signature tersimpan. Upload PNG/JPG bila dokter ingin tanda tangan muncul di surat.</p>
                            </template>
                            <input type="hidden" name="remove_signature" x-bind:value="form.remove_signature ? 1 : 0">
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">STR number</label>
                        <input x-model="form.str_number" type="text" name="str_number" placeholder="STR-001" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">STR expired at</label>
                        <input x-model="form.str_expired_at" type="date" name="str_expired_at" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">SIP number</label>
                        <input x-model="form.sip_number" type="text" name="sip_number" placeholder="SIP-001" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">SIP expired at</label>
                        <input x-model="form.sip_expired_at" type="date" name="sip_expired_at" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div class="md:col-span-2">
                        <div class="mb-1.5 flex items-center justify-between gap-3">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Section assignments</label>
                            <span class="text-xs text-gray-500 dark:text-gray-400">Boleh kosong untuk tahap awal.</span>
                        </div>

                        @if ($sectionGroups->isNotEmpty())
                            <div class="grid gap-4 lg:grid-cols-2">
                                @foreach ($sectionGroups as $branchGroup)
                                    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-700">
                                        <div class="mb-3">
                                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $branchGroup->name }}</h4>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $branchGroup->code }}</p>
                                        </div>

                                        <div class="space-y-3">
                                            @foreach ($branchGroup->sections as $section)
                                                <label class="flex items-start gap-3 rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.03]">
                                                    <input
                                                        x-model="form.sections"
                                                        type="checkbox"
                                                        name="sections[]"
                                                        value="{{ $section->id }}"
                                                        class="mt-0.5 h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20"
                                                    >
                                                    <span class="flex-1">
                                                        <span class="block font-medium text-gray-900 dark:text-white">{{ $section->name }}</span>
                                                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                                                            {{ $section->code }} · {{ $section->type === 'emergency' ? 'Emergency' : 'Regular' }} · {{ $section->is_active ? 'Active' : 'Inactive' }}
                                                        </span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="rounded-2xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                Belum ada section yang tersedia. Doctor bisa disimpan dulu, lalu relasi section ditambahkan setelah section dibuat.
                            </div>
                        @endif
                    </div>

                    <div class="flex items-end md:col-span-2">
                        <div>
                            <input type="hidden" name="is_active" x-bind:value="form.is_active ? 1 : 0">
                            <label class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                                <input x-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20">
                                <span>Doctor aktif</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="closeModal()">Batal</x-ui.button>
                    <x-ui.button type="submit" x-text="mode === 'create' ? 'Simpan Doctor' : 'Update Doctor'"></x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    </div>
@endsection

