@php
    $progressPeriods = [
        'morning_in' => 'Morning In',
        'lunch_out' => 'Lunch Out',
        'afternoon_in' => 'Afternoon In',
        'final_out' => 'Final Out',
    ];
    $greeting = now()->hour < 12 ? 'Good morning' : (now()->hour < 18 ? 'Good afternoon' : 'Good evening');
    $statusClasses = [
        'Present' => 'bg-emerald-100 text-emerald-700',
        'Absent' => 'bg-red-100 text-red-700',
        'In Progress' => 'bg-amber-100 text-amber-700',
        'Incomplete' => 'bg-amber-100 text-amber-700',
        'Not Started' => 'bg-slate-100 text-slate-600',
        'On Leave' => 'bg-sky-100 text-sky-700',
    ];
    $workStart = config('attendance.personnel_windows.instructor.morning_in.window_start', '08:00');
    $workEnd = config('attendance.personnel_windows.instructor.final_out.window_end', '17:00');
    $chartWidth = 760;
    $chartHeight = 230;
    $chartTop = 16;
    $chartRight = 18;
    $chartBottom = 38;
    $chartLeft = 46;
    $chartPlotWidth = $chartWidth - $chartLeft - $chartRight;
    $chartPlotHeight = $chartHeight - $chartTop - $chartBottom;
    $chartDays = $monthDays->values();
    $chartLabelInterval = max(1, (int) ceil(max(1, $chartDays->count()) / 7));
    $chartPoints = $chartDays->map(function (array $day, int $index) use (
        $chartDays,
        $chartLeft,
        $chartPlotWidth,
        $chartTop,
        $chartPlotHeight,
        $chartLabelInterval,
    ): array {
        $value = $day['isExcluded'] || $day['leave'] ? null : (int) $day['progressPercentage'];
        $x =
            $chartLeft +
            ($chartDays->count() > 1 ? ($index * $chartPlotWidth) / ($chartDays->count() - 1) : $chartPlotWidth / 2);
        $y = $chartTop + ((100 - ($value ?? 0)) * $chartPlotHeight) / 100;
        $color = match (true) {
            $value === null => '#cbd5e1',
            $day['status'] === 'Absent' => '#f43f5e',
            $day['status'] === 'Not Started' => '#94a3b8',
            $day['isIncomplete'] => '#fbbf24',
            $day['late'] => '#fb923c',
            $day['early'] => '#8b5cf6',
            default => '#34d399',
        };

        return [
            'day' => $day,
            'value' => $value,
            'x' => round($x, 2),
            'y' => round($y, 2),
            'color' => $color,
            'showLabel' => $index === 0 || $index === $chartDays->count() - 1 || $index % $chartLabelInterval === 0,
        ];
    });
    $chartSegments = [];
    $currentSegment = [];
    foreach ($chartPoints as $point) {
        if ($point['value'] === null) {
            if ($currentSegment) {
                $chartSegments[] = implode(' ', $currentSegment);
                $currentSegment = [];
            }
            continue;
        }
        $currentSegment[] = $point['x'] . ',' . $point['y'];
    }
    if ($currentSegment) {
        $chartSegments[] = implode(' ', $currentSegment);
    }
    $chartAreas = array_map(function (string $segment) use ($chartTop, $chartPlotHeight): string {
        $points = explode(' ', $segment);
        $firstX = explode(',', $points[0])[0];
        $lastX = explode(',', end($points))[0];
        $baseline = $chartTop + $chartPlotHeight;

        return "$firstX,$baseline $segment $lastX,$baseline";
    }, $chartSegments);
    $chartHasData = $chartPoints->contains(fn(array $point) => $point['value'] !== null);
@endphp
<x-instructor-layout title="Dashboard">
    <div x-data="instructorWorkspace" data-realtime-url="{{ route('instructor.dashboard.realtime') }}"
        class="instructor-dashboard mx-auto w-full min-w-0 max-w-[1500px] space-y-3">
        @unless (Auth::user()->isAccountActive())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-red-800">
                <div class="flex items-center gap-3"><span
                        class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-bold">Inactive</span>
                    <p class="text-sm font-semibold">Attendance unavailable. Your account is inactive. Please contact the
                        administrator.</p>
                </div>
            </div>
        @endunless
        <div class="grid min-w-0 gap-3 xl:grid-cols-12">
            <div class="min-w-0 xl:col-span-12">
                <section class="dashboard-card rounded-xl border border-gray-200 bg-white px-4 py-3 sm:px-4">
                    <div class="mb-3">
                        <h2 class="text-lg font-semibold text-gray-900">Today's attendance</h2>
                        <p class="text-sm text-gray-500">Your four daily attendance periods</p>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-baseline gap-2">
                            <p data-instructor-progress-count class="text-sm font-bold text-gray-900">
                                {{ $today['completedPeriods'] }} of 4 completed</p>
                            <p data-instructor-progress-percent class="text-sm font-semibold text-emerald-700">
                                {{ $today['progressPercentage'] }}%</p>
                        </div>
                        <span data-instructor-status
                            class="inline-flex rounded-full px-3 py-1 text-xs font-bold {{ $statusClasses[$today['summaryStatus'] ?? $today['status']] ?? 'bg-amber-100 text-amber-700' }}">{{ $today['summaryStatus'] ?? $today['status'] }}</span>
                    </div>
                    <div class="mt-5 h-2.5 w-full overflow-hidden rounded-full bg-gray-200" role="progressbar"
                        aria-label="Today's attendance progress" aria-valuemin="0" aria-valuemax="100"
                        aria-valuenow="{{ $today['progressPercentage'] }}" data-instructor-progress-track>
                        <div data-instructor-progress-bar
                            class="h-full rounded-full bg-emerald-500 transition-[width] duration-300"
                            style="width: {{ $today['progressPercentage'] }}%"></div>
                    </div>
                    <div class="-mt-[17px] grid grid-cols-4">
                        @foreach ($progressPeriods as $key => $label)
                            @php($log = $today['events'][$key])
                            <div data-instructor-milestone="{{ $key }}"
                                class="flex min-w-0 flex-col items-center px-1 text-center">
                                <span data-instructor-milestone-icon
                                    class="relative grid h-6 w-6 shrink-0 place-items-center rounded-full border-2 border-white text-sm font-bold shadow-sm {{ $log ? 'bg-emerald-500 text-white' : 'bg-gray-200 text-gray-500' }}">{{ $log ? '✓' : '○' }}</span>
                                <span class="mt-2 min-w-0">
                                    <span data-instructor-milestone-label class="block text-xs font-semibold {{ $log ? 'text-emerald-700' : 'text-gray-600' }}">{{ $label }}</span>
                                    <span data-instructor-milestone-punctuality @class(['block text-xs font-medium', 'text-rose-600' => $log && in_array($log->punctuality_status, ['late', 'early_out']), 'text-emerald-700' => $log && ! in_array($log->punctuality_status, ['late', 'early_out']), 'text-gray-500' => ! $log])>{{ $log ? str($log->punctuality_status ?? 'on_time')->replace('_', ' ')->title() : 'Not yet recorded' }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-2 border-t border-gray-100 pt-2 text-xs font-medium text-slate-600">Next: <span
                            data-instructor-next
                            class="font-bold text-slate-900">{{ $today['nextPeriod'] ? str($today['nextPeriod'])->replace('_', ' ')->title() : 'Complete' }}</span>
                    </p>
                </section>

            </div>

            <div class="min-w-0 space-y-3 xl:col-span-8">
                @if($studentWarnings->isNotEmpty())
                    <section class="dashboard-card rounded-xl border border-amber-200 bg-amber-50/70 p-4 shadow-sm" aria-labelledby="student-risk-summary-title">
                        <div class="flex items-center justify-between gap-2">
                            <h2 id="student-risk-summary-title" class="text-xs font-extrabold uppercase text-amber-900">Attendance Warnings</h2>
                            <a href="{{ route('instructor.issues') }}" class="text-[11px] font-bold text-rose-700">Review</a>
                        </div>
                        <div class="mt-3 space-y-2">
                            @foreach($studentWarnings->take(3) as $warning)
                                <div class="rounded-lg bg-white px-3 py-2 ring-1 ring-rose-200">
                                    <p class="truncate text-xs font-bold text-slate-900">{{ $warning['student_name'] }}</p>
                                    <p class="text-[10px] text-slate-600">{{ $warning['subject_code'] }} · {{ $warning['absence_count'] }} absences</p>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
                <section class="dashboard-card rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-xs font-extrabold uppercase text-[#10294b]">My Attendance This Month</h2>
                            <p class="mt-1 text-[11px] text-slate-500">Daily completion of your four attendance periods
                            </p>
                        </div>
                        <a href="{{ route('instructor.summary') }}"
                            class="shrink-0 rounded-lg bg-slate-50 px-3 py-1.5 text-[10px] font-semibold">{{ now()->format('F Y') }}⌄</a>
                    </div>
                    @if ($chartHasData)
                        <div class="mt-4 w-full overflow-hidden rounded-xl border border-slate-100 bg-slate-50/40 px-1 pt-2 sm:px-2">
                            <svg class="block h-44 w-full sm:h-52"
                                viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" role="img"
                                aria-labelledby="instructor-attendance-chart-title instructor-attendance-chart-description">
                                <title id="instructor-attendance-chart-title">Daily attendance completion</title>
                                <desc id="instructor-attendance-chart-description">Attendance completion percentage for
                                    each working day this month. One hundred percent means all four attendance periods
                                    were recorded.</desc>
                                <defs>
                                    <linearGradient id="instructor-attendance-area" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#3b82f6" stop-opacity="0.35" />
                                        <stop offset="100%" stop-color="#3b82f6" stop-opacity="0.04" />
                                    </linearGradient>
                                </defs>
                                @foreach ([100, 75, 50, 25, 0] as $gridValue)
                                    @php($gridY = $chartTop + ((100 - $gridValue) * $chartPlotHeight) / 100)
                                    <line x1="{{ $chartLeft }}" y1="{{ $gridY }}"
                                        x2="{{ $chartWidth - $chartRight }}" y2="{{ $gridY }}" stroke="#e2e8f0"
                                        stroke-width="1" />
                                    <text x="{{ $chartLeft - 10 }}" y="{{ $gridY + 4 }}" text-anchor="end"
                                        fill="#64748b" font-size="11">{{ $gridValue }}%</text>
                                @endforeach
                                @foreach ($chartSegments as $index => $segment)
                                    <polygon points="{{ $chartAreas[$index] }}" fill="url(#instructor-attendance-area)" />
                                    <polyline points="{{ $segment }}" fill="none" stroke="#2563eb"
                                        stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                                @endforeach
                                @foreach ($chartPoints as $point)
                                    @if ($point['value'] !== null)
                                        <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4.5"
                                            fill="{{ $point['color'] }}" stroke="#ffffff" stroke-width="2">
                                            <title>{{ $point['day']['date']->format('M j') }}: {{ $point['value'] }}%
                                                ({{ $point['day']['status'] }})</title>
                                        </circle>
                                    @endif
                                    @if ($point['showLabel'])
                                        <text x="{{ $point['x'] }}" y="{{ $chartHeight - 9 }}" text-anchor="middle"
                                            fill="#64748b"
                                            font-size="10">{{ $point['day']['date']->format('M j') }}</text>
                                    @endif
                                @endforeach
                            </svg>
                        </div>
                    @else
                        <div
                            class="mt-4 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center text-xs text-slate-500">
                            No rated attendance days yet this month.</div>
                    @endif
                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-[10px] text-slate-600">
                        <span class="inline-flex items-center gap-1.5"><i
                                class="h-0.5 w-4 rounded-full bg-blue-600"></i>Daily completion</span>
                        @foreach (['#34d399' => 'Complete', '#fbbf24' => 'Incomplete', '#f43f5e' => 'Absent'] as $color => $label)
                            <span class="inline-flex items-center gap-1.5"><i class="h-2 w-2 rounded-full"
                                    style="background-color:{{ $color }}"></i>{{ $label }}</span>
                        @endforeach
                    </div>
                </section>
            </div>

            <aside class="min-w-0 space-y-3 xl:col-span-4">
                <section class="dashboard-card rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-700"><x-heroicon-o-calendar-days class="h-5 w-5" /></span>
                        <div><h2 class="text-sm font-bold text-[#10294b]">Next Teaching Schedule</h2><p class="mt-0.5 text-xs text-slate-500">Your upcoming class today</p></div>
                    </div>
                    @if ($nextSchedule)
                        <div class="mt-5 rounded-xl bg-slate-50 p-4">
                            <div class="flex items-center gap-2 text-blue-700"><x-heroicon-o-clock class="h-4 w-4 shrink-0" /><p class="text-sm font-bold">{{ Carbon\Carbon::parse($nextSchedule->start_time)->format('g:i A') }} &ndash; {{ Carbon\Carbon::parse($nextSchedule->end_time)->format('g:i A') }}</p></div>
                            <p class="mt-3 break-words text-base font-bold leading-snug text-[#10294b]">{{ $nextSchedule->subject?->subject_name ?? 'Unnamed subject' }}</p>
                            <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 border-t border-slate-200 pt-3 text-xs text-slate-600">
                                <span class="inline-flex items-center gap-1.5"><x-heroicon-o-user-group class="h-4 w-4 text-slate-400" />{{ $nextSchedule->section?->section_name ?? 'No section' }}</span>
                                <span class="inline-flex items-center gap-1.5"><x-heroicon-o-map-pin class="h-4 w-4 text-slate-400" />Room {{ $nextSchedule->room ?? 'TBD' }}</span>
                            </div>
                        </div>
                    @else
                        <p class="mt-5 rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-500">No upcoming teaching schedule today.</p>
                    @endif
                </section>
                {{--
                <section class="dashboard-card rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xs font-extrabold uppercase text-[#10294b]">Attendance Issues</h2><a
                            href="{{ route('instructor.issues') }}" class="text-[11px] font-bold text-blue-600">View
                            All</a>
                    </div>
                    <div class="mt-3 divide-y divide-slate-100">
                        @forelse($issues as $issue)
                            <div class="flex items-center gap-3 py-3"><span
                                    class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-orange-100 text-orange-600">△</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-bold">
                                        {{ $issue['status'] === 'Absent' ? 'Absent Day' : ($issue['isIncomplete'] ? 'Incomplete Attendance' : ($issue['late'] ? 'Late Time-In' : 'Early Time-Out')) }}
                                    </p>
                                    <p class="mt-1 text-[10px] text-slate-500">{{ $issue['date']->format('F j, Y') }}
                                        · {{ $issue['punctuality'] }}</p>
                                </div><span
                                    class="rounded-lg bg-orange-50 px-2.5 py-1 text-[9px] font-bold text-orange-600">Open</span>
                        </div>@empty<p class="rounded-lg bg-emerald-50 p-4 text-center text-xs text-emerald-700">No
                                attendance issues found.</p>
                        @endforelse
                    </div>
                </section>
                --}}
            </aside>
        </div>

    </div>
</x-instructor-layout>
