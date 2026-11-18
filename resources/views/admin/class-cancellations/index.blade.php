<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-bold text-gray-900">Class Cancellation Requests</h1>
        <p class="mt-1 text-sm font-normal text-slate-500">Review instructor requests for individual class dates.</p>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
        @if($errors->any())
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">{{ $errors->first() }}</div>
        @endif

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-end justify-between gap-4 border-b border-slate-100 p-5 sm:px-6">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-base font-bold text-[#10294b]">Requests</h2>
                        <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600">{{ $items->total() }} {{ Str::plural('request', $items->total()) }}</span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">Select a status to narrow the list.</p>
                </div>
                <form method="GET" class="flex flex-wrap items-end gap-2">
                    <div>
                        <label for="status" class="mb-1 block text-xs font-semibold text-slate-600">Status</label>
                        <select id="status" name="status" class="min-h-10 rounded-lg border-slate-300 bg-white text-sm text-slate-700 focus:border-blue-600 focus:ring-blue-600">
                            <option value="">All statuses</option>
                            @foreach(['pending', 'approved', 'rejected'] as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="min-h-10 rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">Apply filter</button>
                </form>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($items as $item)
                    <article class="p-5 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="text-base font-bold text-[#10294b]">{{ $item->schedule->subject?->subject_code ?? 'Class' }} <span class="font-medium text-slate-400">&middot;</span> {{ $item->schedule->section?->section_name ?? 'Section unavailable' }}</h3>
                                <p class="mt-1 text-sm text-slate-500">{{ $item->occurrence_date->format('M d, Y') }} <span class="mx-1 text-slate-300">&middot;</span> Requested by {{ $item->requester?->name ?? 'Unknown instructor' }}</p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ ['pending' => 'bg-amber-50 text-amber-700', 'approved' => 'bg-emerald-50 text-emerald-700', 'rejected' => 'bg-rose-50 text-rose-700'][$item->status] ?? 'bg-slate-100 text-slate-600' }}">{{ ucfirst($item->status) }}</span>
                        </div>
                        <div class="mt-4 rounded-xl bg-slate-50 px-4 py-3">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Reason for cancellation</p>
                            <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $item->reason }}</p>
                        </div>
                        @if($item->status === 'pending')
                            <form method="POST" action="{{ route('admin.class-cancellations.review', $item) }}" data-password-confirmation-required class="mt-4 flex flex-wrap items-end gap-3">
                                @csrf @method('PATCH')
                                <div class="min-w-[220px] flex-1">
                                    <label for="review-notes-{{ $item->id }}" class="mb-1.5 block text-sm font-semibold text-slate-700">Review notes <span class="font-normal text-slate-400">(optional)</span></label>
                                    <input id="review-notes-{{ $item->id }}" name="review_notes" maxlength="2000" placeholder="Add a note for the instructor" class="block min-h-10 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                </div>
                                <div class="flex gap-2">
                                    <button name="status" value="rejected" class="min-h-10 rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600">Reject</button>
                                    <button name="status" value="approved" class="min-h-10 rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">Approve</button>
                                </div>
                            </form>
                        @else
                            <div class="mt-4 text-sm text-slate-500">
                                <p>Reviewed by <span class="font-semibold text-slate-700">{{ $item->reviewer?->name ?? 'Unknown reviewer' }}</span></p>
                                @if($item->review_notes)<p class="mt-1 whitespace-pre-line">{{ $item->review_notes }}</p>@endif
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="px-6 py-14 text-center">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-heroicon-o-calendar-days class="h-6 w-6" /></span>
                        <h3 class="mt-4 text-sm font-bold text-slate-800">No requests found</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ request('status') ? 'Try another status to see more requests.' : 'Cancellation requests will appear here when submitted.' }}</p>
                    </div>
                @endforelse
            </div>
            @if($items->hasPages()) <div class="border-t border-slate-100 px-5 py-4 sm:px-6">{{ $items->links() }}</div> @endif
        </section>
    </div>
</x-app-layout>
