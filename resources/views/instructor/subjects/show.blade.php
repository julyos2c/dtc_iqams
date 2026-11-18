<x-instructor-layout :title="$meetings->first()->subject->subject_code . ' - Students'">
    @php($first = $meetings->first())
    <div data-subject-roster class="space-y-6" x-data="{
        deleteId: '', deleteName: '', deleting: false, historyName: '', historyItems: [], statusFilter: new URLSearchParams(window.location.search).get('roster_status') || '', selected: [], query: new URLSearchParams(window.location.search).get('roster_search') || '', page: Math.max(1, Number(new URLSearchParams(window.location.search).get('roster_page')) || 1),
        rows: @js($rows->values()->map(fn ($row) => ['number' => $row['number'], 'name' => $row['name'], 'status' => $row['status'], 'entry_id' => $row['status'] === 'draft' ? (string) $row['entry_id'] : null])->all()),
        get filtered() { const q = this.query.trim().toLocaleLowerCase(); return this.rows.filter(row => (!this.statusFilter || row.status === this.statusFilter) && (row.name + ' ' + row.number).toLocaleLowerCase().includes(q)); },
        get visibleDrafts() { return this.filtered.slice((this.page - 1) * 10, this.page * 10).filter(row => row.entry_id).map(row => row.entry_id); },
        toggleVisible(checked) { this.selected = checked ? [...new Set([...this.selected, ...this.visibleDrafts])] : this.selected.filter(id => !this.visibleDrafts.includes(id)); },
        get pages() { return Math.max(1, Math.ceil(this.filtered.length / 10)); },
        visible(number) { return this.filtered.slice((this.page - 1) * 10, this.page * 10).some(row => row.number === number); },
        updateUrl() { const url = new URL(window.location.href); this.query ? url.searchParams.set('roster_search', this.query) : url.searchParams.delete('roster_search'); this.statusFilter ? url.searchParams.set('roster_status', this.statusFilter) : url.searchParams.delete('roster_status'); this.page > 1 ? url.searchParams.set('roster_page', this.page) : url.searchParams.delete('roster_page'); history.replaceState(null, '', url); },
        search() { this.page = 1; this.updateUrl(); },
        go(page) { this.page = Math.max(1, Math.min(page, this.pages)); this.updateUrl(); }
    }" x-init="page = Math.min(page, pages); if (@js($search !== '' || old('first_name') !== null)) $nextTick(() => $refs.addDialog.showModal())">
        <a href="{{ route('instructor.schedule') }}" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-[#10294b] shadow-sm hover:bg-slate-50"><x-heroicon-o-arrow-left class="h-4 w-4" />Back to My Teaching Schedule</a>
        @include('instructor.class-lists.errors')
        <section class="min-w-0 rounded-xl border border-slate-200 bg-white p-3 text-[#10294b] min-[375px]:p-4 md:p-5 lg:p-6">
            <div class="md:hidden">
                <div class="flex items-start justify-between gap-3 border-b border-slate-200 pb-3">
                    <h2 class="min-w-0 break-words text-base font-semibold min-[375px]:text-lg min-[375px]:font-bold">{{ $first->subject->subject_name }}</h2>
                    <x-heroicon-o-book-open class="h-5 w-5 shrink-0 text-slate-500" aria-hidden="true" />
                </div>
                <div class="mt-3 grid grid-cols-2 gap-3 min-[375px]:gap-4">
                    <div class="min-w-0"><p class="text-[11px] text-slate-500 min-[375px]:text-xs">Section</p><p class="break-words text-[13px] font-semibold min-[375px]:text-sm">{{ $first->section->section_name }}</p></div>
                    <div class="min-w-0"><p class="text-[11px] text-slate-500 min-[375px]:text-xs">Students</p><p class="text-[13px] font-semibold min-[375px]:text-sm"><span data-roster-count>{{ $rows->count() }}</span></p></div>
                    @foreach($meetings as $meeting)
                        <div class="min-w-0"><p class="text-[11px] text-slate-500 min-[375px]:text-xs">Schedule Day</p><p class="break-words text-[13px] font-semibold min-[375px]:text-sm">{{ ucfirst($meeting->day) }}</p></div>
                        <div class="min-w-0"><p class="text-[11px] text-slate-500 min-[375px]:text-xs">Time</p><p class="break-words text-[13px] font-semibold min-[375px]:text-sm">{{ \Carbon\Carbon::parse($meeting->start_time)->format('g:i A') }} - {{ \Carbon\Carbon::parse($meeting->end_time)->format('g:i A') }}</p></div>
                        <div class="col-span-2 min-w-0"><p class="text-[11px] text-slate-500 min-[375px]:text-xs">Room</p><p class="break-words text-[13px] font-semibold min-[375px]:text-sm">{{ $meeting->room }}</p></div>
                    @endforeach
                </div>
            </div>
            <div class="hidden min-w-0 items-center gap-5 md:flex">
                <x-heroicon-o-book-open class="h-7 w-7 shrink-0 text-slate-500" aria-hidden="true" />
                <div class="min-w-0 flex-1">
                    <h2 class="break-words text-xl font-bold">{{ $first->subject->subject_name }}</h2>
                    <ul class="mt-3 flex flex-wrap gap-3 text-sm font-medium">
                        <li class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2"><x-heroicon-o-academic-cap class="h-4 w-4 shrink-0 text-slate-500" aria-hidden="true" />{{ $first->section->section_name }}</li>
                        @foreach($meetings as $meeting)
                            <li class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2"><x-heroicon-o-calendar-days class="h-4 w-4 shrink-0 text-slate-500" aria-hidden="true" />{{ ucfirst($meeting->day) }}</li>
                            <li class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2"><x-heroicon-o-clock class="h-4 w-4 shrink-0 text-slate-500" aria-hidden="true" />{{ \Carbon\Carbon::parse($meeting->start_time)->format('g:i A') }} - {{ \Carbon\Carbon::parse($meeting->end_time)->format('g:i A') }}</li>
                            <li class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2"><x-heroicon-o-map-pin class="h-4 w-4 shrink-0 text-slate-500" aria-hidden="true" />{{ $meeting->room }}</li>
                        @endforeach
                    </ul>
                </div>
                <div class="flex shrink-0 items-center gap-3 border-l border-slate-200 pl-5"><x-heroicon-o-user-group class="h-7 w-7 text-slate-500" aria-hidden="true" /><div><p class="text-sm">Students</p><p class="text-2xl font-bold">{{ $rows->count() }}</p></div></div>
            </div>
        </section>
        <section data-roster-list data-roster-rows="{{ json_encode($rows->values()->map(fn ($row) => ['number' => $row['number'], 'name' => $row['name'], 'status' => $row['status'], 'entry_id' => $row['status'] === 'draft' ? (string) $row['entry_id'] : null])->all()) }}" class="min-w-0 rounded-xl border bg-white p-3 min-[375px]:p-4 md:p-5">
            <h2 class="text-lg font-semibold">Students for this subject</h2>
            <p class="mt-1 text-sm text-slate-600">Manage students, review enrollment statuses, and submit drafts for admin approval.</p>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <div class="grid w-full grid-cols-1 gap-2 min-[360px]:grid-cols-2 sm:flex sm:w-auto sm:flex-wrap">
                    <button type="button" @click="$refs.addDialog.showModal()" class="inline-flex min-h-11 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg bg-blue-700 px-3 text-[13px] font-semibold text-white transition hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700 sm:text-sm"><x-heroicon-o-plus class="h-4 w-4 shrink-0" aria-hidden="true" />Add Student</button>
                    <button type="button" @click="document.getElementById('copy-students-dialog').showModal()" class="inline-flex min-h-11 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-slate-300 bg-white px-3 text-[13px] font-semibold text-slate-800 transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700 sm:text-sm"><x-heroicon-o-document-duplicate class="h-4 w-4 shrink-0" aria-hidden="true" />Copy Students</button>
                    <button form="submit-roster" type="submit" :disabled="selected.length === 0" class="inline-flex min-h-11 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg bg-blue-100 px-3 text-[13px] font-semibold text-blue-800 transition hover:bg-blue-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500 min-[360px]:col-span-2 sm:text-sm"><x-heroicon-o-paper-airplane class="h-4 w-4 shrink-0 -rotate-45" aria-hidden="true" /><span x-text="'Submit for Approval (' + selected.length + ')'"></span></button>
                </div>
                <label class="w-full sm:w-auto lg:ml-auto"><span class="sr-only">Filter by enrollment status</span><select x-model="statusFilter" @change="search()" class="min-h-11 w-full rounded-lg border-slate-300 text-sm"><option value="">All Statuses</option><option value="draft">Draft</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select></label>
                <label class="w-full lg:w-72"><span class="sr-only">Search students by ID or name</span><input type="search" x-model.debounce.250ms="query" @input.debounce.250ms="search()" placeholder="Search student (ID or name)..." class="min-h-11 w-full rounded-lg border-slate-300 text-sm focus:border-blue-600 focus:ring-blue-600"></label>
            </div>
            <form id="submit-roster" method="POST" action="{{ route('instructor.subjects.submit', $groupId) }}" class="mt-4 space-y-4">
                @csrf
                @if($readyEntries->isNotEmpty())
                    <label class="block rounded-lg bg-slate-50 p-3 text-sm text-slate-700"><input type="checkbox" name="confirmed" value="1" required class="mr-2 rounded">I checked these students against this subject's official printed roster.</label>
                @else
                    <p class="text-sm text-slate-500">No draft students available for submission.</p>
                @endif
                <template x-for="id in selected" :key="id"><input type="hidden" name="entry_ids[]" :value="id"></template>
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-100 text-slate-900"><tr><th class="rounded-l-lg p-3"><input type="checkbox" aria-label="Select drafts on this page" :checked="visibleDrafts.length > 0 && visibleDrafts.every(id => selected.includes(id))" :disabled="visibleDrafts.length === 0" @change="toggleVisible($event.target.checked)" class="rounded border-slate-400 text-blue-700 focus:ring-blue-600"></th><th class="p-3">Student</th><th class="p-3">Student ID</th><th class="p-3">Status</th><th class="rounded-r-lg p-3">Actions</th></tr></thead>
                        <tbody>
                            @foreach($rows as $row)
                                <tr class="border-b border-slate-200" x-show="visible(@js($row['number']))">
                                    <td class="p-3">@if($row['status'] === 'draft')<input type="checkbox" value="{{ $row['entry_id'] }}" x-model="selected" aria-label="Select {{ $row['name'] }} for approval" class="rounded border-slate-400 text-blue-700 focus:ring-blue-600">@endif</td>
                                    
                                    <td class="p-3"><div class="flex items-center gap-3"><span aria-hidden="true" class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-blue-100 text-xs font-bold text-blue-800">{{ collect(preg_split('/\s+/', trim($row['name'])))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') }}</span><span class="font-semibold">{{ $row['name'] }}@if($row['new_registration'])<span class="block text-xs font-normal text-slate-500">Pending registration</span>@endif</span></div></td>
                                    <td class="p-3">{{ $row['number'] }}</td>
                                    <td class="p-3"><span @class(['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-emerald-100 text-emerald-800' => $row['status'] === 'approved', 'bg-amber-100 text-amber-800' => $row['status'] === 'pending', 'bg-slate-100 text-slate-700' => $row['status'] === 'draft', 'bg-red-100 text-red-800' => $row['status'] === 'rejected'])>{{ ucfirst($row['status']) }}</span></td>
                                    <td class="p-3">@if($row['status'] === 'draft')<button type="button" data-entry-id="{{ $row['entry_id'] }}" data-student-name="{{ $row['name'] }}" @click="deleteId = $el.dataset.entryId; deleteName = $el.dataset.studentName; deleting = false; $refs.deleteDialog.showModal()" aria-label="Delete {{ $row['name'] }} from draft" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-red-200 text-red-700 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700"><x-heroicon-o-trash class="h-4 w-4" /></button>@endif @if(!empty($row['history']))<button type="button" data-history="{{ json_encode($row['history']) }}" data-student-name="{{ $row['name'] }}" @click="historyItems = JSON.parse($el.dataset.history); historyName = $el.dataset.studentName; $refs.historyDialog.showModal()" class="min-h-10 rounded-lg px-2 text-sm font-semibold text-blue-700 hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700">View Details</button>@endif @if($row['correction_url'] ?? null)<a href="{{ $row['correction_url'] }}" class="inline-block py-2 text-sm font-semibold text-blue-700 underline">Correct and resubmit</a>@endif @if($row['status'] !== 'draft' && empty($row['history']))<span aria-label="No actions">&mdash;</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="grid gap-2.5 sm:grid-cols-2 md:hidden">
                    @foreach($rows as $row)
                        <article x-show="visible(@js($row['number']))" class="rounded-xl border border-slate-200 bg-white p-3">
                            <div class="flex items-start gap-2">
                                @if($row['status'] === 'draft')<label class="flex min-h-11 min-w-11 shrink-0 items-center justify-center"><input type="checkbox" value="{{ $row['entry_id'] }}" x-model="selected" aria-label="Select {{ $row['name'] }} for approval" class="rounded border-slate-400 text-blue-700 focus:ring-blue-600"></label>@endif
                                <div class="flex min-w-0 flex-1 items-center gap-2">
                                    <span aria-hidden="true" class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-blue-100 text-xs font-bold text-blue-800">{{ collect(preg_split('/\s+/', trim($row['name'])))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') }}</span>
                                    <span class="min-w-0 break-words text-sm font-semibold text-slate-900">{{ $row['name'] }}</span>
                                </div>
                                <span @class(['shrink-0 rounded-full px-2 py-1 text-xs font-semibold', 'bg-emerald-100 text-emerald-800' => $row['status'] === 'approved', 'bg-amber-100 text-amber-800' => $row['status'] === 'pending', 'bg-slate-100 text-slate-700' => $row['status'] === 'draft', 'bg-red-100 text-red-800' => $row['status'] === 'rejected'])>{{ ucfirst($row['status']) }}</span>
                            </div>
                            <div class="mt-2 text-left"><p class="text-xs text-slate-500">Student ID</p><p class="text-[13px] font-medium text-slate-800">{{ $row['number'] }}</p></div>
                            @if($row['status'] === 'draft' || !empty($row['history']))<div class="mt-3 flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-1">
                                @if(!empty($row['history']))<button type="button" data-history="{{ json_encode($row['history']) }}" data-student-name="{{ $row['name'] }}" @click="historyItems = JSON.parse($el.dataset.history); historyName = $el.dataset.studentName; $refs.historyDialog.showModal()" class="min-h-11 rounded-lg px-2 text-sm font-semibold text-blue-700">View Details</button>@endif
                                @if($row['correction_url'] ?? null)<a href="{{ $row['correction_url'] }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-blue-700 underline">Correct and resubmit</a>@endif
                                @if($row['status'] === 'draft')<button type="button" data-entry-id="{{ $row['entry_id'] }}" data-student-name="{{ $row['name'] }}" @click="deleteId = $el.dataset.entryId; deleteName = $el.dataset.studentName; deleting = false; $refs.deleteDialog.showModal()" class="min-h-11 rounded-lg px-3 text-sm font-semibold text-red-700 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700">Delete</button>@endif
                            </div>@endif
                        </article>
                    @endforeach
                </div>
                <p x-show="filtered.length === 0" class="py-3 text-center text-sm text-slate-600" x-text="rows.length === 0 ? 'No students have been added to this subject.' : (query.trim() ? 'No students match your search.' : 'No students found with this enrollment status.')"></p>
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-slate-600" x-show="filtered.length > 0">
                    <span x-text="'Showing ' + ((page - 1) * 10 + 1) + ' - ' + Math.min(page * 10, filtered.length) + ' of ' + filtered.length + ' students'"></span>
                    <div class="ml-auto flex items-center gap-2"><button type="button" @click="go(page - 1)" :disabled="page === 1" class="min-h-11 rounded-lg border px-3 disabled:opacity-50">Previous</button><span x-text="page + ' / ' + pages"></span><button type="button" @click="go(page + 1)" :disabled="page === pages" class="min-h-11 rounded-lg border px-3 disabled:opacity-50">Next</button></div>
                </div>

            </form>
            @foreach($readyEntries as $entry)
                <form id="remove-entry-{{ $entry->id }}" method="POST" action="{{ route('instructor.subjects.students.destroy', [$groupId, $entry]) }}">@csrf @method('DELETE')</form>
            @endforeach
        </section>
        <dialog x-ref="historyDialog" aria-labelledby="history-title" class="fixed inset-0 m-auto max-h-[85vh] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-xl bg-white p-5 shadow-xl backdrop:bg-black/40">
            <div class="flex items-start justify-between gap-3"><div><h2 id="history-title" class="text-lg font-semibold text-[#10294b]">Enrollment request details</h2><p class="text-sm text-slate-600" x-text="historyName"></p></div><button type="button" @click="$refs.historyDialog.close()" aria-label="Close request details" class="min-h-10 min-w-10 text-xl text-slate-600">&times;</button></div>
            <div class="mt-4 space-y-3"><template x-for="(item, index) in historyItems" :key="index"><div class="rounded-lg border border-slate-200 p-3 text-sm"><p class="font-semibold capitalize text-slate-800" x-text="item.status + (item.date ? ' - ' + item.date : '')"></p><p x-show="item.notes" class="mt-1 whitespace-pre-wrap break-words text-slate-600" x-text="item.notes"></p></div></template></div>
            <div class="mt-4 flex justify-end"><button type="button" @click="$refs.historyDialog.close()" class="min-h-10 rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700">Close</button></div>
        </dialog>
        <dialog x-ref="deleteDialog" aria-labelledby="delete-student-title" aria-describedby="delete-student-description" @cancel="if (deleting) $event.preventDefault()" style="position: fixed; inset: 0; margin: auto; height: fit-content;" class="w-[calc(100%-2rem)] max-w-sm rounded-lg bg-white p-6 shadow-xl backdrop:bg-black/40">
            <h3 id="delete-student-title" class="mb-2 text-lg font-semibold text-gray-800">Remove Student</h3>
            <p id="delete-student-description" class="mb-6 text-sm text-gray-500">Are you sure you want to remove <span class="font-medium text-gray-700" x-text="deleteName"></span> from this subject's draft? Existing enrollments and attendance history will be retained.</p>
            <div class="flex items-center justify-end gap-3">
                <button type="button" @click="$refs.deleteDialog.close()" :disabled="deleting" class="min-h-10 px-2 text-sm text-gray-500 hover:text-gray-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700">Cancel</button>
                <button type="button" @click="if (!deleting) { deleting = true; document.getElementById('remove-entry-' + deleteId).requestSubmit() }" :disabled="deleting" class="min-h-10 rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700 disabled:opacity-50" x-text="deleting ? 'Removing...' : 'Remove'"></button>
            </div>
        </dialog>
        <dialog x-ref="addDialog" aria-labelledby="add-dialog-title" class="m-auto max-h-[90vh] w-[calc(100%-2rem)] max-w-3xl overflow-y-auto rounded-2xl border border-slate-200 bg-white p-3 shadow-xl backdrop:bg-slate-950/50 sm:p-4">
            <div class="flex items-start gap-3"><span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-blue-100 text-blue-700"><x-heroicon-o-user-plus class="h-6 w-6" aria-hidden="true" /></span><div class="min-w-0 flex-1"><h2 id="add-dialog-title" class="text-xl font-bold text-[#10294b]">Add Student</h2><p class="mt-1 text-sm text-slate-600">Add an existing student or register a new student to this subject.</p></div><button type="button" @click="$el.closest('dialog').close()" aria-label="Close add student dialog" class="min-h-11 min-w-11 border-0 bg-transparent p-0 text-xl text-[#10294b] hover:text-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700">&times;</button></div>
            @include('instructor.class-lists.errors')
            <div x-data="{ tab: @js(old('first_name') !== null ? 'new' : 'existing') }">
            <div class="mt-2 flex flex-wrap gap-2 border-b border-slate-200 pb-2"><button type="button" @click="tab = 'existing'" :aria-pressed="tab === 'existing'" :class="tab === 'existing' ? 'bg-blue-700 text-white' : 'border border-slate-300 bg-white text-[#10294b]'" class="inline-flex min-h-9 items-center gap-2 rounded-lg px-3 text-[13px] font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700"><x-heroicon-o-user class="h-4 w-4" aria-hidden="true" />Existing Student</button><button type="button" @click="tab = 'new'" :aria-pressed="tab === 'new'" :class="tab === 'new' ? 'bg-blue-700 text-white' : 'border border-slate-300 bg-white text-[#10294b]'" class="inline-flex min-h-9 items-center gap-2 rounded-lg px-3 text-[13px] font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700"><x-heroicon-o-user-plus class="h-4 w-4" aria-hidden="true" />Register New Student</button></div>
            <div x-show="tab === 'existing'" x-data="existingStudentSearch(@js(route('instructor.subjects.students.search', $groupId)), @js(route('instructor.subjects.students.store', $groupId)))">
                <h3 class="mt-3 font-semibold text-[#10294b]">Find an existing student</h3><p class="mt-1 text-sm text-slate-600">Search by student number or name to add an existing student to this subject.</p>
                <form @submit.prevent="search()" class="mt-3 flex gap-2">@csrf<label class="sr-only" for="student-search">Student number or student name</label><input id="student-search" x-model="query" @input.debounce.350ms="search()" type="search" maxlength="100" placeholder="Student number or student name..." class="min-w-0 w-full rounded-lg border-slate-300 text-sm focus:border-blue-600 focus:ring-blue-600"></form>
                <p x-show="!searched && !loading && !error" class="py-4 text-sm text-slate-600">Search for an existing student.</p><p x-show="loading" x-cloak class="py-4 text-sm text-slate-600" role="status">Loading students...</p><p x-show="error" x-cloak class="py-3 text-sm text-red-700" role="alert" x-text="error"></p>
                <div x-show="searched && !loading && !error" x-cloak><div class="mt-4 flex justify-between gap-2 text-sm"><h4 class="font-semibold text-slate-900">Search Results</h4><span class="text-slate-600" x-text="total + ' students found'"></span></div><p x-show="!results.length" class="py-4 text-sm text-slate-600">No students found.</p>
                    <div class="mt-2 max-h-64 space-y-2 overflow-y-auto"><template x-for="student in results" :key="student.id"><div class="flex items-center gap-2 rounded-lg border border-slate-200 p-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-blue-100 text-xs font-bold text-blue-800" x-text="initials(student.name)"></span><div class="min-w-0 flex-1"><p class="break-words text-sm font-semibold text-slate-900" x-text="student.name"></p><p class="break-words text-xs text-slate-600" x-text="[student.number, student.course, student.section].filter(Boolean).join(' ? ')"></p><p x-show="student.reason" class="mt-1 text-xs text-slate-600" x-text="student.reason"></p></div><button type="button" @click="add(student)" :disabled="student.status !== 'available' || adding === student.id" class="min-h-10 shrink-0 rounded-lg border border-blue-600 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:text-slate-500" x-text="adding === student.id ? 'Adding...' : (student.status === 'available' ? 'Add' : student.status_label)"></button></div></template></div>
                    <button type="button" x-show="hasMore" @click="loadMore()" :disabled="loadingMore" class="mt-3 min-h-10 rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700" x-text="loadingMore ? 'Loading...' : 'Load More'"></button>
                </div>
            </div>
            <div x-show="tab === 'new'" x-cloak>
            <div class="mt-2 border-l-4 border-blue-600 pl-3"><h3 class="text-lg font-bold text-[#10294b]">Register a new student</h3><p class="mt-1 text-sm text-slate-600">Enter the student's details from the printed list. The account and QR credential will be created after admin approval.</p></div>
            <form method="POST" action="{{ route('instructor.subjects.students.store', $groupId) }}" x-data="{ course: @js((string) old('course_id', '')), section: @js((string) old('section_id', '')), sections: @js($sections->map(fn ($section) => ['id' => (string) $section->id, 'course_id' => (string) $section->course_id, 'name' => $section->section_name])->values()->all()) }" class="mt-2 space-y-2">
                @csrf
                <section class="overflow-hidden rounded-xl border border-slate-200"><h4 class="bg-slate-100 px-3 py-1.5 font-semibold text-[#10294b]">Student Information</h4><div class="grid gap-2 p-3 sm:grid-cols-3">
                    @foreach(['student_no' => 'Student Number', 'first_name' => 'First Name', 'middle_name' => 'Middle Initial', 'last_name' => 'Last Name', 'name_suffix' => 'Suffix', 'email' => 'Email'] as $field => $label)
                        <label class="min-w-0 text-sm font-medium text-[#10294b]">{{ $label }} @if(in_array($field, ['middle_name', 'name_suffix']))<span class="font-normal text-slate-600">(optional)</span>@endif
                            <input name="{{ $field }}" value="{{ old($field) }}" type="{{ $field === 'email' ? 'email' : 'text' }}" @required(!in_array($field, ['middle_name', 'name_suffix'])) maxlength="{{ $field === 'student_no' ? 50 : ($field === 'name_suffix' ? 100 : 255) }}" @if($field === 'name_suffix') placeholder="e.g., Jr., Sr., III" @endif class="mt-1 block w-full rounded-lg border-slate-300 text-sm font-normal focus:border-blue-600 focus:ring-blue-600">
                        </label>
                    @endforeach
                </div><div class="grid gap-2 border-t border-slate-100 p-3 sm:grid-cols-2">
                    <label class="text-sm font-medium text-[#10294b]">Course<select name="course_id" x-model="course" @change="section = ''" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm font-normal"><option value="">Choose course</option>@foreach($courses as $course)<option value="{{ $course->id }}" @selected((string) old('course_id') === (string) $course->id)>{{ $course->course_code }}</option>@endforeach</select></label>
                    <label class="text-sm font-medium text-[#10294b]">Home Section <span class="font-normal text-slate-600">(optional)</span><select name="section_id" x-model="section" :disabled="!course" class="mt-1 block w-full rounded-lg border-slate-300 text-sm font-normal disabled:bg-slate-100"><option value="" x-text="course ? 'Not assigned' : 'Choose a course first'"></option><template x-for="item in sections.filter(item => item.course_id === course)" :key="item.id"><option :value="item.id" x-text="item.name"></option></template></select></label>

                </div></section>
                <div class="flex flex-wrap justify-end gap-2 border-t border-slate-200 pt-2"><button type="button" @click="$el.closest('dialog').close()" class="min-h-9 rounded-lg border border-slate-300 px-3 text-[13px] font-semibold text-[#10294b] hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700">Cancel</button><button type="submit" class="inline-flex min-h-9 items-center justify-center gap-2 rounded-lg bg-blue-700 px-3 text-[13px] font-semibold text-white hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700"><x-heroicon-o-plus class="h-4 w-4" aria-hidden="true" />Add new student to this subject</button></div>
            </form>
            </div></div>
        </dialog>
        <dialog id="copy-students-dialog" x-data="copyStudentsDialog(@js(route('instructor.subjects.copy-preview', $groupId)), @js(route('instructor.subjects.copy', $groupId)))" @cancel="if (submitting) $event.preventDefault()" aria-labelledby="copy-dialog-title" aria-describedby="copy-dialog-description" class="m-auto max-h-[90dvh] w-[calc(100%-24px)] max-w-[680px] overflow-hidden rounded-2xl border border-slate-200 bg-white p-0 shadow-xl backdrop:bg-slate-950/50 sm:max-h-[85vh]">
            <form @submit.prevent="submit($el)" class="flex max-h-[90dvh] min-h-0 flex-col sm:max-h-[85vh]">
                @csrf
                <header class="shrink-0 px-4 pt-4 sm:px-6 sm:pt-6">
                    <div class="flex items-start justify-between gap-3"><div><h2 id="copy-dialog-title" class="text-xl font-bold text-[#10294b]">Copy Students</h2><p id="copy-dialog-description" class="mt-1 text-sm text-slate-600">Select students from another subject to add as drafts. Admin approval is required before enrollment.</p></div><button type="button" @click="$el.closest('dialog').close()" :disabled="submitting" aria-label="Close copy students dialog" class="min-h-11 min-w-11 rounded-lg text-xl text-slate-600 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700">&times;</button></div>
                    <label for="copy-from" class="mt-4 block text-sm font-semibold text-slate-800">Source Subject</label>
                    <select id="copy-from" x-model="source" @change="load()" :disabled="submitting" class="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                        <option value="">Select a subject</option>
                        @foreach($offerings as $sourceGroupId => $sourceMeetings)
                            <option value="subject:{{ $sourceGroupId }}">{{ $sourceMeetings->first()->subject->subject_name }} ? {{ $sourceMeetings->first()->section->section_name }}</option>
                        @endforeach
                        @foreach($savedDrafts as $saved)
                            <option value="saved:{{ $saved->id }}">Previously saved students: {{ $saved->name }} ? {{ $saved->section->section_name }}</option>
                        @endforeach
                    </select>
                    @if($offerings->isEmpty() && $savedDrafts->isEmpty())<p class="mt-2 text-sm text-slate-600">No source subjects are available.</p>@endif
                    <div x-show="source && !loading && !error && students.length" x-cloak>
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-2"><h3 class="font-semibold text-slate-900">Available Students</h3><span class="text-xs text-slate-600" x-text="students.length + ' eligible students'"></span></div>
                        <div class="relative mt-2"><x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-3 h-4 w-4 text-slate-500" aria-hidden="true" /><input type="search" x-model="search" :disabled="submitting" aria-label="Search available students" placeholder="Search student by name or ID..." class="w-full rounded-lg border-slate-300 pl-9 text-sm focus:border-blue-600 focus:ring-blue-600"></div>
                        <label class="flex min-h-11 items-center gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" :checked="allSelected" @change="toggleAll($event.target.checked)" :disabled="submitting" class="rounded border-slate-400 text-blue-700 focus:ring-blue-600">Select All</label>
                    </div>
                </header>
                <div class="min-h-0 overflow-y-auto px-4 sm:px-6" aria-live="polite">
                    <p x-show="!source" class="py-6 text-sm text-slate-600">Choose a subject to preview its students.</p>
                    <p x-show="loading" x-cloak class="py-6 text-sm text-slate-600">Loading students...</p>
                    <p x-show="error" x-cloak class="py-4 text-sm text-red-700" x-text="error"></p>
                    <p x-show="source && !loading && !error && !students.length" x-cloak class="py-6 text-sm text-slate-600">No eligible students found.</p>
                    <p x-show="source && !loading && !error && students.length && !filtered.length" x-cloak class="py-6 text-sm text-slate-600">No students match your search.</p>
                    <div x-show="!loading && !error" class="divide-y divide-slate-100">
                        <template x-for="student in filtered" :key="student.key"><label class="flex min-h-14 items-center gap-3 py-2"><input type="checkbox" :value="student.key" x-model="selected" :disabled="submitting" class="rounded border-slate-400 text-blue-700 focus:ring-blue-600"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-blue-100 text-xs font-bold text-blue-800" x-text="initials(student.name)"></span><span class="min-w-0 flex-1"><span class="block break-words text-sm font-semibold text-slate-900" x-text="student.name"></span><span class="block break-all text-xs text-slate-600" x-text="student.number"></span></span></label></template>
                    </div>
                </div>
                <footer class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-t border-slate-200 p-4 sm:px-6"><span class="text-sm text-slate-600" x-text="selected.length + ' students selected'"></span><div class="ml-auto flex flex-wrap gap-2"><button type="button" @click="$el.closest('dialog').close()" :disabled="submitting" class="min-h-10 rounded-lg border border-slate-300 px-3 text-[13px] font-semibold hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700">Cancel</button><button type="submit" :disabled="!selected.length || submitting || loading" class="min-h-10 rounded-lg bg-blue-700 px-3 text-[13px] font-semibold text-white hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700 disabled:cursor-not-allowed disabled:opacity-50" x-text="submitting ? 'Copying...' : 'Copy Selected (' + selected.length + ')'"></button></div></footer>
            </form>
        </dialog>

    </div>
</x-instructor-layout>

