<x-app-layout>
    <div class="min-h-full bg-slate-50 pb-6" x-data="{
        data: {{ Illuminate\Support\Js::from($dashboardData) }},
        endpoint: {{ Illuminate\Support\Js::from(route('admin.dashboard.delta')) }},
        analyticsEndpoint: {{ Illuminate\Support\Js::from(route('admin.dashboard.analytics')) }},
        loading: false,
        analyticsLoading: false,
        analyticsEtag: null,
        online: true,
        cursor: {{ Illuminate\Support\Js::from($dashboardData['cursor']) }},
        timer: null,
        analyticsTimer: null,
        clockTimer: null,
        clockDate: '',
        dailyHover: null,
        weeklyHover: null,
        init() {
            this.tick();
            this.clockTimer = setInterval(() => this.tick(), 1000);
            this.timer = window.createIqamsPollingTask((signal) => this.refresh(signal), { interval: 4000 });
            this.analyticsTimer = window.createIqamsPollingTask((signal) => this.refreshAnalytics(signal));
            this.timer.start();
            this.analyticsTimer.start();
        },
        destroy() {
            this.timer?.stop();
            this.analyticsTimer?.stop();
            clearInterval(this.clockTimer);
        },
        tick() {
            const now = new Date();
            this.clockDate = now.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
            const localDate = now.toLocaleDateString('en-CA');
            if (this.data.date && this.data.date !== localDate) {
                window.location.reload();
            }
        },
        async refresh(signal) {
            if (this.loading || document.hidden) return;
            this.loading = true;
            try {
                const url = new URL(this.endpoint, window.location.href);
                url.searchParams.set('cursor', this.cursor);
                const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal });
                if (!response.ok) throw new Error('Unable to refresh');
                const fresh = await response.json();
                if (fresh.date !== this.data.date) {
                    this.data = { ...this.data, ...fresh, scans: [] };
                    this.cursor = fresh.cursor;
                    return;
                }
                const scansById = new Map(this.data.scans.map(scan => [scan.id, scan]));
                fresh.scans.forEach(scan => scansById.set(scan.id, scan));
                this.data = {
                    ...this.data,
                    ...fresh,
                    filters: this.data.filters,
                    scans: [...scansById.values()]
                        .sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp))
                        .slice(0, 250),
                };
                this.cursor = fresh.cursor;
                this.online = true;
            } catch (error) { this.online = false; } finally { this.loading = false; }
        },
        async refreshAnalytics(signal) {
            if (this.analyticsLoading || document.hidden) return;
            this.analyticsLoading = true;
            try {
                const headers = { Accept: 'application/json' };
                if (this.analyticsEtag) headers['If-None-Match'] = this.analyticsEtag;
                const response = await fetch(this.analyticsEndpoint, { headers, credentials: 'same-origin', cache: 'no-cache', signal });
                if (response.status === 304) {
                    this.online = true;
                    return;
                }
                if (!response.ok) throw new Error('Unable to refresh analytics');
                const fresh = await response.json();
                this.analyticsEtag = response.headers.get('ETag') || this.analyticsEtag;
                this.data = {
                    ...this.data,
                    generated_at: fresh.generated_at ?? this.data.generated_at,
                    stats: fresh.stats,
                    overview: fresh.overview,
                    charts: fresh.charts,
                };
                this.online = true;
            } catch (error) {
                // Keep the last valid analytics snapshot visible during a transient failure.
            } finally { this.analyticsLoading = false; }
        },
        statusColor(status) { return { present: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', late: 'bg-amber-50 text-amber-700 ring-amber-600/20', incomplete: 'bg-red-50 text-red-700 ring-red-600/20', absent: 'bg-red-50 text-red-700 ring-red-600/20', excused: 'bg-slate-100 text-slate-600 ring-slate-500/20' } [status] || 'bg-slate-100 text-slate-600 ring-slate-500/20'; },
        actionColor(action) { return action === 'time_out' ? 'bg-orange-50 text-orange-700 ring-orange-600/20' : 'bg-blue-50 text-blue-700 ring-blue-600/20'; },
        max(items) { return Math.max(1, ...items.map(item => item.value)); },
        points(items) { const max = this.max(items); const width = 560; const height = 120; return items.map((item, index) => `${items.length === 1 ? 0 : index * width / (items.length - 1)},${height - (item.value / max * 105)}`).join(' '); }
    }">
        <header class="sticky top-0 z-30 h-20 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur">
            <div class="mx-auto flex h-full max-w-none items-center px-4 py-2 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between lg:gap-10">
                    <div class="min-w-0 flex-1">
                        {{-- <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-600">IQAMS Control Center</p> --}}
                        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 lg:text-[32px] lg:leading-tight">Attendance Dashboard</h1>
                        <p class="mt-1 text-sm text-slate-500">Live college-wide attendance monitoring and analytics</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3 lg:absolute lg:right-8">
                        <x-leave-notification-bell />
                        <div
                            class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-right shadow-sm">
                            <p class="text-sm font-semibold text-slate-800" x-text="clockDate"></p>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="mx-auto max-w-[1600px] px-4 sm:px-6 lg:px-8">
            <div class="mb-5 flex min-h-8 items-center justify-between gap-4 border-b border-slate-200/80 pb-3">
                <div class="flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium"
                    :class="online ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600'">
                    <span class="relative flex h-2.5 w-2.5"><span x-show="online"
                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span
                            class="relative inline-flex h-2.5 w-2.5 rounded-full"
                            :class="online ? 'bg-emerald-500' : 'bg-red-500'"></span></span>
                    <span x-text="online ? 'Live monitoring active' : 'Connection interrupted — retrying'"></span>
                </div>
                <span class="text-xs text-slate-400"
                    x-text="loading ? 'Updating…' : 'Auto-refreshes every 4 seconds'"></span>
            </div>

            {{-- Summary cards --}}
            <section class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-9">
                @php
                    $cards = [
                        ['total_scanned', 'Total scanned', 'bg-blue-50 text-blue-600', 'M7 7h10M7 12h10M7 17h6'],
                        ['students', 'Students', 'bg-sky-50 text-sky-600', 'M12 14 3 9l9-5 9 5-9 5Zm0 0v6'],
                        [
                            'instructors',
                            'Teaching',
                            'bg-violet-50 text-violet-600',
                            'M4 19.5V6a2 2 0 0 1 2-2h12v15.5M6 16h12',
                        ],
                        [
                            'staff',
                            'Non-teaching',
                            'bg-amber-50 text-amber-600',
                            'M9 6h6m-8 4h10M5 20h14V6a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v14Z',
                        ],
                        ['present', 'Currently present', 'bg-emerald-50 text-emerald-600', 'm5 12 4 4L19 6'],
                        ['absent', 'Absent users', 'bg-rose-50 text-rose-600', 'M6 6l12 12M18 6 6 18'],
                        [
                            'late',
                            'Late users',
                            'bg-yellow-50 text-yellow-600',
                            'M12 7v5l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
                        ],
                        [
                            'missing_timeout',
                            'Missing time-out',
                            'bg-orange-50 text-orange-600',
                            'M12 8v5m0 3h.01M10.3 3.7 2 18h20L13.7 3.7a2 2 0 0 0-3.4 0Z',
                        ],
                        ['incomplete', 'Incomplete', 'bg-red-50 text-red-600', 'M6 18 18 6M6 6l12 12'],
                    ];
                @endphp
                @foreach ($cards as [$key, $label, $colorClasses, $path])
                    <article
                        class="flex min-h-[118px] flex-col rounded-2xl border border-slate-200 bg-white p-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:p-3.5">
                        <div class="mb-2 flex items-start justify-between">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl {{ $colorClasses }}"><svg class="h-5 w-5" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="{{ $path }}" />
                                </svg></span>
                            @if ($key === 'total_scanned')
                                <span
                                    class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">TODAY</span>
                            @endif
                        </div>
                        <p class="text-[28px] font-bold leading-none tabular-nums text-slate-900"
                            x-text="data.stats.{{ $key }}"></p>
                        <p class="mt-2 text-sm font-medium leading-tight text-slate-500">{{ $label }}</p>
                    </article>
                @endforeach
            </section>

            <section class="mt-6 grid grid-cols-1 gap-5">
                {{-- Live activity --}}
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <div>
                            <h2 class="font-semibold text-slate-900">Live QR Scan Activity</h2>
                            <p class="mt-0.5 text-xs text-slate-500">Newest successful scan appears first</p>
                        </div>
                        <span
                            class="flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700"><span
                                class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>LIVE</span>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <template x-for="scan in data.scans.slice(0, 8)" :key="scan.id">
                            <div
                                class="grid gap-4 px-5 py-4 transition hover:bg-slate-50 md:grid-cols-[minmax(280px,1.5fr)_minmax(180px,1fr)_minmax(150px,.8fr)_auto] md:items-center">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div
                                        class="h-10 w-10 shrink-0 overflow-hidden rounded-xl bg-gradient-to-br from-blue-100 to-indigo-100">
                                        <img x-show="scan.avatar" :src="scan.avatar"
                                            class="h-full w-full object-cover" alt="">
                                        <span x-show="!scan.avatar"
                                            class="flex h-full w-full items-center justify-center text-sm font-bold text-blue-700"
                                            x-text="scan.initials"></span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900" x-text="scan.name"></p>
                                        <p class="text-xs text-slate-500"><span x-text="scan.identifier"></span> · <span
                                                x-text="scan.role"></span></p>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-700" x-text="scan.group"></p>
                                    <p class="truncate text-xs text-slate-500"
                                        x-text="scan.subject || 'General attendance'"></p>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold tabular-nums text-slate-800" x-text="scan.time"></p>
                                    <p class="text-xs text-slate-400" x-text="scan.location || 'Main scanner'"></p>
                                </div>
                                <div class="flex gap-2 md:justify-end"><span
                                        class="rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset"
                                        :class="actionColor(scan.attendance_type)"
                                        x-text="scan.attendance_label"></span><span
                                        class="rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset"
                                        :class="statusColor(scan.status)" x-text="scan.status_label"></span></div>
                            </div>
                        </template>
                        <div x-show="!data.scans.length" class="px-6 py-16 text-center text-sm text-slate-400">Waiting
                            for today's first QR scan…</div>
                    </div>
                </div>

                <div class="grid gap-5 lg:grid-cols-2">
                {{-- Today overview --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="font-semibold text-slate-900">Today's Attendance Overview</h2>
                    <p class="mt-1 text-xs text-slate-500">Unique people recorded by user type</p>
                    <div class="mt-5 space-y-6">
                        <template x-for="row in data.overview" :key="row.label">
                            <div>
                                <div class="mb-2 flex items-end justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-800" x-text="row.label"></p>
                                        <p class="mt-0.5 text-xs text-slate-500"><span x-text="row.scanned"></span> of
                                            <span x-text="row.total"></span> scanned</p>
                                    </div><span class="text-lg font-bold text-blue-700"
                                        x-text="row.percentage + '%' "></span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-gradient-to-r from-blue-600 to-cyan-500 transition-all duration-500"
                                        :style="`width:${row.percentage}%`"></div>
                                </div>
                                <div class="mt-2 flex justify-between text-xs"><span class="text-emerald-700"><span
                                            x-text="row.primary_label"></span>: <b
                                            x-text="row.primary"></b></span><span class="text-amber-700"><span
                                            x-text="row.secondary_label"></span>: <b
                                            x-text="row.secondary"></b></span></div>
                            </div>
                        </template>
                    </div>
                </div>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-900">By Department</h3>
                    <p class="text-xs text-slate-500">Top teaching departments</p>
                    <div class="mt-4 space-y-2"><template x-for="item in data.charts.departments">
                            <div class="flex items-center gap-2"><span class="w-20 truncate text-xs text-slate-600"
                                    x-text="item.label"></span>
                                <div class="h-2 flex-1 rounded bg-slate-100">
                                    <div class="h-2 rounded bg-indigo-500"
                                        :style="`width:${item.value / max(data.charts.departments) * 100}%`"></div>
                                </div><b class="w-6 text-right text-xs" x-text="item.value"></b>
                            </div>
                        </template>
                        <p x-show="!data.charts.departments.length" class="py-8 text-center text-xs text-slate-400">No
                            data today</p>
                    </div>
                </article>
                </div>
            </section>

            {{-- Analytics --}}
            <section class="mt-6 grid gap-4 lg:grid-cols-2">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">Daily Scan Trend</h3>
                            <p class="text-xs text-slate-500">Scans by hour today</p>
                        </div><span class="text-xs font-medium text-blue-600">Live</span>
                    </div>
                    <div class="relative mt-5 h-40 w-full"><svg class="h-full w-full overflow-visible" viewBox="0 0 560 140"
                            preserveAspectRatio="none">
                            <defs>
                                <linearGradient id="trendFill" x1="0" y1="0" x2="0"
                                    y2="1">
                                    <stop offset="0" stop-color="#3b82f6" stop-opacity=".28" />
                                    <stop offset="1" stop-color="#3b82f6" stop-opacity="0" />
                                </linearGradient>
                            </defs>
                            <line x1="0" y1="120" x2="560" y2="120" stroke="#e2e8f0" />
                            <polygon :points="`0,120 ${points(data.charts.hourly)} 560,120`" fill="url(#trendFill)" />
                            <polyline :points="points(data.charts.hourly)" fill="none" stroke="#2563eb"
                                stroke-width="3" vector-effect="non-scaling-stroke" stroke-linecap="round"
                                stroke-linejoin="round" />
                            <template x-for="(item, index) in data.charts.hourly" :key="item.label">
                                <g @mouseenter="dailyHover = index" @mouseleave="dailyHover = null"
                                    @focus="dailyHover = index" @blur="dailyHover = null" tabindex="0"
                                    :aria-label="`${item.label}: ${item.value} scans`">
                                    <rect :x="data.charts.hourly.length === 1 ? 0 : Math.max(0, index * 560 / (data.charts.hourly.length - 1) - 560 / (2 * (data.charts.hourly.length - 1)))"
                                        y="0" :width="data.charts.hourly.length === 1 ? 560 : (index === 0 || index === data.charts.hourly.length - 1 ? 280 / (data.charts.hourly.length - 1) : 560 / (data.charts.hourly.length - 1))"
                                        height="140" fill="transparent" />
                                    <circle x-show="dailyHover === index" :cx="data.charts.hourly.length === 1 ? 0 : index * 560 / (data.charts.hourly.length - 1)"
                                        :cy="120 - item.value / max(data.charts.hourly) * 105" r="4" fill="#2563eb" vector-effect="non-scaling-stroke" />
                                </g>
                            </template>
                        </svg>
                        <div x-cloak x-show="dailyHover !== null" class="pointer-events-none absolute top-0 -translate-x-1/2 rounded-lg bg-slate-800 px-2 py-1 text-xs font-medium text-white shadow"
                            :style="`left: ${data.charts.hourly.length <= 1 ? 0 : Math.max(8, Math.min(92, dailyHover * 100 / (data.charts.hourly.length - 1)))}%`"
                            x-text="dailyHover === null ? '' : `${data.charts.hourly[dailyHover].label}: ${data.charts.hourly[dailyHover].value} scans`"></div>
                    </div>
                    <div class="flex justify-between text-[10px] text-slate-400"><template
                            x-for="(item, i) in data.charts.hourly"><span x-show="i % 2 === 0"
                                x-text="item.label"></span></template></div>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-900">Weekly Attendance Trend</h3>
                    <p class="text-xs text-slate-500">Scan activity for the current week</p>
                    <div class="relative mt-5 h-40 w-full">
                        <svg class="h-full w-full overflow-visible" viewBox="0 0 560 140" preserveAspectRatio="none" aria-hidden="true">
                            <defs>
                                <linearGradient id="weeklyTrendFill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0" stop-color="#3b82f6" stop-opacity=".28" />
                                    <stop offset="1" stop-color="#3b82f6" stop-opacity="0" />
                                </linearGradient>
                            </defs>
                            <line x1="0" y1="120" x2="560" y2="120" stroke="#e2e8f0" />
                            <polygon :points="`0,120 ${points(data.charts.weekly)} 560,120`" fill="url(#weeklyTrendFill)" />
                            <polyline :points="points(data.charts.weekly)" fill="none" stroke="#2563eb"
                                stroke-width="3" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" />
                            <template x-for="(item, index) in data.charts.weekly" :key="item.label">
                                <g @mouseenter="weeklyHover = index" @mouseleave="weeklyHover = null"
                                    @focus="weeklyHover = index" @blur="weeklyHover = null" tabindex="0"
                                    :aria-label="`${item.label}: ${item.value} scans`">
                                    <rect :x="data.charts.weekly.length === 1 ? 0 : Math.max(0, index * 560 / (data.charts.weekly.length - 1) - 560 / (2 * (data.charts.weekly.length - 1)))"
                                        y="0" :width="data.charts.weekly.length === 1 ? 560 : (index === 0 || index === data.charts.weekly.length - 1 ? 280 / (data.charts.weekly.length - 1) : 560 / (data.charts.weekly.length - 1))"
                                        height="140" fill="transparent" />
                                    <circle x-show="weeklyHover === index" :cx="data.charts.weekly.length === 1 ? 0 : index * 560 / (data.charts.weekly.length - 1)"
                                        :cy="120 - item.value / max(data.charts.weekly) * 105" r="4" fill="#2563eb" vector-effect="non-scaling-stroke" />
                                </g>
                            </template>
                        </svg>
                        <div x-cloak x-show="weeklyHover !== null" class="pointer-events-none absolute top-0 -translate-x-1/2 rounded-lg bg-slate-800 px-2 py-1 text-xs font-medium text-white shadow"
                            :style="`left: ${data.charts.weekly.length <= 1 ? 0 : Math.max(8, Math.min(92, weeklyHover * 100 / (data.charts.weekly.length - 1)))}%`"
                            x-text="weeklyHover === null ? '' : `${data.charts.weekly[weeklyHover].label}: ${data.charts.weekly[weeklyHover].value} scans`"></div>
                    </div>
                    <div class="flex justify-between gap-2 text-center text-[11px] text-slate-500">
                        <template x-for="item in data.charts.weekly" :key="item.label">
                            <span x-text="item.label"></span>
                        </template>
                    </div>
                </article>
            </section>

        </div>

    </div>
</x-app-layout>
