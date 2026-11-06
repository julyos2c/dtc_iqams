<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Attendance Logs
            </h2>
            <p class="mt-1 text-sm text-gray-500">Review, filter, and manage attendance records.</p>
        </div>
    </x-slot>

    <div class="py-8" x-data="{
        showCreateModal: {{ $errors->any() ? 'true' : 'false' }},
        createUserId: @js((string) old('user_id', '')),
        createScheduleId: @js((string) old('schedule_id', '')),
        editModal: { show: false, id: null, user_id: '', schedule_id: '', attendance_type: '', scan_time: '', status_override: '', scanner_location: '', remarks: '' },
        deleteModal: { show: false, id: null, name: '' }
    }" @repeatable-form-reset.window="createUserId = ''; createScheduleId = ''">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Filters --}}
            <form method="GET" action="{{ route('attendance-logs.index') }}" x-data="attendanceLogSearch" @submit.prevent="submit()"
                class="mb-4 grid grid-cols-1 items-start gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:grid-cols-2 lg:grid-cols-[minmax(0,1.8fr)_minmax(140px,.55fr)_minmax(180px,.75fr)]">
                <div class="min-w-0">
                    <label for="attendance-person-search" class="mb-1.5 block text-xs font-semibold text-gray-600">Person</label>
                    <div class="relative">
                        <input id="attendance-person-search" type="text" name="search" value="{{ request('search') }}" placeholder="Search people..." autocomplete="off" x-model="query" @input="schedule()"
                            class="h-10 w-full rounded-lg border-gray-300 pr-10 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <button type="button" x-cloak x-show="query.length" @click="query = ''; submit(); $el.previousElementSibling.focus()" aria-label="Clear person search" class="absolute right-2 top-2 rounded p-1 text-gray-400 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"><x-heroicon-o-x-mark class="h-4 w-4" aria-hidden="true" /></button>
                    </div>
                    <p x-cloak x-show="error" x-text="error" role="alert" class="mt-1 text-xs text-red-700"></p>
                </div>

                <div class="min-w-0">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-600">Date</label>
                    <input type="date" name="date" value="{{ request('date') }}" placeholder="dd/mm/yyyy"
                        class="h-10 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div class="min-w-0">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-600">Status</label>
                    <select name="status"
                        class="h-10 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All statuses</option>
                        @foreach (['present', 'late', 'absent', 'excused'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-3 md:col-span-2 lg:col-start-1">
                    <button type="submit"
                        class="h-10 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700">
                        Apply filters
                    </button>
                    <a href="{{ route('attendance-logs.index') }}" @click.prevent="clear()" class="rounded-lg px-2 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 hover:text-gray-700">
                        Clear
                    </a>
                </div>
            </form>

            <div data-repeatable-list class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3.5 sm:px-6">
                    <div class="flex items-baseline gap-2">
                        <span data-attendance-count class="text-sm font-semibold text-gray-800">{{ $logs->total() }}</span>
                        <span class="text-sm text-gray-500">attendance records</span>
                    </div>
                    <button @click="showCreateModal = true"
                        class="h-10 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700">
                        + Add Log
                    </button>
                </div>

                <div class="overflow-x-auto" data-attendance-table>
                <table class="min-w-[900px] w-full text-left text-sm">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                        <tr>
                            <th class="w-[24%] px-5 py-3">Person</th>
                            <th class="w-[28%] px-3 py-3">Schedule</th>
                            <th class="w-[12%] px-3 py-3">Type</th>
                            <th class="w-[19%] whitespace-nowrap px-3 py-3">Scan Time</th>
                            <th class="w-[10%] px-3 py-3">Status</th>
                            <th class="w-[7%] px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($logs as $log)
                            <tr class="hover:bg-gray-50/70">
                                <td class="px-5 py-3.5 font-medium text-gray-800">
                                    {{ $log->user?->nonTeachingStaff?->fullName() ?? ($log->user?->name ?? '—') }}</td>
                                <td class="px-3 py-3.5 text-gray-600">
                                    {{ $log->schoolEvent?->title ?? ($log->schedule?->subject?->subject_code ?? '—') }}
                                    ({{ $log->schedule->section->section_name ?? '—' }})
                                </td>
                                <td class="px-3 py-3.5 text-gray-600">
                                    {{ $log->attendance_type === 'time_in' ? 'Time In' : 'Time Out' }}</td>
                                <td class="whitespace-nowrap px-3 py-3.5 text-gray-600">
                                    {{ \Illuminate\Support\Carbon::parse($log->scan_time)->format('M d, Y g:i A') }}
                                </td>
                                <td class="px-3 py-3.5">
                                    <span @class([
                                        'inline-flex min-h-7 items-center justify-center whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-medium',
                                        'bg-green-50 text-green-700' => $log->status === 'present',
                                        'bg-yellow-50 text-yellow-700' => $log->status === 'late',
                                        'bg-red-50 text-red-700' => $log->status === 'absent',
                                        'bg-blue-50 text-blue-700' => $log->status === 'excused',
                                    ])>
                                        {{ ucfirst($log->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <x-record-action-menu>
                                        <button type="button"
                                            @click="editModal = {{ Illuminate\Support\Js::from([
                                                'show' => true,
                                                'id' => $log->id,
                                                'user_id' => (string) $log->user_id,
                                                'schedule_id' => (string) $log->schedule_id,
                                                'attendance_type' => $log->attendance_type,
                                                'scan_time' => \Illuminate\Support\Carbon::parse($log->scan_time)->format('Y-m-d\TH:i'),
                                                'status_override' => $log->status,
                                                'scanner_location' => $log->scanner_location,
                                                'remarks' => $log->remarks,
                                            ]) }}; $nextTick(() => $dispatch('lookup-refresh', { key: 'attendance-edit', values: {{ Illuminate\Support\Js::from(['user_id' => (string) $log->user_id, 'schedule_id' => (string) $log->schedule_id]) }} }))"
                                            class="text-indigo-600 hover:text-indigo-800">Edit</button>

                                        <button type="button"
                                            @click="deleteModal = { show: true, id: {{ $log->id }}, name: {{ Illuminate\Support\Js::from(($log->user?->nonTeachingStaff?->fullName() ?? ($log->user?->name ?? 'Log')) . ' - ' . \Illuminate\Support\Carbon::parse($log->scan_time)->format('M d, g:i A')) }} }"
                                            class="!text-red-600 hover:!text-red-700">Delete</button>
                                    </x-record-action-menu>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-400">
                                    No attendance logs found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>

                <div class="px-6 py-4 border-t border-gray-200" data-attendance-pagination>
                    {{ $logs->links() }}
                </div>
            </div>
        </div>

        {{-- Create Log Modal --}}
        <x-admin-modal show="showCreateModal" close="showCreateModal = false" z-index="z-50">
            <div @click.outside="showCreateModal = false"
                class="flex max-h-[calc(100vh-2rem)] w-full max-w-2xl flex-col overflow-hidden rounded-lg bg-white shadow-xl">

                <div class="flex shrink-0 items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">Add Attendance Log</h3>
                    <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>

                <form method="POST" action="{{ route('attendance-logs.store') }}" data-repeatable-create
                    class="grid min-h-0 gap-x-4 overflow-y-auto px-6 py-5 md:grid-cols-2">
                    @csrf

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Person</label>
                        <div x-data="attendanceLookupSearch({ endpoint: @js(route('admin.lookups.people')), selected: @js(old('user_id')) })"
                            @repeatable-form-reset.window="reset()" @click.outside="open = false" class="relative">
                            <input type="hidden" name="user_id" x-model="createUserId">
                            <input type="text" x-model="search" @input="change()" @focus="if (!createUserId) { open = true; load() }" @keydown.escape="open = false"
                                placeholder="Search people..." autocomplete="off" aria-label="Search people"
                                :aria-expanded="open.toString()" aria-controls="attendance-person-results"
                                class="w-full rounded-md border-gray-300 pr-10 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <button type="button" x-cloak x-show="search.length" @click="search = ''; change(); $el.previousElementSibling.focus()" aria-label="Clear person search" class="absolute right-2 top-2 rounded p-1 text-gray-400 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"><x-heroicon-o-x-mark class="h-4 w-4" aria-hidden="true" /></button>
                            <div id="attendance-person-results" x-cloak x-show="open" class="absolute z-10 mt-1 max-h-56 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                                <p x-show="loading" class="px-3 py-2 text-sm text-gray-500">Searching...</p>
                                <p x-show="error" x-text="error" role="alert" class="px-3 py-2 text-sm text-red-700"></p>
                                <p x-show="!loading && !error && searched && options.length === 0" class="px-3 py-2 text-sm text-gray-500">No people found.</p>
                                <template x-for="option in options" :key="option.id">
                                    <button type="button" @click="select(option)" class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-indigo-50 focus:bg-indigo-50" x-text="option.label"></button>
                                </template>
                            </div>
                        </div>
                        @error('user_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Schedule</label>
                        <div x-data="attendanceLookupSearch({ endpoint: @js(route('admin.lookups.schedules')), selected: @js(old('schedule_id')), model: 'createScheduleId', label: 'schedules' })"
                            @repeatable-form-reset.window="reset()" @click.outside="open = false" class="relative">
                            <input type="hidden" name="schedule_id" x-model="createScheduleId">
                            <input type="text" x-model="search" @input="change()" @focus="if (!createScheduleId) { open = true; load() }" @keydown.escape="open = false"
                                placeholder="Search schedules..." autocomplete="off" aria-label="Search schedules"
                                :aria-expanded="open.toString()" aria-controls="attendance-schedule-results"
                                class="w-full rounded-md border-gray-300 pr-10 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <button type="button" x-cloak x-show="search.length" @click="search = ''; change(); $el.previousElementSibling.focus()" aria-label="Clear schedule search" class="absolute right-2 top-2 rounded p-1 text-gray-400 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"><x-heroicon-o-x-mark class="h-4 w-4" aria-hidden="true" /></button>
                            <div id="attendance-schedule-results" x-cloak x-show="open" class="absolute z-10 mt-1 max-h-56 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                                <p x-show="loading" class="px-3 py-2 text-sm text-gray-500">Searching...</p>
                                <p x-show="error" x-text="error" role="alert" class="px-3 py-2 text-sm text-red-700"></p>
                                <p x-show="!loading && !error && searched && options.length === 0" class="px-3 py-2 text-sm text-gray-500">No schedules found.</p>
                                <template x-for="option in options" :key="option.id">
                                    <button type="button" @click="select(option)" class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-indigo-50 focus:bg-indigo-50" x-text="option.label"></button>
                                </template>
                            </div>
                        </div>
                        @error('schedule_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select name="attendance_type"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Select Type --</option>
                            <option value="time_in" @selected(old('attendance_type') === 'time_in')>Time In</option>
                            <option value="time_out" @selected(old('attendance_type') === 'time_out')>Time Out</option>
                        </select>
                        @error('attendance_type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Scan Time</label>
                        <input type="datetime-local" name="scan_time" value="{{ old('scan_time') }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('scan_time')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status Override <span
                                class="text-gray-400 font-normal">(optional)</span></label>
                        <select name="status_override"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Auto-detect (present/late)</option>
                            <option value="present" @selected(old('status_override') === 'present')>Present</option>
                            <option value="late" @selected(old('status_override') === 'late')>Late</option>
                            <option value="absent" @selected(old('status_override') === 'absent')>Absent</option>
                            <option value="excused" @selected(old('status_override') === 'excused')>Excused</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-400">Leave as Auto-detect unless you're making a manual
                            correction.</p>
                        @error('status_override')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Scanner Location <span
                                class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="text" name="scanner_location" value="{{ old('scanner_location') }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="e.g. Main Gate">
                        @error('scanner_location')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Remarks <span
                                class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="text" name="remarks" value="{{ old('remarks') }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="e.g. Manual correction, doctor's note on file">
                        @error('remarks')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div
                        class="sticky bottom-0 flex items-center justify-end gap-3 border-t border-gray-100 bg-white py-4 md:col-span-2">
                        <button type="button" @click="showCreateModal = false"
                            class="text-sm text-gray-500 hover:text-gray-700">
                            Cancel
                        </button>
                        <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded">
                            Save Log
                        </button>
                    </div>
                </form>
            </div>
        </x-admin-modal>

        {{-- Edit Log Modal --}}
        <x-admin-modal show="editModal.show" close="editModal.show = false" z-index="z-50">
            <div @click.outside="editModal.show = false"
                class="flex max-h-[calc(100vh-2rem)] w-full max-w-2xl flex-col overflow-hidden rounded-lg bg-white shadow-xl">

                <div class="flex shrink-0 items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">Edit Attendance Log</h3>
                    <button type="button" @click="editModal.show = false" class="text-gray-400 hover:text-gray-600">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>

                <form method="POST" :action="'{{ url('attendance-logs') }}/' + editModal.id"
                    class="grid min-h-0 gap-x-4 overflow-y-auto px-6 py-5 md:grid-cols-2"
                    data-password-confirmation-required>
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Person</label>
                        <x-admin-lookup-field :endpoint="route('admin.lookups.people')" name="user_id" model="editModal.user_id"
                            :selected="old('_form') === 'edit' ? old('user_id') : null" placeholder="Search people..." empty-label="-- Select Person --"
                            lookup-key="attendance-edit" />
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Schedule</label>
                        <x-admin-lookup-field :endpoint="route('admin.lookups.schedules')" name="schedule_id" model="editModal.schedule_id"
                            :selected="old('_form') === 'edit' ? old('schedule_id') : null" placeholder="Search schedules..." empty-label="-- Select Schedule --"
                            lookup-key="attendance-edit" />
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select name="attendance_type" x-model="editModal.attendance_type"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="time_in">Time In</option>
                            <option value="time_out">Time Out</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Scan Time</label>
                        <input type="datetime-local" name="scan_time" x-model="editModal.scan_time"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status Override</label>
                        <select name="status_override" x-model="editModal.status_override"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="present">Present</option>
                            <option value="late">Late</option>
                            <option value="absent">Absent</option>
                            <option value="excused">Excused</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-400">Editing always uses this value directly (no auto-detect
                            on edit).</p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Scanner Location</label>
                        <input type="text" name="scanner_location" x-model="editModal.scanner_location"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="mb-6 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
                        <input type="text" name="remarks" x-model="editModal.remarks"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div
                        class="sticky bottom-0 flex items-center justify-end gap-3 border-t border-gray-100 bg-white py-4 md:col-span-2">
                        <button type="button" @click="editModal.show = false"
                            class="text-sm text-gray-500 hover:text-gray-700">
                            Cancel
                        </button>
                        <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded">
                            Update Log
                        </button>
                    </div>
                </form>
            </div>
        </x-admin-modal>

        {{-- Delete Confirmation Modal --}}
        <x-admin-modal show="deleteModal.show" close="deleteModal.show = false" z-index="z-50">
            <div @click.outside="deleteModal.show = false" class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6">

                <h3 class="text-lg font-semibold text-gray-800 mb-2">Delete Log</h3>
                <p class="text-sm text-gray-500 mb-6">
                    Are you sure you want to delete <span class="font-medium text-gray-700"
                        x-text="deleteModal.name"></span>?
                    This can't be undone.
                </p>

                <form method="POST" :action="'{{ url('attendance-logs') }}/' + deleteModal.id"
                    data-password-confirmation-required>
                    @csrf
                    @method('DELETE')

                    <div class="flex items-center justify-end gap-3">
                        <button type="button" @click="deleteModal.show = false"
                            class="text-sm text-gray-500 hover:text-gray-700">
                            Cancel
                        </button>
                        <button type="submit"
                            class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded">
                            Delete
                        </button>
                    </div>
                </form>
            </div>
        </x-admin-modal>
    </div>
</x-app-layout>
