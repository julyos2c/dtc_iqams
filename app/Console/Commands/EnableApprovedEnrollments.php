<?php

namespace App\Console\Commands;

use App\Models\Schedule;
use App\Services\AttendanceSummaryCache;
use App\Services\AuditLogger;
use App\Services\EnrollmentWorkflow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnableApprovedEnrollments extends Command
{
    protected $signature = 'enrollment:enable {--apply : Backfill existing regular rosters and enable approval-only eligibility}';

    protected $description = 'Audit legacy rosters; apply only with enrollment and attendance writes paused';

    public function handle(): int
    {
        $invalidGroups = Schedule::active()->whereNull('recurring_schedule_group_id')->count();
        $orphans = DB::table('student_schedule_enrollments')->whereNotIn('recurring_schedule_group_id', DB::table('schedules')->whereNotNull('recurring_schedule_group_id')->select('recurring_schedule_group_id'))->count();
        $duplicates = DB::table('students')->selectRaw('UPPER(TRIM(student_no)) as identity_number')->groupByRaw('UPPER(TRIM(student_no))')->havingRaw('COUNT(*) > 1')->get()->count();
        $mixedGroups = Schedule::active()->select('recurring_schedule_group_id')->groupBy('recurring_schedule_group_id')->havingRaw('COUNT(DISTINCT subject_id) > 1 OR COUNT(DISTINCT section_id) > 1 OR COUNT(DISTINCT instructor_id) > 1')->get()->count();
        $pairs = DB::table('students')->join('schedules', 'students.section_id', '=', 'schedules.section_id')
            ->where('students.enrollment_type', 'regular')->whereIn('schedules.id', Schedule::active()->select('schedules.id'))
            ->select('students.id as student_id', 'schedules.recurring_schedule_group_id')->distinct()->get();
        $this->info('Legacy roster relationships: '.$pairs->count().'. Null groups: '.$invalidGroups.'. Orphaned enrollments: '.$orphans.'. Conflicting identities: '.$duplicates.'. Mixed offering groups: '.$mixedGroups.'.');
        if ($invalidGroups + $orphans + $duplicates + $mixedGroups > 0) {
            $this->error('Resolve the reported integrity issues before cutover. No changes applied.');

            return self::FAILURE;
        }
        if (! $this->option('apply')) {
            $this->info('Dry run only. Pause writes and back up the database before running with --apply.');

            return self::SUCCESS;
        }
        DB::transaction(function () use ($pairs): void {
            $state = DB::table('enrollment_workflow_states')->where('id', 1)->lockForUpdate()->first();
            if ($state->enabled_at) {
                $this->info('Already enabled; existing relationships were preserved.');

                return;
            }
            foreach ($pairs->chunk(500) as $chunk) {
                DB::table('student_schedule_enrollments')->insertOrIgnore($chunk->map(fn ($pair) => [
                    'student_id' => $pair->student_id, 'recurring_schedule_group_id' => $pair->recurring_schedule_group_id,
                    'origin' => 'legacy', 'effective_at' => null, 'created_at' => now(), 'updated_at' => now(),
                ])->all());
            }
            $missing = DB::table('students')->join('schedules', 'students.section_id', '=', 'schedules.section_id')
                ->leftJoin('student_schedule_enrollments as enrollment', fn ($join) => $join->on('enrollment.student_id', '=', 'students.id')->on('enrollment.recurring_schedule_group_id', '=', 'schedules.recurring_schedule_group_id'))
                ->where('students.enrollment_type', 'regular')->whereIn('schedules.id', Schedule::active()->select('schedules.id'))->whereNull('enrollment.id')->exists();
            if ($missing) {
                throw new \RuntimeException('Backfill verification failed; cutover rolled back.');
            }
            DB::table('enrollment_workflow_states')->where('id', 1)->update(['enabled_at' => now()]);
            app(AuditLogger::class)->record('enrollment.cutover_enabled', metadata: ['legacy_relationships' => $pairs->count()]);
            DB::afterCommit(fn () => app(AttendanceSummaryCache::class)->invalidateAll());
        });
        app(EnrollmentWorkflow::class)->refresh();
        $this->info('Approved enrollment eligibility is enabled.');

        return self::SUCCESS;
    }
}
