<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-medium">Manage Students</h2></x-slot>
    <div class="mx-auto max-w-7xl space-y-4 p-4 sm:p-6" x-data="{
        selected: [], targets: [], decision: '', rejectOpen: false, reason: '',
        review(ids, action) {
            this.targets = [...ids];
            this.decision = action;
            if (action === 'rejected') {
                this.reason = '';
                this.rejectOpen = true;
                this.$nextTick(() => this.$refs.reason.focus());
            } else {
                this.$nextTick(() => this.$refs.reviewForm.requestSubmit());
            }
        },
        toggleAll(ids) { this.selected = this.selected.length === ids.length ? [] : [...ids]; }
    }">
        @include('students.partials.navigation', ['activeTab' => 'submissions'])
        <div class="flex flex-wrap items-center gap-3">
            <h3 class="text-lg font-medium text-gray-800">Instructor submissions</h3>
            <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800">{{ $pendingCount }} pending</span>
        </div>
        @include('instructor.class-lists.errors')
        @unless(app(\App\Services\EnrollmentWorkflow::class)->enabled())
            <p role="status" class="rounded border border-amber-300 bg-amber-50 p-4 text-sm">Approval is waiting for the legacy roster backfill. The deployment administrator must complete cutover before accounts and enrollments can be approved.</p>
        @endunless
        @if(session('enrollment_review_results'))
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-live="polite">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-50 text-indigo-600"><x-heroicon-o-clipboard-document-check class="h-5 w-5" aria-hidden="true" /></span>
                    <div><h3 class="font-semibold text-slate-900">Review results</h3><p class="text-sm text-slate-500">Results for the submissions you reviewed.</p></div>
                </div>
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach(session('enrollment_review_results') as $result)
                        <li class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1"><span class="font-medium text-slate-800">Submission #{{ $result['id'] }}</span><span @class(['rounded-full px-2.5 py-1 text-xs font-medium capitalize', 'bg-emerald-100 text-emerald-800' => $result['status'] === 'approved', 'bg-red-100 text-red-800' => $result['status'] === 'rejected', 'bg-slate-200 text-slate-700' => ! in_array($result['status'], ['approved', 'rejected'])])>{{ $result['status'] }}</span></div>
                            @if(!empty($result['message']))<p class="mt-2 text-slate-600">{{ $result['message'] }}</p>@endif
                            @if(array_key_exists('credentials_email_sent', $result))
                                <p class="mt-1 rounded bg-amber-50 p-2">New account: <span>{{ $result['username'] }}</span>. {{ $result['credentials_email_sent'] ? 'Credentials email sent.' : 'Credentials email could not be sent. Arrange a password reset for the student.' }} A password change is required at first login.</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
        <form method="GET" action="{{ route('admin.students.submissions') }}" @change="if ($event.target.matches('select')) $el.requestSubmit()" class="grid items-end gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-3 lg:grid-cols-6">
            <label class="text-xs font-medium text-gray-600">Instructor<select name="instructor_id" class="mt-1 w-full rounded-md border-gray-300 text-sm"><option value="">All</option>@foreach($instructors as $instructor)<option value="{{ $instructor->id }}" @selected(request('instructor_id') == $instructor->id)>{{ $instructor->fullName() }}</option>@endforeach</select></label>
            <label class="text-xs font-medium text-gray-600">Subject<select name="subject_id" class="mt-1 w-full rounded-md border-gray-300 text-sm"><option value="">All</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}" @selected(request('subject_id') == $subject->id)>{{ $subject->subject_code }}</option>@endforeach</select></label>
            <label class="text-xs font-medium text-gray-600">Section<select name="section_id" class="mt-1 w-full rounded-md border-gray-300 text-sm"><option value="">All</option>@foreach($sections as $section)<option value="{{ $section->id }}" @selected(request('section_id') == $section->id)>{{ $section->section_name }}</option>@endforeach</select></label>
            <label class="text-xs font-medium text-gray-600">Request type<select name="type" class="mt-1 w-full rounded-md border-gray-300 text-sm"><option value="">All</option><option value="registration" @selected(request('type') === 'registration')>New registration</option><option value="enrollment" @selected(request('type') === 'enrollment')>Existing student</option></select></label>
            <label class="text-xs font-medium text-gray-600">Status<select name="status" class="mt-1 w-full rounded-md border-gray-300 text-sm"><option value="all" @selected($status === 'all')>All</option>@foreach(['pending', 'approved', 'rejected'] as $option)<option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>@endforeach</select></label>
            <a href="{{ route('admin.students.submissions') }}" class="rounded-md border border-indigo-300 bg-indigo-50 px-3 py-2 text-center text-sm font-medium text-indigo-700 hover:bg-indigo-100">Clear filters</a>
        </form>

        @php($pendingIds = $requests->where('status', 'pending')->pluck('id')->map(fn ($id) => (string) $id)->values())
        <form method="POST" action="{{ route('admin.enrollment-requests.review') }}" x-ref="reviewForm" data-password-confirmation-required>
            @csrf
            <input type="hidden" name="decision" :value="decision">
            <template x-for="id in targets" :key="id"><input type="hidden" name="request_ids[]" :value="id"></template>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="mb-5 flex flex-wrap items-center gap-3">
                    <span class="mr-auto text-sm font-semibold text-slate-500"><span x-text="selected.length">0</span> selected</span>
                    <button type="button" @click="review(selected, 'approved')" :disabled="!selected.length" class="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"><x-heroicon-o-check-circle class="h-4 w-4" aria-hidden="true" />Approve Selected</button>
                    <button type="button" @click="review(selected, 'rejected')" :disabled="!selected.length" class="inline-flex items-center gap-1.5 rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"><x-heroicon-o-x-circle class="h-4 w-4" aria-hidden="true" />Reject Selected</button>
                </div>
                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full min-w-[850px] text-left text-sm">
                        <thead class="bg-slate-100 text-xs uppercase text-slate-500"><tr>
                            <th class="p-4 font-bold"><input type="checkbox" aria-label="Select all pending submissions on this page" :disabled="!{{ $pendingIds->count() }}" :checked="selected.length > 0 && selected.length === {{ $pendingIds->count() }}" @change="toggleAll(@js($pendingIds))" class="rounded border-gray-300"></th>
                            <th class="p-4 font-bold">Student</th><th class="p-4 font-bold">Subject / section</th><th class="p-4 font-bold">Submitted by</th><th class="p-4 font-bold">Status / review</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($requests as $item)
                                <tr class="align-top">
                                    <td class="p-4">@if($item->status === 'pending')<input type="checkbox" value="{{ $item->id }}" x-model="selected" aria-label="Select submission {{ $item->id }}" class="rounded border-gray-300">@endif</td>
                                    <td class="p-4"><div class="flex items-start gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-100 font-normal text-indigo-700" aria-hidden="true">{{ mb_strtoupper(mb_substr($item->snapshot['student']['first_name'], 0, 1).mb_substr($item->snapshot['student']['last_name'], 0, 1)) }}</span><div><span class="font-normal">{{ $item->snapshot['student']['first_name'] }} {{ $item->snapshot['student']['middle_name'] ?? '' }} {{ $item->snapshot['student']['last_name'] }} {{ $item->snapshot['student']['name_suffix'] ?? '' }}</span><div class="text-slate-500">{{ $item->identity->student_no }}</div><div class="mt-1 text-xs text-gray-500">{{ $item->registration_request_id ? 'New registration' : 'Existing student' }}</div></div></div></td>
                                    <td class="p-4"><span class="font-normal">{{ \Illuminate\Support\Str::before($item->snapshot['subject'], ' - ') }}</span><span class="mx-2 text-slate-300" aria-hidden="true">|</span><span>{{ $item->snapshot['section'] }}</span></td>
                                    <td class="p-4">{{ $item->instructor->fullName() }}<div class="text-xs text-gray-500">{{ $item->created_at->format('M j, Y g:i A') }}</div></td>
                                    <td class="p-4"><span @class(['inline-flex rounded-full px-2.5 py-1 text-xs font-normal capitalize', 'bg-amber-50 text-amber-800' => $item->status === 'pending', 'bg-emerald-50 text-emerald-700' => $item->status === 'approved', 'bg-red-50 text-red-700' => $item->status === 'rejected'])>{{ $item->status }}</span>@if($item->status !== 'pending')<div class="mt-2 whitespace-pre-wrap text-gray-700">{{ $item->review_notes }}</div><div class="mt-1 text-xs text-gray-500">{{ $item->reviewer?->name }} · {{ $item->reviewed_at?->format('M j, Y g:i A') }}</div>@endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="p-8 text-center text-gray-500">No submissions match these filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-4">{{ $requests->links() }}</div>
            <div x-show="rejectOpen" x-cloak @keydown.escape.window="rejectOpen = false" @click.self="rejectOpen = false" class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true" aria-labelledby="reject-submissions-title">
                <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
                    <h3 id="reject-submissions-title" class="text-lg font-medium">Reject submissions</h3>
                    <p class="mt-2 text-sm text-gray-600">Give a reason for rejecting <span x-text="targets.length"></span> selected submission(s). The instructor can use it to make corrections.</p>
                    <label for="submission-reason" class="mt-4 block text-sm font-medium text-gray-700">Rejection reason <span class="text-red-700">*</span></label>
                    <textarea id="submission-reason" x-ref="reason" x-model="reason" name="review_notes" rows="3" maxlength="2000" :required="rejectOpen" :disabled="!rejectOpen" class="mt-1 w-full rounded-md border-gray-300"></textarea>
                    <div class="mt-5 flex justify-end gap-3"><button type="button" @click="rejectOpen = false" class="rounded-md px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">Cancel</button><button type="submit" class="rounded-md bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-800">Reject submissions</button></div>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
