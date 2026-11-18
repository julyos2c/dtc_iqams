<div data-report-summary class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4">
    <div class="flex h-full flex-col items-start rounded-xl border border-gray-200 bg-white p-4 sm:p-5">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600"><x-heroicon-o-users class="h-5 w-5" aria-hidden="true" /></span>
        <p class="mt-3 text-2xl font-bold tabular-nums text-gray-900">{{ $summary->affected }}</p>
        <p class="mt-1 text-sm font-medium text-gray-500">Affected Students</p>
    </div>
    <div class="flex h-full flex-col items-start rounded-xl border border-gray-200 bg-white p-4 sm:p-5">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600"><x-heroicon-o-clipboard-document-check class="h-5 w-5" aria-hidden="true" /></span>
        <p class="mt-3 text-2xl font-bold tabular-nums text-gray-900">{{ $summary->absences }}</p>
        <p class="mt-1 text-sm font-medium text-gray-500">Total Absences</p>
    </div>
    <div class="flex h-full flex-col items-start rounded-xl border border-gray-200 bg-white p-4 sm:p-5">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-amber-700"><x-heroicon-o-exclamation-triangle class="h-5 w-5" aria-hidden="true" /></span>
        <p class="mt-3 text-2xl font-bold tabular-nums text-gray-900">{{ $summary->at_risk }}</p>
        <p class="mt-1 text-sm font-medium text-gray-500">At Risk</p>
    </div>
</div>
