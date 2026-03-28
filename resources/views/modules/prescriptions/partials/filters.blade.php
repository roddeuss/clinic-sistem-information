<section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
    <form method="GET" action="{{ route('prescriptions') }}" class="grid gap-4 md:grid-cols-[1.1fr_220px_180px_180px_auto]">
        <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari patient, RM, doctor, atau item obat" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
        <select name="branch" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
            <option value="">Semua branch</option>
            @foreach ($branchOptions as $branchOption)
                <option value="{{ $branchOption->id }}" @selected($filters['branch'] === (string) $branchOption->id)>{{ $branchOption->code }} - {{ $branchOption->name }}</option>
            @endforeach
        </select>
        <select name="status" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
            <option value="">Semua status</option>
            <option value="none" @selected($filters['status'] === 'none')>No prescription</option>
            <option value="draft" @selected($filters['status'] === 'draft')>Draft</option>
            <option value="finalized" @selected($filters['status'] === 'finalized')>Finalized</option>
            <option value="partial_dispensed" @selected($filters['status'] === 'partial_dispensed')>Partial dispensed</option>
            <option value="dispensed" @selected($filters['status'] === 'dispensed')>Dispensed</option>
        </select>
        <input type="date" name="date" value="{{ $filters['date'] }}" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
        <div class="flex gap-3">
            <x-ui.button type="submit" variant="outline">Filter</x-ui.button>
            <a href="{{ route('prescriptions') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">Reset</a>
        </div>
    </form>
</section>
