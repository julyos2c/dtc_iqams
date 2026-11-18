<x-staff-layout title="Attendance Issues">
    <div class="mx-auto max-w-[1500px] space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><p class="text-sm text-slate-600">Review your attendance issues.</p><p class="mt-1 text-xs text-slate-500">Your attendance issues are shown for {{ now()->format('F Y') }}.</p></div>
            <a href="{{ route('staff.attendance.history') }}" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-blue-300 hover:text-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700"><x-heroicon-o-clock class="h-4 w-4" /> View attendance history</a>
        </div>
        <div class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-amber-50 text-amber-700"><x-heroicon-o-exclamation-triangle class="h-5 w-5" /></span><div><p class="text-sm font-medium text-slate-500">My attendance issues <span class="text-xs">? this month</span></p><p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $days->count() }}</p></div></div>
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="personal-issues-title">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-5 sm:px-6"><div><h2 id="personal-issues-title" class="text-base font-bold text-[#10294b]">My attendance issues</h2><p class="mt-1 text-sm text-slate-500">Detected attendance issues for {{ now()->format('F Y') }}.</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $days->count() }} {{ $days->count() === 1 ? 'day' : 'days' }}</span></div>
            @forelse($days as $day)
                <article class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 last:border-b-0 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div class="flex items-start gap-3"><span class="mt-0.5 grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-amber-50 text-amber-700"><x-heroicon-o-calendar-days class="h-5 w-5" /></span><div><p class="font-semibold text-slate-900">{{ $day['date']->format('l, F j, Y') }}</p><p class="mt-1 text-sm text-slate-500">@if($day['status'] === 'Absent') No time-in recorded @elseif($day['isIncomplete']) Missing: {{ $day['events']->filter(fn($value) => !$value)->keys()->map(fn($value) => str($value)->replace('_', ' ')->title())->join(', ') }} @else {{ $day['notes']->join(', ') }} @endif</p></div></div>
                    <span class="inline-flex self-start rounded-full bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 sm:self-center">{{ $day['isIncomplete'] ? 'Incomplete' : $day['status'] }} · {{ $day['punctuality'] }}</span>
                </article>
            @empty
                <div class="px-5 py-8 text-center sm:px-6"><span class="mx-auto grid h-10 w-10 place-items-center rounded-full bg-emerald-50 text-emerald-600"><x-heroicon-o-check-circle class="h-5 w-5" /></span><p class="mt-3 text-sm font-semibold text-slate-700">No attendance issues found this month.</p><p class="mt-1 text-sm text-slate-500">Any detected issues will appear here.</p></div>
            @endforelse
        </section>
    </div>
</x-staff-layout>
