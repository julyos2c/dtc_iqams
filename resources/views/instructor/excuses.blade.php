<x-instructor-layout title="Student Excuse Requests">
    <div class="mx-auto max-w-5xl">
        <div class="mb-6"><p class="text-xs font-bold uppercase tracking-wider text-blue-700">Attendance review</p><h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Student excuse requests</h2><p class="mt-1 text-sm text-slate-500">Review requests for your classes and provide a decision for each absence.</p></div>
        @if ($errors->any()) <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800"><p class="font-bold">Unable to complete review</p><ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
        <div class="space-y-4">@forelse($decisions as $decision)
            @php
                $excuse = $decision->excuseRequest; $log = $decision->attendanceLog; $schedule = $decision->schedule ?? $log?->schedule; $date = $decision->occurrence_date ?? $log?->attendance_date;
                $badge = match($decision->status) { 'approved' => 'bg-emerald-100 text-emerald-800', 'rejected' => 'bg-rose-100 text-rose-800', default => 'bg-amber-100 text-amber-800' };
            @endphp
            <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4 sm:px-6"><div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-wider text-blue-700">{{ $schedule?->subject?->subject_code }} · {{ $date?->format('M d, Y') }}</p><h3 class="mt-1 text-lg font-bold text-slate-900">{{ $excuse->studentUser->name }}</h3><p class="mt-1 text-sm text-slate-500">{{ $schedule?->subject?->subject_name }} · Submitted {{ $excuse->created_at->format('M d, Y') }}</p></div><span class="rounded-full px-3 py-1 text-xs font-bold {{ $badge }}">{{ ucfirst($decision->status) }}</span></div></div>
                <div class="px-5 py-5 sm:px-6"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ ucfirst($excuse->reason_type) }}</p><p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700">{{ $excuse->explanation }}</p>@if($excuse->attachment_path)<a class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-blue-700 hover:underline" href="{{ route('instructor.excuses.attachment', $excuse) }}"><x-heroicon-o-paper-clip class="h-4 w-4" /> Download attachment</a>@endif</div>
                @if($decision->status === 'pending')
                    <form method="POST" action="{{ route('instructor.excuses.decide', $decision) }}" class="border-t border-slate-100 bg-slate-50 px-5 py-5 sm:px-6">@csrf @method('PATCH')
                        <label class="block text-sm font-semibold text-slate-700">Remarks <span class="font-normal text-slate-500">(required when rejecting)</span><textarea name="remarks" maxlength="2000" rows="3" placeholder="Add a note for the student" class="mt-2 block w-full rounded-lg border-slate-300 text-sm font-normal focus:border-blue-600 focus:ring-blue-600"></textarea></label>
                        <div class="mt-4 flex flex-wrap gap-2"><button name="status" value="approved" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-800">Approve request</button><button name="status" value="rejected" class="rounded-lg border border-rose-300 bg-white px-4 py-2.5 text-sm font-bold text-rose-700 hover:bg-rose-50">Reject request</button></div>
                    </form>
                @elseif($decision->remarks)<div class="border-t border-slate-100 bg-slate-50 px-5 py-4 text-sm text-slate-600 sm:px-6"><span class="font-semibold text-slate-700">Your remarks:</span> {{ $decision->remarks }}</div>@endif
            </article>
        @empty <div class="rounded-xl border border-dashed border-slate-300 bg-white px-5 py-14 text-center"><div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-blue-50 text-blue-700"><x-heroicon-o-document-text class="h-6 w-6" /></div><p class="mt-4 font-bold text-slate-900">No requests yet</p><p class="mt-1 text-sm text-slate-500">Student excuse requests for your classes will appear here.</p></div> @endforelse</div>
        <div class="mt-5">{{ $decisions->links() }}</div>
    </div>
</x-instructor-layout>
