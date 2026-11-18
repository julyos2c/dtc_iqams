<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonInterface;

/** Resolves the class offerings a student may attend. */
class StudentScheduleEligibility
{
    public function schedulesFor(Student $student, ?CarbonInterface $at = null): Builder
    {
        $query = Schedule::query()->active();

        if (app(EnrollmentWorkflow::class)->enabled()) {
            return $query->whereExists(function ($query) use ($student, $at): void {
                $query->selectRaw('1')->from('student_schedule_enrollments')
                    ->whereColumn('student_schedule_enrollments.recurring_schedule_group_id', 'schedules.recurring_schedule_group_id')
                    ->where('student_id', $student->id)
                    ->where(fn ($query) => $query->whereNull('effective_at')->orWhere('effective_at', '<=', $at ?? now()));
            });
        }

        if ($student->enrollment_type === 'irregular') {
            return $query->whereIn('recurring_schedule_group_id', $student->scheduleEnrollments()
                ->select('recurring_schedule_group_id'));
        }

        return $query->where('section_id', $student->section_id);
    }

    public function isEligibleForSchedule(Student $student, Schedule $schedule, ?CarbonInterface $at = null): bool
    {
        if (app(EnrollmentWorkflow::class)->enabled()) {
            return $this->schedulesFor($student, $at)->whereKey($schedule->id)->exists();
        }
        if ($student->enrollment_type !== 'irregular') {
            return (int) $student->section_id === (int) $schedule->section_id;
        }

        return $this->schedulesFor($student)->whereKey($schedule->id)->exists();
    }

    public function isEligibleForSection(Student $student, int $sectionId, ?CarbonInterface $at = null): bool
    {
        if (app(EnrollmentWorkflow::class)->enabled()) {
            return $this->schedulesFor($student, $at)->where('section_id', $sectionId)->exists();
        }
        if ($student->enrollment_type !== 'irregular') {
            return (int) $student->section_id === $sectionId;
        }

        return $this->schedulesFor($student)->where('section_id', $sectionId)->exists();
    }

    /** Active students eligible for a schedule, without duplicate rows. */
    public function studentsForSchedule(Schedule $schedule, ?CarbonInterface $at = null): Builder
    {
        if (app(EnrollmentWorkflow::class)->enabled()) {
            return Student::query()->where('students.status', 'active')
                ->whereHas('user', fn ($query) => $query->where('status', 'active'))
                ->whereHas('scheduleEnrollments', fn ($query) => $query
                    ->where('recurring_schedule_group_id', $schedule->recurring_schedule_group_id)
                    ->where(fn ($query) => $query->whereNull('effective_at')->orWhere('effective_at', '<=', $at ?? now())));
        }
        return Student::query()
            ->where('students.status', 'active')
            ->whereHas('user', fn (Builder $query) => $query->where('status', 'active'))
            ->where(function (Builder $query) use ($schedule): void {
                $query->where(function (Builder $regular) use ($schedule): void {
                    $regular->where('students.enrollment_type', 'regular')
                        ->where('students.section_id', $schedule->section_id);
                })->orWhere(function (Builder $irregular) use ($schedule): void {
                    $irregular->where('students.enrollment_type', 'irregular')
                        ->whereHas('scheduleEnrollments', fn (Builder $enrollments) => $enrollments
                            ->where('recurring_schedule_group_id', $schedule->recurring_schedule_group_id));
                });
            });
    }

    /** @param array<int, int> $sectionIds @param array<int, string> $groupIds */
    public function studentsForTargets(array $sectionIds, array $groupIds, ?CarbonInterface $at = null): Builder
    {
        if (app(EnrollmentWorkflow::class)->enabled()) {
            return Student::query()->where('students.status', 'active')
                ->whereHas('user', fn ($query) => $query->where('status', 'active'))
                ->whereHas('scheduleEnrollments', fn ($query) => $query->whereIn('recurring_schedule_group_id', $groupIds)
                    ->where(fn ($query) => $query->whereNull('effective_at')->orWhere('effective_at', '<=', $at ?? now())));
        }
        return Student::query()
            ->where('students.status', 'active')
            ->whereHas('user', fn (Builder $query) => $query->where('status', 'active'))
            ->where(function (Builder $query) use ($sectionIds, $groupIds): void {
                $query->where(function (Builder $regular) use ($sectionIds): void {
                    $regular->where('students.enrollment_type', 'regular')->whereIn('students.section_id', $sectionIds);
                })->orWhere(function (Builder $irregular) use ($sectionIds, $groupIds): void {
                    $irregular->where('students.enrollment_type', 'irregular')
                        ->whereHas('scheduleEnrollments', fn (Builder $enrollments) => $enrollments->whereIn('recurring_schedule_group_id', $groupIds));
                });
            });
    }

    /** @param array<int, string> $groupIds */
    public function sync(Student $student, array $groupIds): void
    {
        abort_if(app(EnrollmentWorkflow::class)->enabled(), 403, 'Subject enrollments require an approved instructor request.');
        $groupIds = $student->enrollment_type === 'irregular' ? array_unique($groupIds) : [];
        $student->scheduleEnrollments()->whereNotIn('recurring_schedule_group_id', $groupIds)->delete();
        foreach ($groupIds as $groupId) {
            $student->scheduleEnrollments()->firstOrCreate(['recurring_schedule_group_id' => $groupId]);
        }

        app(AttendanceSummaryCache::class)->invalidateStudentContext();
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => app(AttendanceSummaryCache::class)->invalidateStudentContext());
        }
    }

    /** @return Collection<int, string> */
    public function groupIdsFor(Student $student): Collection
    {
        return $student->scheduleEnrollments()->pluck('recurring_schedule_group_id');
    }
}
