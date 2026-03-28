@php
    $patientModalOpen = $errors->any() && in_array(old('form_context'), ['patient-create', 'patient-update'], true);
    $patientModalMode = old('form_context') === 'patient-update' ? 'update' : 'create';
    $archiveModalOpen = $errors->any() && old('form_context') === 'patient-archive';
    $patientModalForm = [
        'id' => old('entity_id'),
        'branch_id' => (string) old('branch_id', ''),
        'full_name' => old('full_name', ''),
        'gender' => old('gender', 'female'),
        'date_of_birth' => old('date_of_birth', ''),
        'nik' => old('nik', ''),
        'phone' => old('phone', ''),
        'email' => old('email', ''),
        'province_code' => old('province_code', ''),
        'city_code' => old('city_code', ''),
        'district_code' => old('district_code', ''),
        'village_code' => old('village_code', ''),
        'address_line' => old('address_line', ''),
        'allergy_notes' => old('allergy_notes', ''),
        'is_active' => (int) old('is_active', 1) === 1,
    ];
    $archiveModalForm = [
        'id' => old('entity_id'),
        'full_name' => old('full_name', ''),
        'reason' => old('reason', ''),
    ];
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Patients" />

    <div
        x-data="{
            modalOpen: @js($patientModalOpen),
            mode: @js($patientModalMode),
            archiveModalOpen: @js($archiveModalOpen),
            storeAction: @js(route('patients.store')),
            updateBase: @js(url('/patients')),
            archiveBase: @js(url('/patients')),
            provincesUrl: @js(route('regions.provinces')),
            citiesUrl: @js(route('regions.cities')),
            districtsUrl: @js(route('regions.districts')),
            villagesUrl: @js(route('regions.villages')),
            form: @js($patientModalForm),
            archiveForm: @js($archiveModalForm),
            provinces: [],
            cities: [],
            districts: [],
            villages: [],
            async init() {
                await this.loadProvinces();
                if (this.form.province_code) await this.loadCities();
                if (this.form.city_code) await this.loadDistricts();
                if (this.form.district_code) await this.loadVillages();
            },
            async openCreate() {
                this.mode = 'create';
                this.form = {
                    id: null,
                    branch_id: '',
                    full_name: '',
                    gender: 'female',
                    date_of_birth: '',
                    nik: '',
                    phone: '',
                    email: '',
                    province_code: '',
                    city_code: '',
                    district_code: '',
                    village_code: '',
                    address_line: '',
                    allergy_notes: '',
                    is_active: true,
                };
                this.cities = [];
                this.districts = [];
                this.villages = [];
                this.modalOpen = true;
            },
            async openEdit(payload) {
                this.mode = 'update';
                this.form = payload;
                this.modalOpen = true;
                await this.loadProvinces();
                if (this.form.province_code) await this.loadCities();
                if (this.form.city_code) await this.loadDistricts();
                if (this.form.district_code) await this.loadVillages();
            },
            closeModal() {
                this.modalOpen = false;
            },
            openArchive(payload) {
                this.archiveForm = {
                    id: payload.id,
                    full_name: payload.full_name,
                    reason: '',
                };
                this.archiveModalOpen = true;
            },
            closeArchiveModal() {
                this.archiveModalOpen = false;
            },
            async onProvinceChange() {
                this.form.city_code = '';
                this.form.district_code = '';
                this.form.village_code = '';
                this.cities = [];
                this.districts = [];
                this.villages = [];
                if (this.form.province_code) await this.loadCities();
            },
            async onCityChange() {
                this.form.district_code = '';
                this.form.village_code = '';
                this.districts = [];
                this.villages = [];
                if (this.form.city_code) await this.loadDistricts();
            },
            async onDistrictChange() {
                this.form.village_code = '';
                this.villages = [];
                if (this.form.district_code) await this.loadVillages();
            },
            async loadProvinces() {
                this.provinces = await this.fetchOptions(this.provincesUrl);
            },
            async loadCities() {
                this.cities = await this.fetchOptions(`${this.citiesUrl}?province_code=${encodeURIComponent(this.form.province_code)}`);
            },
            async loadDistricts() {
                this.districts = await this.fetchOptions(`${this.districtsUrl}?city_code=${encodeURIComponent(this.form.city_code)}`);
            },
            async loadVillages() {
                this.villages = await this.fetchOptions(`${this.villagesUrl}?district_code=${encodeURIComponent(this.form.district_code)}`);
            },
            async fetchOptions(url) {
                const response = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok) return [];
                const payload = await response.json();
                return payload.data ?? [];
            },
            nameOf(options, code) {
                const match = options.find((item) => item.code === code);
                return match ? match.name : '';
            },
        }"
        x-init="init()"
        @keydown.escape.window="closeModal(); closeArchiveModal()"
        class="space-y-6"
    >
        @if (session('status'))
            <x-ui.alert variant="success" :message="session('status')" />
        @endif

        @if ($errors->any())
            <x-ui.alert variant="error" :message="$errors->first()" />
        @endif

        @if (session('duplicate_warnings'))
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100">
                <p class="font-medium">Ada kandidat data pasien serupa. Data tetap disimpan, tapi mohon dicek ulang.</p>
                <div class="mt-2 space-y-1 text-xs">
                    @foreach (session('duplicate_warnings', []) as $warning)
                        <div>{{ $warning['name'] }} | {{ $warning['phone'] }} | {{ $warning['date_of_birth'] }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Data table patients</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">
                        Kelola master pasien global, nomor RM per branch, kontak, alergi dasar, dan snapshot wilayah dari API eksternal.
                    </p>
                </div>

                @if ($abilities['create'])
                    <x-ui.button type="button" x-on:click="openCreate()">Tambah Patient</x-ui.button>
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('patients') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari nama, NIK, HP, RM, atau wilayah" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Branch</label>
                    <select name="branch" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Semua branch</option>
                        @foreach ($branchOptions as $branchOption)
                            <option value="{{ $branchOption->id }}" @selected($filters['branch'] === (string) $branchOption->id)>{{ $branchOption->code }} - {{ $branchOption->name }}</option>
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

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Sort by</label>
                    <select name="sort_by" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        @foreach ($sortOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['sort_by'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Direction</label>
                    <select name="sort_direction" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="asc" @selected($filters['sort_direction'] === 'asc')>Ascending</option>
                        <option value="desc" @selected($filters['sort_direction'] === 'desc')>Descending</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Per page</label>
                    <select name="per_page" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        @foreach ($perPageOptions as $perPageOption)
                            <option value="{{ $perPageOption }}" @selected($filters['per_page'] === $perPageOption)>{{ $perPageOption }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end gap-3 xl:col-span-1">
                    <x-ui.button type="submit" variant="outline">Filter</x-ui.button>
                    <a href="{{ route('patients') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">Reset</a>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Patient list</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Menampilkan {{ $patients->firstItem() ?? 0 }} - {{ $patients->lastItem() ?? 0 }} dari {{ $patients->total() }} patient.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Patient</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Contact</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Wilayah</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">RM</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($patients as $patient)
                            <tr class="align-top">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $patient->full_name }}</p>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $patient->nik ?: 'NIK belum diisi' }}</p>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $patient->date_of_birth?->format('d M Y') }} | {{ $patient->gender === 'male' ? 'Male' : 'Female' }}</p>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $patient->phone }}</div>
                                    <div class="mt-1">{{ $patient->email ?: '-' }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ collect([$patient->village_name, $patient->district_name, $patient->city_name, $patient->province_name])->filter()->implode(', ') ?: '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    @if ($patient->branchRecords->isEmpty())
                                        -
                                    @else
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($patient->branchRecords as $record)
                                                <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-white/[0.04] dark:text-gray-200">
                                                    {{ $record->branch?->code ?? '-' }} - {{ $record->medical_record_no }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $patient->is_active ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-white/[0.04] dark:text-gray-300' }}">
                                        {{ $patient->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    @php
                                        $editPayload = [
                                            'id' => $patient->id,
                                            'branch_id' => '',
                                            'full_name' => $patient->full_name,
                                            'gender' => $patient->gender,
                                            'date_of_birth' => $patient->date_of_birth?->format('Y-m-d'),
                                            'nik' => $patient->nik,
                                            'phone' => $patient->phone,
                                            'email' => $patient->email,
                                            'province_code' => $patient->province_code,
                                            'city_code' => $patient->city_code,
                                            'district_code' => $patient->district_code,
                                            'village_code' => $patient->village_code,
                                            'address_line' => $patient->address_line,
                                            'allergy_notes' => $patient->allergy_notes,
                                            'is_active' => $patient->is_active,
                                        ];
                                        $archivePayload = [
                                            'id' => $patient->id,
                                            'full_name' => $patient->full_name,
                                        ];
                                    @endphp
                                    <div class="flex justify-end gap-2">
                                        @if ($abilities['edit'])
                                            <button type="button" data-payload='@json($editPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)' x-on:click='openEdit(JSON.parse($el.dataset.payload))' class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                    <path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                                    <path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                </svg>
                                            </button>
                                        @endif
                                        @if ($abilities['delete'])
                                            <button type="button" data-payload='@json($archivePayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)' x-on:click='openArchive(JSON.parse($el.dataset.payload))' class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-200 bg-white text-red-600 transition hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500/20 dark:border-red-500/30 dark:bg-gray-900 dark:text-red-300 dark:hover:bg-red-500/10">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                    <path d="M10 11V17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                    <path d="M14 11V17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                    <path d="M5 7H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                    <path d="M8 7L9 5H15L16 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                    <path d="M18 7L17.2987 17.5193C17.2285 18.5738 16.3519 19.4 15.2951 19.4H8.70491C7.6481 19.4 6.77152 18.5738 6.70136 17.5193L6 7" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada data patient yang cocok dengan filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">{{ $patients->links() }}</div>
        </section>

        <x-ui.modal show="modalOpen" maxWidth="4xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="mode === 'create' ? 'Tambah Patient' : 'Update Patient'"></h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Alamat boleh dikosongkan dulu. Kalau branch dipilih, sistem membuat RM awal untuk branch tersebut.</p>
                    </div>
                    <x-ui.icon-button title="Tutup modal" x-on:click="closeModal()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </x-ui.icon-button>
                </div>
            </div>

            <form method="POST" x-bind:action="mode === 'create' ? storeAction : `${updateBase}/${form.id}`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" x-bind:value="mode === 'create' ? 'patient-create' : 'patient-update'">
                <input type="hidden" name="entity_id" x-bind:value="form.id">
                <input type="hidden" name="province_name" x-bind:value="nameOf(provinces, form.province_code)">
                <input type="hidden" name="city_name" x-bind:value="nameOf(cities, form.city_code)">
                <input type="hidden" name="district_name" x-bind:value="nameOf(districts, form.district_code)">
                <input type="hidden" name="village_name" x-bind:value="nameOf(villages, form.village_code)">

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Branch awal untuk RM</label>
                        <select x-model="form.branch_id" name="branch_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="">Buat tanpa RM dulu</option>
                            @foreach ($branchOptions as $branchOption)
                                <option value="{{ $branchOption->id }}">{{ $branchOption->code }} - {{ $branchOption->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama patient</label>
                        <input x-model="form.full_name" type="text" name="full_name" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Gender</label>
                        <select x-model="form.gender" name="gender" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="female">Female</option>
                            <option value="male">Male</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Tanggal lahir</label>
                        <input x-model="form.date_of_birth" type="date" name="date_of_birth" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">NIK</label>
                        <input x-model="form.nik" type="text" name="nik" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">No. HP</label>
                        <input x-model="form.phone" type="text" name="phone" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                        <input x-model="form.email" type="email" name="email" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div class="flex items-end">
                        <div>
                            <input type="hidden" name="is_active" x-bind:value="form.is_active ? 1 : 0">
                            <label class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                                <input x-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20">
                                <span>Patient aktif</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Provinsi</label>
                        <select x-model="form.province_code" @change="onProvinceChange()" name="province_code" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="">Pilih provinsi</option>
                            <template x-for="province in provinces" :key="province.code">
                                <option :value="province.code" x-text="province.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Kota / Kabupaten</label>
                        <select x-model="form.city_code" @change="onCityChange()" name="city_code" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="">Pilih kota / kabupaten</option>
                            <template x-for="city in cities" :key="city.code">
                                <option :value="city.code" x-text="city.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Kecamatan</label>
                        <select x-model="form.district_code" @change="onDistrictChange()" name="district_code" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="">Pilih kecamatan</option>
                            <template x-for="district in districts" :key="district.code">
                                <option :value="district.code" x-text="district.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Kelurahan</label>
                        <select x-model="form.village_code" name="village_code" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="">Pilih kelurahan</option>
                            <template x-for="village in villages" :key="village.code">
                                <option :value="village.code" x-text="village.name"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Alamat detail</label>
                        <textarea x-model="form.address_line" name="address_line" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Riwayat alergi dasar</label>
                        <textarea x-model="form.allergy_notes" name="allergy_notes" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="closeModal()">Batal</x-ui.button>
                    <x-ui.button type="submit" x-text="mode === 'create' ? 'Simpan Patient' : 'Update Patient'"></x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.modal show="archiveModalOpen" maxWidth="lg">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Nonaktifkan Patient</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Patient akan tetap tersimpan sebagai data historis, tetapi tidak bisa dipakai untuk registrasi baru.</p>
                    </div>
                    <x-ui.icon-button title="Tutup modal" x-on:click="closeArchiveModal()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </x-ui.icon-button>
                </div>
            </div>

            <form method="POST" x-bind:action="`${archiveBase}/${archiveForm.id}/archive`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" value="patient-archive">
                <input type="hidden" name="entity_id" x-bind:value="archiveForm.id">
                <input type="hidden" name="full_name" x-bind:value="archiveForm.full_name">

                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100">
                    <p class="font-medium" x-text="`Patient yang akan dinonaktifkan: ${archiveForm.full_name || '-'}`"></p>
                    <p class="mt-1 text-xs">Gunakan langkah ini hanya jika patient memang tidak boleh dipakai untuk kunjungan baru.</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Alasan nonaktif</label>
                    <textarea x-model="archiveForm.reason" name="reason" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="closeArchiveModal()">Batal</x-ui.button>
                    <x-ui.button type="submit" variant="outline" className="border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/10">Nonaktifkan Patient</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    </div>
@endsection

