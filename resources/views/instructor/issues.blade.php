<x-instructor-layout title="Attendance Issues">
    <div class="mx-auto max-w-[1500px] space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><p class="text-sm text-slate-600">Review your attendance and student absence warnings in one place.</p><p class="mt-1 text-xs text-slate-500">Your attendance issues are shown for {{ now()->format('F Y') }}.</p></div>
            <a href="{{ route('instructor.attendance') }}" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-blue-300 hover:text-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700"><x-heroicon-o-clock class="h-4 w-4" /> View attendance history</a>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-amber-50 text-amber-700"><x-heroicon-o-exclamation-triangle class="h-5 w-5" /></span><div><p class="text-sm font-medium text-slate-500">My attendance issues <span class="text-xs">· this month</span></p><p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $days->count() }}</p></div></div>
            <div class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-rose-50 text-rose-700"><x-heroicon-o-user-group class="h-5 w-5" /></span><div><p class="text-sm font-medium text-slate-500">Student warnings <span class="text-xs">· by subject</span></p><p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $studentWarnings->count() }}</p></div></div>
        </div>
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="student-risk-title">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-5 sm:px-6">
                <div><div class="flex items-center gap-2"><h2 id="student-risk-title" class="text-base font-bold text-[#10294b]">Student dropout-risk warnings</h2><span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">{{ $studentWarnings->count() }}</span></div><p class="mt-1 text-sm text-slate-500">Students with at least {{ \App\Services\StudentAbsenceWarningService::THRESHOLD }} absences in one of your subjects.</p></div>
                <a href="{{ route('instructor.schedule') }}" class="inline-flex min-h-9 items-center gap-1 text-sm font-semibold text-blue-700 hover:text-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">Review class attendance <span aria-hidden="true">→</span></a>
            </div>
            @forelse($studentWarnings as $warning)
                <article class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 last:border-b-0 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div class="min-w-0"><p class="font-semibold text-slate-900">{{ $warning['student_name'] }} <span class="font-normal text-slate-500">· {{ $warning['student_no'] }}</span></p><p class="mt-1 text-sm text-slate-500">{{ $warning['subject_code'] }} · {{ $warning['subject_name'] }} · {{ $warning['section_name'] }}</p><p class="mt-2 text-xs text-slate-500">Review the class attendance history for the affected dates.</p></div>
                    <span class="inline-flex self-start whitespace-nowrap rounded-full bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 sm:self-center">{{ $warning['absence_count'] }} absences · At risk</span>
                </article>
            @empty
                <div class="px-5 py-8 text-center sm:px-6"><span class="mx-auto grid h-10 w-10 place-items-center rounded-full bg-emerald-50 text-emerald-600"><x-heroicon-o-check-circle class="h-5 w-5" /></span><p class="mt-3 text-sm font-semibold text-slate-700">No student dropout-risk warnings.</p><p class="mt-1 text-sm text-slate-500">No students have reached the absence warning threshold.</p></div>
            @endforelse
        </section>
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
</x-instructor-layout>
