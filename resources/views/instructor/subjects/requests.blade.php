        <section class="rounded-xl border bg-white p-5">
            <h2 class="text-lg font-semibold">Pending and rejected requests</h2>
            <form method="GET" action="{{ route('instructor.subjects.show', $groupId) }}" class="my-4 flex gap-2">
                <label class="sr-only" for="request-status">Request status</label>
                <select id="request-status" name="status" class="rounded border-slate-300">
                    <option value="">Pending and rejected</option>
                    @foreach(['pending', 'rejected'] as $status)
                        <option @selected(request('status') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                <button class="rounded border px-4 py-2">Filter</button>
            </form>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b"><th class="p-2">Student</th><th class="p-2">Subject</th><th class="p-2">Status / admin notes</th><th class="p-2">Action</th></tr></thead>
                    <tbody>
                        @forelse($requests as $item)
                            <tr class="border-b align-top">
                                <td class="p-2"><strong>{{ $item->identity->student_no }}</strong><br>{{ $item->snapshot['student']['first_name'] }} {{ $item->snapshot['student']['last_name'] }}</td>
                                <td class="p-2">{{ $item->snapshot['subject'] }}<br><span class="text-slate-500">{{ $item->snapshot['section'] }}</span></td>
                                <td class="p-2"><strong class="capitalize">{{ $item->status }}</strong><p>{{ $item->review_notes }}</p></td>
                                <td class="p-2">@if($item->status === 'rejected')<a class="text-blue-700 underline" href="{{ route('instructor.enrollment-requests.edit', $item) }}">Correct and resubmit</a>@endif</td>
                            </tr>
                        @empty
                            <tr><td class="p-4 text-slate-500" colspan="4">No matching requests for this subject.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $requests->links() }}
        </section>
