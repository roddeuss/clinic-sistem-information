<section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Pharmacy dispensing desk</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">
                Dokter membuat order resep, farmasi memverifikasi alert alergi, ingredient, duplicate therapy, lalu stok batch FEFO baru berkurang saat item benar-benar didispense.
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            @if ($abilities['create'])
                <x-ui.button type="button" x-on:click="openCreatePrescription()">Tambah Prescription</x-ui.button>
                <button type="button" x-on:click="openCreateItem()" @disabled($prescriptionOptions->isEmpty()) class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">
                    Tambah Item
                </button>
            @endif
        </div>
    </div>
</section>
