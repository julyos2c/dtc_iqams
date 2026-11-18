<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StudentAttendanceSummary
{
    public function __construct(
        private AttendanceSummaryCache $cache,
    ) {}

    /**
     * Build the student's attendance totals from canonical time-in records.
     * Excused and cancelled sessions are excluded from the denominator.
     *
     * @return array{present:int,late:int,absent:int,excused:int,attended:int,excluded:int,scheduled:int,percentage:float}
     */
    public function forStudent(Student $student): array
    {
        return $this->cache->rememberStudent((int) $student->user_id, function () use ($student): array {
            return $this->totals($this->ratedRows($student));
        });
    }

    /** Calculate dashboard values using the same rows on a summary cache miss. */
    public function dashboardForStudent(Student $student): array
    {
        $rows = null;
        $summary = $this->cache->rememberStudent((int) $student->user_id, function () use ($student, &$rows): array {
            $rows = $this->ratedRows($student);
            return $this->totals($rows);
        });

        return ['summary' => $summary, 'overview' => $this->overviewFromRows($rows ?? $this->ratedRows($student))];
    }

    /**
     * Return chart-ready attendance rates using the exact same rated-session
     * rules as the student summary. There is no academic-term date table, so
     * the semester view starts at the student's first recorded rated session.
     *
     * @return array{week: array<int, array{label: string, percentage: float}>, month: array<int, array{label: string, percentage: float}>, semester: array<int, array{label: string, percentage: float}>}
     */
    public function overviewForStudent(Student $student): array
    {
        return $this->overviewFromRows($this->ratedRows($student));
    }

    private function overviewFromRows(Collection $rows): array
    {
        $today = now(config('app.timezone'))->startOfDay();
        $firstRated = $rows->first(fn ($row) => $row->status !== 'excused' && $row->event_attendance_mode !== 'cancelled');
        $semesterStart = $firstRated
            ? Carbon::parse($firstRated->attendance_date, config('app.timezone'))->startOfWeek()
            : $today->copy()->startOfWeek();

        return [
            'week' => $this->periods($rows, $today->copy()->startOfWeek(), $today->copy()->endOfWeek(), 'day'),
            'month' => $this->periods($rows, $today->copy()->startOfMonth(), $today->copy()->endOfMonth(), 'week'),
            'semester' => $this->periods($rows, $semesterStart, $today->copy()->endOfWeek(), 'week', true),
        ];
    }

    private function summaryQuery(Student $student)
    {
        $cancelled = "COALESCE(school_events.attendance_mode, '') = 'cancelled'";
        $notExcluded = "attendance_logs.status <> 'excused' AND NOT ({$cancelled})";

        $query = DB::table('attendance_logs')
            ->leftJoin('schedules', 'schedules.id', '=', 'attendance_logs.schedule_id')
            ->leftJoin('school_events', 'school_events.id', '=', 'attendance_logs.school_event_id')
            ->where(function ($query): void {
                $query->where('attendance_logs.record_state', 'canonical')->orWhereNull('attendance_logs.record_state');
            })
            ->where('attendance_logs.user_id', $student->user_id)
            ->where('attendance_logs.attendance_type', 'time_in')
            ->where(function ($query): void {
                $query->whereNotNull('attendance_logs.schedule_id')->orWhereNotNull('attendance_logs.school_event_id');
            })
            ->selectRaw("\n                COALESCE(SUM(CASE WHEN {$notExcluded} AND attendance_logs.status = 'present' THEN 1 ELSE 0 END), 0) AS present,\n                COALESCE(SUM(CASE WHEN {$notExcluded} AND attendance_logs.status = 'late' THEN 1 ELSE 0 END), 0) AS late,\n                COALESCE(SUM(CASE WHEN {$notExcluded} AND attendance_logs.status = 'absent' THEN 1 ELSE 0 END), 0) AS absent,\n                COALESCE(SUM(CASE WHEN attendance_logs.status = 'excused' THEN 1 ELSE 0 END), 0) AS excused,\n                COALESCE(SUM(CASE WHEN {$cancelled} OR attendance_logs.status = 'excused' THEN 1 ELSE 0 END), 0) AS excluded\n            ");
        return ClassCancellationReportFilter::exclude($query);
    }

    /** @return Collection<int, object> */
    private function ratedRows(Student $student): Collection
    {
        $rows = $this->summaryQuery($student)
            ->select([
                'attendance_logs.status',
                'attendance_logs.attendance_date',
                'attendance_logs.schedule_id',
                'school_events.attendance_mode as event_attendance_mode',
            ])
            ->orderBy('attendance_logs.attendance_date')
            ->get();

        // Historical absences come from persisted records, never current membership.
        return $rows->sortBy('attendance_date')->values();
    }

    /** @param Collection<int, object> $rows @return array{present:int,late:int,absent:int,excused:int,attended:int,excluded:int,scheduled:int,percentage:float} */
    private function totals(Collection $rows): array
    {
        $present = $rows->where('status', 'present')->count(); $late = $rows->where('status', 'late')->count(); $absent = $rows->where('status', 'absent')->count();
        $excused = $rows->where('status', 'excused')->count(); $excluded = $rows->filter(fn ($row) => $row->status === 'excused' || $row->event_attendance_mode === 'cancelled')->count(); $attended = $present + $late; $scheduled = $attended + $absent;
        return compact('present', 'late', 'absent', 'excused', 'attended', 'excluded', 'scheduled') + ['percentage' => $scheduled > 0 ? round($attended / $scheduled * 100, 2) : 0.0];
    }

    /** @return array<int, array{label: string, percentage: float}> */
    private function periods(Collection $rows, Carbon $from, Carbon $to, string $unit, bool $numberWeeks = false): array
    {
        $filtered = $rows->filter(function ($row) use ($from, $to): bool {
            $date = Carbon::parse($row->attendance_date, config('app.timezone'))->startOfDay();
            return $date->betweenIncluded($from, $to);
        })->groupBy(function ($row) use ($unit): string {
            $date = Carbon::parse($row->attendance_date, config('app.timezone'));
            return $unit === 'day' ? $date->toDateString() : $date->startOfWeek()->toDateString();
        });

        return $filtered->map(function (Collection $logs, string $key) use ($unit, $from, $numberWeeks): array {
            $rated = $logs->filter(fn ($log) => $log->status !== 'excused' && $log->event_attendance_mode !== 'cancelled');
            $attended = $rated->whereIn('status', ['present', 'late'])->count();
            $scheduled = $rated->whereIn('status', ['present', 'late', 'absent'])->count();
            $date = Carbon::parse($key, config('app.timezone'));
            return [
                'label' => $unit === 'day' ? $date->format('D') : ($numberWeeks ? 'Week '.($from->diffInWeeks($date) + 1) : $date->format('M j')),
                'percentage' => $scheduled > 0 ? round($attended / $scheduled * 100, 2) : 0.0,
                'attended' => $attended,
                'scheduled' => $scheduled,
            ];
        })->values()->all();
    }
}
