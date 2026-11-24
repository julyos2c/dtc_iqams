@if (Auth::user()->isAdmin() || Auth::user()->isStaff() || Auth::user()->isInstructor() || Auth::user()->isStudent())
    @php
        $types = [\App\Notifications\LeaveRequestNotification::class, \App\Notifications\ClassCancellationNotification::class, \App\Notifications\StudentExcuseNotification::class, \App\Notifications\EnrollmentApprovedNotification::class];
        $key = 'iqams.notifications.' . Auth::id();
        $data = request()->attributes->get($key);
        if ($data === null) {
            $notifications = Auth::user()->notifications()->whereIn('type', $types)->latest()->take(8)->get();
            $leaveStatuses = \App\Models\LeaveRequest::query()->whereKey($notifications->where('type', \App\Notifications\LeaveRequestNotification::class)->pluck('data.leave_request_id')->filter()->unique())->pluck('status', 'id')->all();
            $cancellationStatuses = \App\Models\ClassCancellationRequest::query()->whereKey($notifications->where('type', \App\Notifications\ClassCancellationNotification::class)->pluck('data.class_cancellation_request_id')->filter()->unique())->pluck('status', 'id')->all();
            $unread = Auth::user()->unreadNotifications()->whereIn('type', Auth::user()->isAdmin() ? array_slice($types, 1) : $types)->count();
            if (Auth::user()->isAdmin()) {
                $unread += \App\Models\LeaveRequest::query()->where('status', 'pending')->count();
            }
            $data = [$notifications, $leaveStatuses, $cancellationStatuses, $unread];
            request()->attributes->set($key, $data);
        }
        [$notifications, $leaveStatuses, $cancellationStatuses, $unread] = $data;
    @endphp
    <div {{ $attributes->class('relative') }} x-data="{ open: false, unread: {{ $unread }} }" @keydown.escape.window="open=false">
        <button type="button" @click="open = !open" class="relative rounded-lg border border-slate-200 bg-white p-2.5 text-slate-500 transition hover:bg-slate-50 hover:text-slate-700" aria-label="Notifications" :aria-expanded="open">
            <x-heroicon-o-bell class="h-5 w-5" aria-hidden="true" />
            <span x-show="unread > 0" x-cloak x-text="unread > 99 ? '99+' : unread" class="absolute -right-2 -top-2 grid min-h-5 min-w-5 place-items-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white ring-2 ring-white"></span>
        </button>
        <div x-show="open" x-cloak x-transition.origin.top.right @click.outside="open=false" class="fixed inset-x-3 top-24 z-[80] overflow-hidden rounded-xl border border-slate-200 bg-white text-left shadow-xl sm:absolute sm:inset-x-auto sm:right-0 sm:top-auto sm:mt-2 sm:w-96">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                <div><p class="text-sm font-semibold text-slate-900">Notifications</p><p class="text-xs text-slate-500">Recent request activity</p></div>
                @if($notifications->isNotEmpty())
                    <form method="POST" action="{{ route('notifications.clear') }}">
                        @csrf
                        <button type="submit" class="text-xs font-semibold text-blue-600 hover:text-blue-800">Clear all</button>
                    </form>
                @endif
            </div>
            <div class="max-h-[calc(100dvh-7rem)] overflow-y-auto divide-y divide-slate-100 sm:max-h-96">
                @forelse($notifications as $notification)
                    @php
                        $item = $notification->data;
                        $event = $item['event'] ?? 'updated';
                        $isLeave = $notification->type === \App\Notifications\LeaveRequestNotification::class;
                        $isCancellation = $notification->type === \App\Notifications\ClassCancellationNotification::class;
                        $isEnrollment = $notification->type === \App\Notifications\EnrollmentApprovedNotification::class;
                        $status = $isLeave ? ($leaveStatuses[$item['leave_request_id'] ?? null] ?? ($item['status'] ?? 'pending')) : ($isCancellation ? ($cancellationStatuses[$item['class_cancellation_request_id'] ?? null] ?? ($item['status'] ?? null)) : ($item['status'] ?? null));
                        $statusClass = match ($status) { 'approved' => 'bg-emerald-100 text-emerald-700', 'rejected' => 'bg-red-100 text-red-700', 'cancelled' => 'bg-slate-100 text-slate-600', default => 'bg-amber-100 text-amber-700' };
                        $message = $isEnrollment ? (($item['approved_count'] ?? 1) . ' student enrollment ' . (($item['approved_count'] ?? 1) === 1 ? 'request was' : 'requests were') . ' approved.') : ($isLeave ? match ($event) {
                            'submitted' => Auth::user()->isAdmin() ? ($item['requester_name'] ?? 'Someone') . ' submitted a leave request.' : 'Your leave request was submitted.',
                            'approved' => 'Your leave request was approved.', 'rejected' => 'Your leave request was declined.',
                            'cancelled' => Auth::user()->isAdmin() ? ($item['requester_name'] ?? 'Someone') . ' cancelled a leave request.' : 'Your leave request was cancelled.',
                            default => 'A leave request was updated.',
                        } : ($isCancellation ? (Auth::user()->isAdmin() ? 'A class cancellation request was submitted.' : 'Your class cancellation request was ' . ($status ?? 'updated') . '.') : ($event === 'submitted' ? 'A student submitted an excuse request.' : 'Your excuse request was ' . $event . '.')));
                    @endphp
                    <form method="POST" action="{{ route('notifications.open', $notification->id) }}">
                        @csrf
                        <button type="submit" class="block w-full px-4 py-3 text-left transition hover:bg-slate-50 {{ $notification->read_at ? '' : 'bg-blue-50/60' }}">
                            <div class="flex items-start justify-between gap-3"><p class="text-sm font-medium leading-5 text-slate-800">{{ $message }}</p>@if($status)<span class="shrink-0 rounded-md px-2 py-0.5 text-[10px] font-semibold capitalize {{ $statusClass }}">{{ $status }}</span>@endif</div>
                            @if($isEnrollment)
                                <p class="mt-1 text-xs text-slate-500">{{ $item['subject'] ?? '' }}</p>
                            @elseif($isLeave && !empty($item['start_date']) && !empty($item['end_date']))
                                <p class="mt-1 text-xs text-slate-500">{{ $item['leave_type'] ?? 'Leave' }} · {{ \Illuminate\Support\Carbon::parse($item['start_date'])->format('M j') }}–{{ \Illuminate\Support\Carbon::parse($item['end_date'])->format('M j, Y') }}</p>
                            @elseif($isCancellation && !empty($item['occurrence_date']))
                                <p class="mt-1 text-xs text-slate-500">{{ \Illuminate\Support\Carbon::parse($item['occurrence_date'])->format('M j, Y') }}</p>
                            @endif
                            @if (!empty($item['review_notes']))<p class="mt-1 truncate text-xs text-slate-500">Note: {{ $item['review_notes'] }}</p>@endif
                            <p class="mt-1 text-[11px] text-slate-400">{{ $notification->created_at->diffForHumans() }}</p>
                        </button>
                    </form>
                @empty
                    <div class="px-5 py-10 text-center"><x-heroicon-o-bell class="mx-auto h-8 w-8 text-slate-300" /><p class="mt-2 text-sm text-slate-500">No notifications yet.</p></div>
                @endforelse
            </div>
        </div>
    </div>
@endif
