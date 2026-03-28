<section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
        <p class="text-sm text-gray-500 dark:text-gray-400">Visit siap farmasi</p>
        <p class="mt-3 text-3xl font-semibold text-gray-900 dark:text-white">{{ number_format($dispensingMetrics['visits_ready']) }}</p>
        <p class="mt-2 text-xs uppercase tracking-[0.16em] text-gray-400">SOAP sudah ada</p>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
        <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada prescription</p>
        <p class="mt-3 text-3xl font-semibold text-gray-900 dark:text-white">{{ number_format($dispensingMetrics['without_prescription']) }}</p>
        <p class="mt-2 text-xs uppercase tracking-[0.16em] text-gray-400">Butuh order dokter</p>
    </div>
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-theme-sm dark:border-amber-500/20 dark:bg-amber-500/10">
        <p class="text-sm text-amber-700 dark:text-amber-300">Pending dispense items</p>
        <p class="mt-3 text-3xl font-semibold text-amber-700 dark:text-amber-200">{{ number_format($dispensingMetrics['pending_dispense_items']) }}</p>
        <p class="mt-2 text-xs uppercase tracking-[0.16em] text-amber-500 dark:text-amber-200">Perlu fulfillment</p>
    </div>
    <div class="rounded-2xl border border-brand-200 bg-brand-50 p-5 shadow-theme-sm dark:border-brand-500/20 dark:bg-brand-500/10">
        <p class="text-sm text-brand-700 dark:text-brand-300">Partial dispensed</p>
        <p class="mt-3 text-3xl font-semibold text-brand-700 dark:text-brand-200">{{ number_format($dispensingMetrics['partial_dispensed_prescriptions']) }}</p>
        <p class="mt-2 text-xs uppercase tracking-[0.16em] text-brand-500 dark:text-brand-200">Masih ada sisa item</p>
    </div>
    <div class="rounded-2xl border border-green-200 bg-green-50 p-5 shadow-theme-sm dark:border-green-500/20 dark:bg-green-500/10">
        <p class="text-sm text-green-700 dark:text-green-300">Fully dispensed</p>
        <p class="mt-3 text-3xl font-semibold text-green-700 dark:text-green-200">{{ number_format($dispensingMetrics['dispensed_prescriptions']) }}</p>
        <p class="mt-2 text-xs uppercase tracking-[0.16em] text-green-500 dark:text-green-200">Siap billing checkout</p>
    </div>
</section>
