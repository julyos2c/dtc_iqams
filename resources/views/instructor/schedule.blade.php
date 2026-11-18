<x-instructor-layout title="My Teaching Schedule">
    <div x-data="classAttendanceBrowser()" data-today="{{ now()->toDateString() }}"
        data-attendance-endpoint="{{ url('/instructor/schedule/__SCHEDULE__/attendance') }}"
        data-download-endpoint="{{ url('/instructor/schedule/__SCHEDULE__/attendance/download') }}"
        class="mx-auto max-w-[1500px] space-y-5">
        <div>
            <p class="text-sm text-slate-600">Manage students for each subject or select a class, weekday, and actual date to review attendance.
            </p>
        </div>
        @if(session('success')) <p class="text-green-700">{{ session('success') }}</p> @endif
        @if($errors->any()) <p class="text-red-700">{{ $errors->first() }}</p> @endif
        @if($scheduleGroups->isNotEmpty())
        <section x-data="{ open: @js($errors->any()), schedule: @js(old('cancellation_schedule', '')), date: @js(old('occurrence_date', '')), day: '', submitting: false,
            get dateMatches() { if (!this.date || !this.day) return true; const d = new Date(this.date + 'T00:00:00Z'); return !Number.isNaN(d.getTime()) && ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'][d.getUTCDay()] === this.day; } }"
            x-init="$nextTick(() => { day = $refs.cancelClass?.selectedOptions[0]?.dataset.day || '' })"
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4 p-5 sm:p-6">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-heroicon-o-calendar-days class="h-5 w-5" /></span>
                    <div><h2 class="text-base font-bold text-[#10294b]">Class cancellation</h2><p class="mt-1 text-sm text-slate-500">Request a cancellation for one scheduled class. An admin will review it.</p></div>
                </div>
                <button type="button" @click="open = !open; if (open) $nextTick(() => $refs.cancelClass.focus())" :aria-expanded="open.toString()" aria-controls="cancellation-form-panel"
                    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">
                    <span x-text="open ? 'Close form' : 'Request Cancellation'"></span>
                </button>
            </div>
            <div id="cancellation-form-panel" x-show="open" x-cloak class="border-t border-slate-100 bg-slate-50/60 p-5 sm:p-6">
                @foreach($leaveClasses as $entry)
                    <div class="mb-4 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                        <p class="font-semibold">Approved leave: {{ $entry['leave']->start_date->format('M d, Y') }} – {{ $entry['leave']->end_date->format('M d, Y') }}</p>
                        <p class="mt-1 text-blue-800">Affected classes: {{ $entry['classes']->implode('; ') ?: 'None' }}.</p>
                    </div>
                @endforeach
                <form method="POST" :action="@js(url('/instructor/schedule/__SCHEDULE__/cancellation')).replace('__SCHEDULE__', encodeURIComponent(schedule))" @submit="if (!dateMatches || submitting) { $event.preventDefault(); } else { submitting = true; }" class="space-y-4">
                    @csrf
                    <input type="hidden" name="cancellation_schedule" :value="schedule">
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                        <div>
                            <label for="cancellation-class" class="mb-1.5 block text-sm font-semibold text-slate-700">Class</label>
                            <select id="cancellation-class" x-ref="cancelClass" x-model="schedule" @change="day = $event.target.selectedOptions[0]?.dataset.day || ''" required class="block min-h-11 w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                <option value="" disabled>Select a scheduled class</option>
                                @foreach($scheduleGroups as $group) @foreach($group['days'] as $day)
                                    <option value="{{ $day['schedule_id'] }}" data-day="{{ $day['name'] }}">{{ $group['subject_code'] }} · {{ $group['section'] }} · {{ $day['label'] }} · {{ $group['start_time'] }}–{{ $group['end_time'] }}</option>
                                @endforeach @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="cancellation-date" class="mb-1.5 block text-sm font-semibold text-slate-700">Class date</label>
                            <input id="cancellation-date" type="date" name="occurrence_date" x-model="date" :aria-invalid="(!dateMatches).toString()" aria-describedby="cancellation-date-help" required class="block min-h-11 w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            <p id="cancellation-date-help" class="mt-1.5 text-xs" :class="dateMatches ? 'text-slate-500' : 'font-semibold text-red-700'" x-text="dateMatches ? 'Choose a date that matches the class day.' : 'Choose a date that matches the selected class day.'"></p>
                        </div>
                    </div>
                    <div>
                        <label for="cancellation-reason" class="mb-1.5 block text-sm font-semibold text-slate-700">Reason</label>
                        <textarea id="cancellation-reason" name="reason" rows="3" maxlength="2000" required placeholder="Briefly explain why this class needs to be cancelled" class="block w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-blue-600 focus:ring-blue-600">{{ old('reason') }}</textarea>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4">
                        <p class="text-xs leading-5 text-slate-500">The request applies only to the selected class and date.</p>
                        <button type="submit" :disabled="!schedule || !date || !dateMatches || submitting" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-blue-700 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700" x-text="submitting ? 'Submitting…' : 'Submit request'"></button>
                    </div>
                </form>
            </div>
            @if($cancellations->isNotEmpty())
                <div class="border-t border-slate-100 px-5 py-4 sm:px-6">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Recent requests</h3>
                    <ul class="mt-2 divide-y divide-slate-100">
                        @foreach($cancellations as $item)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm">
                                <div><span class="font-semibold text-slate-800">{{ $item->schedule->subject?->subject_code ?? 'Class' }}</span><span class="ml-2 text-slate-500">{{ $item->occurrence_date->format('M d, Y') }}</span>@if($item->review_notes)<p class="mt-1 text-xs text-slate-500">Review note: {{ $item->review_notes }}</p>@endif</div>
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ ['pending' => 'bg-amber-50 text-amber-700', 'approved' => 'bg-emerald-50 text-emerald-700', 'rejected' => 'bg-rose-50 text-rose-700'][$item->status] ?? 'bg-slate-100 text-slate-600' }}">{{ ucfirst($item->status) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>
        @endif

        @if ($scheduleGroups->isEmpty())
            <section class="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm">
                <p class="font-semibold text-slate-700">No teaching schedules assigned.</p>
                <p class="mt-1 text-sm text-slate-500">Assigned classes will appear here.</p>
            </section>
        @else
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($scheduleGroups as $group)
                    <article
                        :class="selectedGroup?.key === @js($group['key']) ? 'border-blue-500 ring-2 ring-blue-100' :
                            'border-slate-200 hover:border-blue-300'"
                        class="flex flex-col rounded-xl border bg-white p-5 text-left shadow-sm transition">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-lg font-extrabold text-[#10294b]">{{ $group['subject_code'] }}</p>
                                <p class="mt-1 text-sm font-semibold text-slate-700">{{ $group['subject_name'] }}</p>
                            </div>
                            <span
                                class="shrink-0 rounded-md bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">{{ $group['days_label'] }}</span>
                        </div>
                        <div class="mt-4 space-y-1.5 border-t border-slate-100 pt-3 text-sm text-slate-600">
                            <p class="font-semibold text-slate-800">{{ $group['start_time'] }} -
                                {{ $group['end_time'] }}</p>
                            <p>{{ $group['section'] }} <span class="mx-2 text-slate-300">•</span> Room
                                {{ $group['room'] }}</p>
                            <p class="text-xs text-slate-600">
                                {{ collect($group['days'])->pluck('label')->implode(' · ') }}</p>
                        </div>
                        <div class="mt-auto flex flex-wrap gap-2 pt-4">
                        @if($group['roster_url'])
                            <a href="{{ $group['roster_url'] }}" class="inline-flex min-h-10 flex-[1_1_140px] items-center justify-center whitespace-nowrap rounded-lg border border-blue-600 px-3 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">Manage Students</a>
                        @else
                            <p class="text-xs text-slate-500">Ask the admin to complete this subject’s schedule setup before adding students.</p>
                        @endif
                        <button type="button" @click="openGroup(@js($group))"
                            :class="selectedGroup?.key === @js($group['key']) ? 'bg-blue-700' : 'bg-blue-600'"
                            class="inline-flex min-h-10 flex-[1_1_140px] items-center justify-center gap-1.5 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                            <x-heroicon-o-user-group class="h-4 w-4 shrink-0" />
                            <span
                                x-text="selectedGroup?.key === @js($group['key']) ? 'Viewing Attendance' : 'View Attendance'">View Attendance</span>
                        </button>
                        </div>
                    </article>
                @endforeach
            </div>

            <section x-show="selectedGroup" x-cloak class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 p-5">
                    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                        <div>
                            <p class="text-xs font-extrabold uppercase tracking-wider text-blue-600">Selected class</p>
                            <h3 class="mt-1 text-xl font-extrabold text-[#10294b]"
                                x-text="selectedGroup ? selectedGroup.subject_code + ' · ' + selectedGroup.subject_name : ''">
                            </h3>
                            <p class="mt-1 text-sm text-slate-500"
                                x-text="selectedGroup ? selectedGroup.section + ' · Room ' + selectedGroup.room : ''">
                            </p>
                        </div>
                        <p class="text-sm font-semibold text-slate-700"
                            x-text="selectedGroup ? selectedGroup.start_time + ' - ' + selectedGroup.end_time : ''"></p>
                    </div>

                    <div class="mt-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Class day</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <template x-for="day in selectedGroup?.days || []" :key="day.schedule_id"><button
                                    type="button" @click="selectDay(day)"
                                    :class="selectedDay?.schedule_id === day.schedule_id ?
                                        'border-blue-600 bg-blue-600 text-white' :
                                        'border-slate-200 bg-white text-slate-600 hover:border-blue-300'"
                                    class="min-h-10 rounded-lg border px-3 py-2 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                                    x-text="day.label"></button></template>
                        </div>
                    </div>

                    <div class="mt-4" x-show="selectedDay">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Actual date · <span
                                x-text="monthLabel"></span></p>
                        <div class="mt-2 flex gap-2 overflow-x-auto pb-1">
                            <template x-for="date in availableDates" :key="date.value"><button type="button"
                                    @click="selectDate(date.value)"
                                    :class="selectedDate === date.value ? 'border-[#10294b] bg-[#10294b] text-white' :
                                        'border-slate-200 bg-slate-50 text-slate-700 hover:border-blue-300'"
                                    class="min-w-[60px] rounded-lg border px-2 py-1.5 text-center focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"><span
                                        class="block text-[10px] font-bold uppercase"
                                        x-text="date.shortDay"></span><span
                                        class="mt-0.5 block text-base font-extrabold"
                                        x-text="date.dayNumber"></span></button></template>
                        </div>
                    </div>
                </div>

                <div class="p-5">
                    <div x-show="loading" class="grid min-h-52 place-items-center">
                        <div class="text-center">
                            <div
                                class="mx-auto h-9 w-9 animate-spin rounded-full border-4 border-slate-200 border-t-blue-600">
                            </div>
                            <p class="mt-3 text-sm font-semibold text-slate-500">Loading class attendance…</p>
                        </div>
                    </div>
                    <div x-show="error && !loading"
                        class="rounded-xl border border-red-200 bg-red-50 p-5 text-sm font-semibold text-red-700"
                        x-text="error"></div>

                    <div x-show="attendance && !loading && !error" x-cloak>
                        <p x-show="attendance?.class.cancellation_status" class="mb-4 rounded bg-amber-50 p-3 font-semibold" x-text="attendance?.class.cancellation_status === 'approved' ? 'Class Cancelled' : 'Cancellation request pending'"></p>
                        <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                            <div>
                                <p class="text-xs font-extrabold uppercase tracking-wider text-blue-600">Student
                                    attendance</p>
                                <h3 class="mt-1 text-2xl font-extrabold text-slate-950"
                                    x-text="attendance?.class.date_label"></h3>
                                <p class="mt-1 text-sm text-slate-500"
                                    x-text="attendance ? attendance.class.time_label + ' · Room ' + attendance.class.room : ''">
                                </p>
                            </div>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <div class="rounded-xl bg-emerald-50 px-4 py-3 text-center">
                                    <p class="text-[10px] font-bold uppercase text-emerald-700">Present</p>
                                    <p class="mt-1 text-2xl font-extrabold text-emerald-700"
                                        x-text="attendance?.summary.present"></p>
                                </div>
                                <div class="rounded-xl bg-rose-50 px-4 py-3 text-center">
                                    <p class="text-[10px] font-bold uppercase text-rose-700">Absent</p>
                                    <p class="mt-1 text-2xl font-extrabold text-rose-700"
                                        x-text="attendance?.summary.absent"></p>
                                </div>
                                <div class="rounded-xl bg-violet-50 px-4 py-3 text-center">
                                    <p class="text-[10px] font-bold uppercase text-violet-700">Excused</p>
                                    <p class="mt-1 text-2xl font-extrabold text-violet-700"
                                        x-text="attendance?.summary.excused"></p>
                                </div>
                                <div class="rounded-xl bg-amber-50 px-4 py-3 text-center">
                                    <p class="text-[10px] font-bold uppercase text-amber-700">Pending</p>
                                    <p class="mt-1 text-2xl font-extrabold text-amber-700"
                                        x-text="attendance?.summary.pending"></p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 flex justify-end">
                            <a :href="downloadUrl" x-show="downloadUrl" download
                                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700">
                                <x-heroicon-o-arrow-down-tray class="h-5 w-5" />
                                Download Excel Report
                            </a>
                        </div>

                        <div x-show="attendance && !attendance.has_students"
                            class="mt-6 rounded-xl bg-slate-50 p-8 text-center">
                            <p class="font-semibold text-slate-700">No students are enrolled in this section.</p>
                        </div>
                        <div x-show="attendance?.has_students && !attendance?.has_records && !attendance?.class.cancellation_status"
                            class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            No attendance recorded for this class yet. Students remain Pending until the attendance
                            cutoff, then appear as Absent.</div>

                        <div x-show="attendance?.has_students"
                            class="mt-6 overflow-x-auto rounded-xl border border-slate-200">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th class="px-5 py-3">Student ID</th>
                                        <th class="px-5 py-3">Student Name</th>
                                        <th class="px-5 py-3">Time In</th>
                                        <th class="px-5 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100"><template
                                        x-for="student in attendance?.students || []" :key="student.student_no">
                                        <tr>
                                            <td class="whitespace-nowrap px-5 py-4 font-mono text-xs text-slate-500"
                                                x-text="student.student_no"></td>
                                            <td class="px-5 py-4 font-semibold text-slate-900" x-text="student.name">
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4 tabular-nums text-slate-600"
                                                x-text="student.status === 'absent' ? 'No scan recorded' : (student.time_in || '—')">
                                            </td>
                                            <td class="px-5 py-4"><span :class="statusClass(student.status)"
                                                    class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold uppercase"
                                                    x-text="student.status"></span></td>
                                        </tr>
                                    </template></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        @endif
    </div>

</x-instructor-layout>
