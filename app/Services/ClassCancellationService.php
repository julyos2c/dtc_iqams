<?php

namespace App\Services;

use App\Models\ClassCancellationRequest;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\ClassCancellationNotification;
use App\ValueObjects\ScheduleOccurrence;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class ClassCancellationService
{
    public function status(ScheduleOccurrence $occurrence): ?string
    {
        return ClassCancellationRequest::where('schedule_id', $occurrence->schedule->id)
            ->whereDate('occurrence_date', $occurrence->sessionDate->toDateString())
            ->whereIn('status', ['pending', 'approved'])->value('status');
    }

    public function submit(Schedule $schedule, Carbon $date, string $reason, User $actor): ClassCancellationRequest
    {
        abort_unless($actor->instructor && $schedule->instructor_id === $actor->instructor->id, 403);
        $occurrence = app(ScheduleOccurrenceResolver::class)->forDate($schedule, $date);
        if (! $occurrence || ! Schedule::active()->whereKey($schedule->id)->exists()) {
            throw ValidationException::withMessages(['occurrence_date' => 'Choose a valid active class date.']);
        }
        try {
            return DB::transaction(function () use ($schedule, $date, $reason, $actor, $occurrence) {
                Schedule::whereKey($schedule->id)->lockForUpdate()->firstOrFail();
                $item = ClassCancellationRequest::create(['schedule_id' => $schedule->id, 'occurrence_date' => $date->toDateString(), 'opens_at' => $occurrence->opensAt, 'ends_at' => $occurrence->endsAt, 'requester_id' => $actor->id, 'reason' => $reason, 'status' => 'pending', 'active_key' => $schedule->id.':'.$date->toDateString()]);
                app(AttendanceSummaryCache::class)->forgetAdminAnalytics();
                app(AuditLogger::class)->record('class_cancellation.submitted', $item, [], $actor);
                DB::afterCommit(function () use ($item): void {
                    app(AttendanceSummaryCache::class)->forgetAdminAnalytics();
                    Notification::send(User::whereHas('roles', fn ($q) => $q->where('name', 'admin')->where('guard_name', 'web'))->get(), new ClassCancellationNotification($item));
                });
                return $item;
            });
        } catch (QueryException $e) {
            if (in_array((string) $e->getCode(), ['23000', '23505'], true)) throw ValidationException::withMessages(['occurrence_date' => 'An active cancellation request already exists for this class.']);
            throw $e;
        }
    }

    public function review(ClassCancellationRequest $target, string $status, ?string $notes, User $actor): void
    {
        DB::transaction(function () use ($target, $status, $notes, $actor) {
            $schedule = Schedule::whereKey($target->schedule_id)->lockForUpdate()->firstOrFail();
            $item = ClassCancellationRequest::whereKey($target->id)->lockForUpdate()->firstOrFail();
            abort_unless($item->status === 'pending', 422);
            $occurrence = app(ScheduleOccurrenceResolver::class)->forDate($schedule, Carbon::parse($item->occurrence_date->toDateString(), config('app.timezone')));
            abort_unless($occurrence, 422, 'The schedule no longer matches this date.');
            $item->update(['status' => $status, 'active_key' => $status === 'rejected' ? null : $item->active_key, 'reviewer_id' => $actor->id, 'review_notes' => $notes, 'reviewed_at' => now()]);
            if ($status === 'rejected') app(StudentAbsenceService::class)->markOccurrenceDue($occurrence, now());
            app(AuditLogger::class)->record('class_cancellation.reviewed', $item, ['status' => $status], $actor);
            DB::afterCommit(function () use ($item) {
                app(AttendanceSummaryCache::class)->invalidateStudentContext();
                $item->requester->notify(new ClassCancellationNotification($item));
            });
        }, 3);
    }
}
