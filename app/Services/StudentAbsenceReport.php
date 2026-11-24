<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class StudentAbsenceReport
{
    public function records(array $filters): Builder
    {
        $query = DB::table('attendance_logs as a')
            ->join('students as st', 'st.user_id', '=', 'a.user_id')
            ->join('schedules as sc', 'sc.id', '=', 'a.schedule_id')
            ->join('subjects as su', 'su.id', '=', 'sc.subject_id')
            ->leftJoin('courses as c', 'c.id', '=', 'st.course_id')
            ->leftJoin('sections as sec', 'sec.id', '=', 'st.section_id')
            ->leftJoin('instructors as i', 'i.id', '=', 'sc.instructor_id')
            ->leftJoin('school_events as e', 'e.id', '=', 'a.school_event_id')
            ->where(fn ($q) => $q->where('a.record_state', 'canonical')->orWhereNull('a.record_state'))
            ->where('a.attendance_type', 'time_in')->where('a.status', 'absent')
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('attendance_logs as duplicate')
                    ->whereColumn('duplicate.user_id', 'a.user_id')->whereColumn('duplicate.schedule_id', 'a.schedule_id')
                    ->whereColumn('duplicate.attendance_date', 'a.attendance_date')->whereColumn('duplicate.id', '<', 'a.id')
                    ->where('duplicate.attendance_type', 'time_in')->where('duplicate.status', 'absent')
                    ->where(fn ($q) => $q->where('duplicate.record_state', 'canonical')->orWhereNull('duplicate.record_state'));
            })
            ->where(fn ($q) => $q->whereNull('e.attendance_mode')->orWhere('e.attendance_mode', '<>', 'cancelled'));

        ClassCancellationReportFilter::exclude($query, 'a');
        foreach (['department' => 'c.department_id', 'course' => 'st.course_id', 'section' => 'st.section_id', 'subject' => 'su.id'] as $filter => $column) {
            if (! empty($filters[$filter])) $query->where($column, $filters[$filter]);
        }
        if (! empty($filters['from'])) $query->where('a.attendance_date', '>=', $filters['from']);
        if (! empty($filters['to'])) $query->where('a.attendance_date', '<=', $filters['to']);
        if ($search = trim($filters['search'] ?? '')) {
            foreach (preg_split('/\s+/', $search) as $term) {
                $query->where(function ($q) use ($term) {
                    $like = '%'.$term.'%';
                    $q->where('st.first_name', 'like', $like)->orWhere('st.middle_name', 'like', $like)
                        ->orWhere('st.last_name', 'like', $like)->orWhere('st.student_no', 'like', $like);
                });
            }
        }
        return $query;
    }

    public function subjectTotals(array $filters): Builder
    {
        return $this->records($filters)->selectRaw('st.id as student_id, su.id as subject_id, COUNT(DISTINCT a.id) as absence_count')
            ->groupBy('st.id', 'su.id')
            ->havingRaw('COUNT(DISTINCT a.id) >= ?', [StudentAbsenceWarningService::THRESHOLD]);
    }

    public function totals(array $filters): Builder
    {
        $query = DB::query()->fromSub($this->subjectTotals($filters), 'subject_totals')
            ->selectRaw('student_id, SUM(absence_count) as total_absences, MAX(absence_count) as max_subject_absences')
            ->groupBy('student_id');
        if (($filters['risk'] ?? '') === 'at-risk') $query->havingRaw('MAX(absence_count) >= ?', [StudentAbsenceWarningService::THRESHOLD]);
        if (($filters['risk'] ?? '') === 'normal') $query->havingRaw('MAX(absence_count) < ?', [StudentAbsenceWarningService::THRESHOLD]);
        return $query;
    }

    public function summary(array $filters): object
    {
        return DB::query()->fromSub($this->totals($filters), 'totals')
            ->selectRaw('COUNT(*) as affected, COALESCE(SUM(total_absences), 0) as absences, COALESCE(SUM(CASE WHEN max_subject_absences >= ? THEN 1 ELSE 0 END), 0) as at_risk', [StudentAbsenceWarningService::THRESHOLD])->first();
    }

    public function students(array $filters): Builder
    {
        return DB::query()->fromSub($this->subjectTotals($filters), 'totals')
            ->join('students as st', 'st.id', '=', 'totals.student_id')
            ->join('subjects as su', 'su.id', '=', 'totals.subject_id')
            ->join('users as u', 'u.id', '=', 'st.user_id')
            ->leftJoin('courses as c', 'c.id', '=', 'st.course_id')
            ->leftJoin('sections as sec', 'sec.id', '=', 'st.section_id')
            ->select('st.id', 'st.student_no', 'st.first_name', 'st.middle_name', 'st.last_name', 'st.name_suffix', 'u.avatar_path', 'c.course_code', 'sec.section_name', 'su.id as subject_id', 'su.subject_code', 'su.subject_name', 'totals.absence_count as total_absences', 'totals.absence_count as max_subject_absences')
            ->when(($filters['risk'] ?? '') === 'normal', fn ($q) => $q->whereRaw('1 = 0'))
            ->orderBy('st.last_name')->orderBy('st.first_name')->orderBy('st.id')->orderBy('su.subject_code');
    }

    public function details(array $filters, ?int $studentId = null): Builder
    {
        $query = $this->records($filters)->joinSub($this->subjectTotals($filters), 'eligible', function ($join) {
            $join->on('eligible.student_id', '=', 'st.id')->on('eligible.subject_id', '=', 'su.id');
        });
        if (($filters['risk'] ?? '') === 'normal') $query->whereRaw('1 = 0');
        if ($studentId !== null) $query->where('st.id', $studentId);
        return $query->select('st.id as student_id', 'st.student_no', 'st.first_name', 'st.middle_name', 'st.last_name', 'st.name_suffix', 'c.course_code', 'sec.section_name', 'sec.school_year', 'su.id as subject_id', 'su.subject_code', 'su.subject_name', 'i.first_name as instructor_first', 'i.last_name as instructor_last', 'a.attendance_date', 'sc.start_time', 'sc.end_time')
            ->orderBy('st.last_name')->orderBy('st.first_name')->orderBy('su.subject_code')->orderBy('a.attendance_date');
    }
}
