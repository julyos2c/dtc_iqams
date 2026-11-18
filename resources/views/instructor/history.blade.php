<x-instructor-layout title="My Attendance">
    <div class="mx-auto max-w-[1500px] space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><h2 class="text-lg font-bold text-[#10294b]">Attendance history</h2><p class="mt-1 text-sm text-slate-500">Review your workday attendance records and scan times.</p></div>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="attendance-filter-title">
            <div><h3 id="attendance-filter-title" class="text-base font-bold text-[#10294b]">Filter records</h3><p class="mt-1 text-sm text-slate-500">Choose a date range and refine by attendance status or punctuality.</p></div>
            @if ($errors->any())<div role="alert" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
            <form class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div><label for="attendance-from" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">From</label><input id="attendance-from" class="block min-h-11 w-full rounded-lg border-slate-300 text-sm text-slate-800 focus:border-blue-600 focus:ring-blue-600" type="date" name="from" value="{{ $from->toDateString() }}"></div>
                <div><label for="attendance-to" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">To</label><input id="attendance-to" class="block min-h-11 w-full rounded-lg border-slate-300 text-sm text-slate-800 focus:border-blue-600 focus:ring-blue-600" type="date" name="to" value="{{ $to->toDateString() }}"></div>
                <div><label for="attendance-status" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Status</label><select id="attendance-status" class="block min-h-11 w-full rounded-lg border-slate-300 text-sm text-slate-800 focus:border-blue-600 focus:ring-blue-600" name="status"><option value="">All statuses</option>@foreach (['Present', 'On Leave', 'In Progress', 'Incomplete', 'Absent'] as $v)<option @selected(request('status') === $v)>{{ $v }}</option>@endforeach</select></div>
                <div><label for="attendance-punctuality" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Punctuality</label><select id="attendance-punctuality" class="block min-h-11 w-full rounded-lg border-slate-300 text-sm text-slate-800 focus:border-blue-600 focus:ring-blue-600" name="punctuality"><option value="">All punctuality</option>@foreach (['On Time', 'Late', 'Early Out', 'Incomplete'] as $v)<option @selected(request('punctuality') === $v)>{{ $v }}</option>@endforeach</select></div>
                <div class="flex flex-wrap items-center gap-3 sm:col-span-2 xl:col-span-4"><button class="inline-flex min-h-11 items-center justify-center rounded-lg bg-blue-700 px-5 text-sm font-semibold text-white transition hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">Apply filters</button><a href="{{ route('instructor.attendance') }}" class="inline-flex min-h-11 items-center px-2 text-sm font-semibold text-slate-600 hover:text-blue-700">Reset</a></div>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="attendance-records-title">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6"><div><h3 id="attendance-records-title" class="text-base font-bold text-[#10294b]">Attendance records</h3><p class="mt-1 text-sm text-slate-500">{{ $from->format('M j, Y') }} – {{ $to->format('M j, Y') }} · Read-only</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $daysPaginator->total() }} {{ $daysPaginator->total() === 1 ? 'record' : 'records' }}</span></div>
            @include('personnel.partials.attendance-table')
        </section>
    </div>
</x-instructor-layout>
