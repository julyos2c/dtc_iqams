<?php

namespace App\Services;

use App\Models\SchoolEvent;
use App\Models\Schedule;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SchoolEventAttendanceService
{
    public function __construct(
        private AttendanceAbsenceWriter $writer,
        private StudentScheduleEligibility $eligibility,
    ) {}

    public function markDue(?Carbon $at = null): int
    {
        $at = ($at ?? now())->copy()->timezone(config('app.timezone'));
        $created = 0;

        SchoolEvent::with('targets.schedule')
            ->where('status', 'published')
            ->where('attendance_mode', 'event_attendance')
            ->where('ends_at', '<', $at)
            ->whereNull('attendance_finalized_at')
            ->each(function (SchoolEvent $event) use ($at, &$created): void {
                $created += DB::transaction(function () use ($event, $at): int {
                    $locked = SchoolEvent::query()->with('targets.schedule')->lockForUpdate()->find($event->id);

                    if (! $locked || $locked->attendance_finalized_at || $locked->ends_at->gte($at)) {
                        return 0;
                    }

                    $created = $this->writer->forEvent($locked, $this->studentsFor($locked));
                    $locked->update(['attendance_finalized_at' => $at]);

                    return $created;
                });
            });

        return $created;
    }

    public function studentsFor(SchoolEvent $event)
    {
        if ($event->target_scope === 'school') {
            return Student::query()->where('students.status', 'active')
                ->whereHas('user', fn ($q) => $q->where('status', 'active'))
                ->when(app(EnrollmentWorkflow::class)->enabled(), fn ($query) => $query->where('students.created_at', '<=', $event->starts_at));
        }

        if ($event->target_scope === 'schedules') {
            $scheduleIds = $event->targets->pluck('schedule_id')->filter()->all();

            return Student::query()->where('students.status', 'active')
                ->whereHas('user', fn ($query) => $query->where('status', 'active'))
                ->where(function ($query) use ($scheduleIds, $event): void {
                    foreach (Schedule::whereIn('id', $scheduleIds)->get() as $schedule) {
                        $query->orWhereIn('students.id', $this->eligibility->studentsForSchedule($schedule, $event->starts_at)->select('students.id'));
                    }
                    // An empty target list must never select every student.
                    $query->orWhereRaw('1 = 0');
                });
        }

        $sections = $event->targets->pluck('section_id')->filter()->map(fn ($id) => (int) $id)->all();
        $groups = Schedule::active()->whereIn('section_id', $sections)->pluck('recurring_schedule_group_id')->all();

        return $this->eligibility->studentsForTargets($sections, $groups, $event->starts_at);
    }
}
