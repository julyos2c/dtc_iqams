<x-staff-layout title="Monthly Summary">
    @php($selectedMonth = \Carbon\Carbon::create($year, $month, 1)->format('F Y'))
    <div class="mx-auto max-w-[1500px] space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><h2 class="text-lg font-bold text-[#10294b]">Monthly attendance summary</h2><p class="mt-1 text-sm text-slate-500">Review your attendance and working hours for the selected month.</p></div>
            <a href="{{ route('staff.attendance.issues') }}" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-blue-300 hover:text-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700"><x-heroicon-o-exclamation-triangle class="h-4 w-4" /> View attendance issues</a>
        </div>

        <form class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="min-w-[150px] flex-1 sm:max-w-[220px]"><label for="summary-month" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Month</label><select id="summary-month" name="month" class="block min-h-11 w-full rounded-lg border-slate-300 text-sm text-slate-800 focus:border-blue-600 focus:ring-blue-600">@foreach(range(1, 12) as $m)<option value="{{ $m }}" @selected($month === $m)>{{ \Carbon\Carbon::create(null, $m)->format('F') }}</option>@endforeach</select></div>
            <div class="w-28"><label for="summary-year" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Year</label><input id="summary-year" name="year" type="number" min="2000" max="2100" value="{{ $year }}" class="block min-h-11 w-full rounded-lg border-slate-300 text-sm text-slate-800 focus:border-blue-600 focus:ring-blue-600"></div>
            <button class="inline-flex min-h-11 items-center justify-center rounded-lg bg-blue-700 px-5 text-sm font-semibold text-white transition hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">View summary</button>
            <p class="w-full text-xs text-slate-500 sm:ml-auto sm:w-auto">Showing <span class="font-semibold text-slate-700">{{ $selectedMonth }}</span></p>
        </form>

        <section aria-labelledby="summary-overview-title">
            <div class="mb-3 flex items-center justify-between"><h3 id="summary-overview-title" class="text-sm font-bold text-slate-700">Overview</h3><span class="text-xs font-medium text-slate-500">{{ $selectedMonth }}</span></div>
            <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                @foreach ([['Attendance', $totals['percentage'].'%', 'Based on present and absent days', 'blue'], ['Present', $totals['presentDays'], 'Completed attendance days', 'emerald'], ['Absent', $totals['absentDays'], 'Days marked absent', 'rose'], ['Total hours', intdiv($totals['totalMinutes'], 60).'h '.$totals['totalMinutes'] % 60 .'m', 'Recorded working time', 'violet']] as [$label, $value, $description, $tone])
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5"><div class="flex items-center gap-2"><span class="h-2 w-2 rounded-full {{ ['blue' => 'bg-blue-500', 'emerald' => 'bg-emerald-500', 'rose' => 'bg-rose-500', 'violet' => 'bg-violet-500'][$tone] }}"></span><p class="text-sm font-semibold text-slate-600">{{ $label }}</p></div><p class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900">{{ $value }}</p><p class="mt-1 text-xs text-slate-500">{{ $description }}</p></article>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="summary-breakdown-title">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6"><h3 id="summary-breakdown-title" class="text-base font-bold text-[#10294b]">Monthly breakdown</h3><p class="mt-1 text-sm text-slate-500">Working days, leave, and attendance details.</p></div>
            <dl class="grid grid-cols-2 divide-x divide-y divide-slate-100 md:grid-cols-4">
                @foreach (['Expected working days' => 'expectedDays', 'Excluded / non-working' => 'excludedDays', 'Approved leave (working days)' => 'leaveDays', 'Approved leave (calendar days)' => 'calendarLeaveDays', 'Late' => 'lateCount', 'Early out' => 'earlyOutCount', 'Incomplete' => 'incompleteCount', 'In progress' => 'inProgressCount'] as $label => $key)
                    <div class="px-5 py-4 sm:px-6"><dt class="text-xs font-medium leading-5 text-slate-500">{{ $label }}</dt><dd class="mt-1 text-xl font-bold text-slate-900">{{ $totals[$key] }}</dd></div>
                @endforeach
            </dl>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="summary-records-title">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4 sm:px-6"><div><h3 id="summary-records-title" class="text-base font-bold text-[#10294b]">Daily attendance</h3><p class="mt-1 text-sm text-slate-500">Attendance records and status for each day.</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $selectedMonth }}</span></div>
            @include('personnel.partials.attendance-table')
        </section>
    </div>
</x-staff-layout>
