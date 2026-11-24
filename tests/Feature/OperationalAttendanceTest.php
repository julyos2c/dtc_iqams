<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\LeaveRequest;
use App\Models\ScannerTerminal;
use App\Models\SchoolEvent;
use App\Models\User;
use App\Services\ClassCancellationService;
use App\Services\PersonnelAttendanceSummary;
use App\Services\QrAttendanceService;
use App\Services\QrCredentialService;
use App\Services\ScannerCoverage;
use App\Services\ScannerRehearsalReceipts;
use App\Services\SchoolEventAttendanceService;
use App\Services\StudentAbsenceReport;
use App\Services\StudentAbsenceService;
use App\Services\StudentAbsenceWarningService;
use App\Services\StudentAttendanceSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ScannerAttendanceFixtures;
use Tests\TestCase;

class OperationalAttendanceTest extends TestCase
{
    use RefreshDatabase, ScannerAttendanceFixtures;

    private string $lease;

    private string $clock;

    private int $anchor;

    protected function setUp(): void
    {
        parent::setUp();
        config(['attendance.operational_sync' => true, 'app.timezone' => 'Asia/Manila']);
        Carbon::setTestNow(Carbon::parse('2026-08-07 06:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function start(string $at = '2026-08-10 07:30'): void
    {
        Carbon::setTestNow(Carbon::parse($at));
        $operator = User::factory()->create();
        $terminal = ScannerTerminal::create(['name' => 'Test', 'location' => 'Fixture']);
        $device = DB::table('scanner_devices')->insertGetId(['terminal_id' => $terminal->id, 'paired_by' => $operator->id, 'fingerprint' => hash('sha256', Str::random()), 'public_key' => 'fixture', 'paired_at' => now()]);
        $this->lease = (string) Str::uuid();
        $this->clock = (string) Str::uuid();
        $this->anchor = now()->getTimestampMs();
        DB::transaction(function () use ($operator, $device) {
            $end = now()->addHours(8)->getTimestampMs();
            DB::table('scanner_offline_sessions')->insert(['id' => $this->lease, 'device_id' => $device, 'operator_id' => $operator->id,
                'anchor_ms' => $this->anchor, 'expires_ms' => $end, 'clock_id' => $this->clock]);
            app(ScannerCoverage::class)->initialize($this->lease, $this->anchor, $end);
        });
    }

    private function event(string $at, string $code = 'STU-001', int $seq = 1): array
    {
        $time = Carbon::parse($at);

        return ['event_uuid' => (string) Str::uuid(), 'sequence' => $seq, 'qr_code' => $code, 'captured_at' => $time->copy()->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'clock_id' => $this->clock, 'elapsed_ms' => $time->getTimestampMs() - $this->anchor];
    }

    private function upload(array ...$events): array
    {
        return DB::transaction(fn () => app(ScannerRehearsalReceipts::class)->batch(
            DB::table('scanner_offline_sessions')->where('id', $this->lease)->lockForUpdate()->firstOrFail(), $events));
    }

    private function checkpoint(int $sequence): bool
    {
        return DB::transaction(fn () => app(ScannerCoverage::class)->checkpoint(
            DB::table('scanner_offline_sessions')->where('id', $this->lease)->lockForUpdate()->firstOrFail(),
            $sequence, now()->getTimestampMs())['verified']);
    }

    public static function boundaries(): array
    {
        return [['07:44:59', null], ['07:45:00', 'present'], ['08:15:59', 'present'], ['08:16:00', 'late']];
    }

    #[DataProvider('boundaries')]
    public function test_delayed_class_boundaries_and_retries(string $time, ?string $expected): void
    {
        $this->studentWithSchedule();
        $this->start();
        Carbon::setTestNow('2026-08-10 09:30');
        $event = $this->event('2026-08-10 '.$time);
        $result = $this->upload($event)[0];
        $this->assertSame($expected ? 'confirmed' : 'rejected', $result['status']);
        $this->assertSame($result, $this->upload($event)[0]);
        $this->assertSame($expected, AttendanceLog::value('status'));
        $this->assertDatabaseCount('scanner_attendance_receipts', 1);
        $this->assertDatabaseCount('attendance_logs', $expected ? 1 : 0);
        if ($expected) {
            $this->assertSame('duplicate', $this->upload($this->event('2026-08-10 '.$time, 'STU-001', 2))[0]['status']);
        }
    }

    public static function correctionTimes(): array
    {
        return [['11:05', 'present'], ['11:25', 'late']];
    }

    #[DataProvider('correctionTimes')]
    public function test_system_absence_correction_and_audit(string $time, string $status): void
    {
        [$user,$schedule] = $this->studentWithSchedule();
        $schedule->update(['start_time' => '11:00', 'end_time' => '12:00']);
        app(StudentAbsenceService::class)->markDue(Carbon::parse('2026-08-10 11:20'));
        $id = AttendanceLog::sole()->id;
        $this->start('2026-08-10 10:55');
        Carbon::setTestNow('2026-08-10 11:30');
        $this->assertSame('confirmed', $this->upload($this->event('2026-08-10 '.$time))[0]['status']);
        $this->assertDatabaseHas('attendance_logs', ['id' => $id, 'user_id' => $user->id, 'status' => $status, 'scan_time' => "2026-08-10 $time:00"]);
        $row = DB::table('scanner_attendance_receipts')->first();
        $this->assertSame('absent', json_decode($row->before_record, true)['status']);
        $this->assertSame($status, json_decode($row->after_record, true)['status']);
        $this->assertNotNull($row->audit_log_id);
    }

    public static function changes(): array
    {
        return array_map(fn ($v) => [$v], ['section', 'enrollment', 'schedule', 'account', 'credential', 'config']);
    }

    #[DataProvider('changes')]
    public function test_changed_historical_inputs_require_review(string $change): void
    {
        [$user,$schedule] = $this->studentWithSchedule();
        $card = app(QrCredentialService::class)->issue($user, $user);
        $qr = app(QrCredentialService::class)->plainText($card);
        $group = $schedule->recurring_schedule_group_id;
        $this->start();
        Carbon::setTestNow('2026-08-10 09:00');
        match ($change) {
            'section' => $user->student->update(['section_id' => null]),
            'enrollment' => DB::table('student_schedule_enrollments')->insert(['student_id' => $user->student->id, 'recurring_schedule_group_id' => $group ?? (string) Str::uuid()]),
            'schedule' => $schedule->update(['start_time' => '09:30']),
            'account' => $user->update(['status' => 'inactive']),
            'credential' => app(QrCredentialService::class)->regenerate($user, $user),
            'config' => config(['attendance.present_grace_minutes' => 25]),
        };
        $this->assertSame('review_required', $this->upload($this->event('2026-08-10 08:05', $qr))[0]['status']);
        $this->assertSame($group, $schedule->fresh()->recurring_schedule_group_id);
        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_overnight_and_manual_correction_protection(): void
    {
        [$user,$schedule] = $this->studentWithSchedule();
        $schedule->update(['start_time' => '22:00', 'end_time' => '01:00']);
        $this->start('2026-08-10 21:00');
        Carbon::setTestNow('2026-08-11 00:50');
        $this->assertSame('confirmed', $this->upload($this->event('2026-08-11 00:10'))[0]['status']);
        $log = AttendanceLog::sole();
        $log->update(['record_origin' => 'admin', 'status' => 'excused']);
        $this->assertSame('review_required', $this->upload($this->event('2026-08-11 00:11', 'STU-001', 2))[0]['status']);
        $this->assertSame('excused', $log->fresh()->status);
    }

    public function test_gap_heartbeat_and_partial_batch_cannot_release_held_absences(): void
    {
        $this->studentWithSchedule();
        $this->start();
        Carbon::setTestNow('2026-08-10 08:16');
        $this->assertSame(0, app(StudentAbsenceService::class)->markDue());
        app(ScannerCoverage::class)->reconcile();
        $this->assertDatabaseHas('scanner_interruptions', ['from_ms' => $this->anchor, 'state' => 'suspected']);
        DB::table('scanner_terminals')->update(['last_heartbeat_at' => now(), 'reported_pending' => 0]);
        $this->assertFalse($this->checkpoint(2));
        $results = $this->upload($this->event('2026-08-10 08:05', 'STU-001', 2), []);
        $this->assertSame('sequence_gap', $results[0]['reason']);
        $this->assertNull($results[1]['receipt_id']);
        $this->assertFalse($this->checkpoint(2));
        Carbon::setTestNow('2026-08-11 09:00');
        app(ScannerCoverage::class)->reconcile();
        $this->assertDatabaseHas('scanner_absence_obligations', ['kind' => 'class', 'state' => 'escalated']);
        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_verified_coverage_releases_only_due_obligations_and_refreshes_reports(): void
    {
        [$user,$schedule] = $this->studentWithSchedule();
        $this->start();
        Carbon::setTestNow('2026-08-10 10:01');
        $this->assertSame(0, app(StudentAbsenceService::class)->markDue());
        $this->assertSame(0, app(StudentAbsenceReport::class)->records([])->count());
        $this->assertTrue($this->checkpoint(0));
        app(ScannerCoverage::class)->reconcile();
        $this->assertDatabaseHas('attendance_logs', ['schedule_id' => $schedule->id, 'status' => 'absent']);
        $this->assertSame(1, app(StudentAbsenceReport::class)->records([])->count());
        app(ScannerCoverage::class)->reconcile();
        $this->assertDatabaseCount('attendance_logs', 1);
    }

    public function test_timestamp_validated_rejection_holds_only_overlapping_occurrences(): void
    {
        [, $schedule] = $this->studentWithSchedule();
        $this->start();
        Carbon::setTestNow('2026-08-10 13:00');
        $this->assertSame('rejected', $this->upload($this->event('2026-08-10 12:00', 'UNASSIGNED'))[0]['status']);
        $this->assertFalse($this->checkpoint(1));
        app(ScannerCoverage::class)->reconcile();
        $this->assertDatabaseHas('attendance_logs', ['schedule_id' => $schedule->id, 'status' => 'absent']);
        $this->assertDatabaseHas('scanner_absence_obligations', ['kind' => 'personnel', 'target' => 'staff/lunch_out', 'state' => 'provisional']);
    }

    public function test_escalation_skips_weekends_and_school_wide_closures(): void
    {
        SchoolEvent::create(['title'=>'Local closure','status'=>'published','target_scope'=>'school',
            'attendance_mode'=>'cancelled','starts_at'=>'2026-08-10 00:00','ends_at'=>'2026-08-10 23:59']);
        $this->start('2026-08-07 07:30');
        Carbon::setTestNow('2026-08-10 12:00');
        app(ScannerCoverage::class)->reconcile();
        $this->assertDatabaseHas('scanner_interruptions',['state'=>'suspected','escalate_at'=>'2026-08-11 07:30:00']);
        Carbon::setTestNow('2026-08-11 07:31');
        app(ScannerCoverage::class)->reconcile();
        $this->assertDatabaseHas('scanner_interruptions',['state'=>'escalated']);
    }

    public function test_declared_policy_revision_revokes_historical_certainty(): void
    {
        $this->studentWithSchedule();
        $this->start();
        Carbon::setTestNow('2026-08-10 08:10');
        $this->artisan('scanner:invalidate-attendance-policy')->assertSuccessful();
        $this->assertSame('eligibility_changed',$this->upload($this->event('2026-08-10 08:05'))[0]['reason']);
    }

    public function test_personnel_windows_order_and_later_scan_conflict(): void
    {
        $person = $this->createPersonnel('staff', 'EMP-1');
        $this->start('2026-08-10 06:30');
        Carbon::setTestNow('2026-08-10 13:15');
        $results = $this->upload($this->event('2026-08-10 12:30', 'EMP-1', 3), $this->event('2026-08-10 07:30', 'EMP-1'), $this->event('2026-08-10 12:00', 'EMP-1', 2));
        $this->assertSame(['confirmed', 'confirmed', 'confirmed'], array_column($results, 'status'));
        $this->assertSame(['morning_in', 'lunch_out', 'afternoon_in'], AttendanceLog::orderBy('scan_time')->pluck('attendance_period')->all());
        app(QrAttendanceService::class)->record('EMP-1', null, Carbon::parse('2026-08-10 17:30'));
        $this->assertSame('later_personnel_scan', $this->upload($this->event('2026-08-10 13:00', 'EMP-1', 4))[0]['reason']);
    }

    public function test_school_event_precedence_and_absence_finalization(): void
    {
        $this->studentWithSchedule();
        $event = SchoolEvent::create(['title' => 'Assembly', 'status' => 'published', 'target_scope' => 'school', 'attendance_mode' => 'event_attendance', 'starts_at' => '2026-08-10 08:00', 'ends_at' => '2026-08-10 09:00']);
        $this->start();
        Carbon::setTestNow('2026-08-10 10:05');
        $this->assertSame(0, app(SchoolEventAttendanceService::class)->markDue());
        $this->assertNull($event->fresh()->attendance_finalized_at);
        $this->assertSame('confirmed', $this->upload($this->event('2026-08-10 08:05'))[0]['status']);
        $this->assertSame($event->id, AttendanceLog::sole()->school_event_id);
        $this->assertTrue($this->checkpoint(1));
        app(ScannerCoverage::class)->reconcile();
        $this->assertNotNull($event->fresh()->attendance_finalized_at);
        $this->assertDatabaseHas('attendance_logs', ['status' => 'excused']);
    }

    public function test_stored_provisional_absence_suppresses_risk_and_invalidates_cached_summary(): void
    {
        [$user] = $this->studentWithSchedule();
        $absence = app(StudentAbsenceService::class);
        foreach (['07-13', '07-20', '07-27', '08-03', '08-10'] as $day) {
            $absence->markDue(Carbon::parse("2026-$day 08:30"));
        }
        $student = $user->student;
        $summaries = app(StudentAttendanceSummary::class);
        $warnings = app(StudentAbsenceWarningService::class);
        $this->assertSame(5, $summaries->forStudent($student)['absent']);
        $this->assertCount(1, $warnings->forStudent($student));
        $this->start();
        $this->assertSame(4, $summaries->forStudent($student)['absent']);
        $this->assertCount(0, $warnings->forStudent($student));
        $report = app(StudentAbsenceReport::class)->summary(['from' => '2026-08-10']);
        $this->assertEquals(0, $report->affected);
        $this->assertEquals(0, $report->at_risk);
        Carbon::setTestNow('2026-08-10 10:05');
        $this->assertTrue($this->checkpoint(0));
        app(ScannerCoverage::class)->reconcile();
        $this->assertSame(5, $summaries->forStudent($student)['absent']);
        $this->assertCount(1, $warnings->forStudent($student));
    }

    public static function cancellationDecisions(): array
    {
        return [['pending'], ['approved'], ['rejected']];
    }

    #[DataProvider('cancellationDecisions')]
    public function test_cancellation_scan_and_rejection_absence_paths(string $decision): void
    {
        [$student,$schedule] = $this->studentWithSchedule();
        $teacher = $schedule->instructor->user;
        $service = app(ClassCancellationService::class);
        $request = $service->submit($schedule, Carbon::parse('2026-08-10'), 'Test cancellation', $teacher);
        if ($decision === 'approved') {
            $service->review($request, 'approved', null, $teacher);
        }
        $this->start();
        Carbon::setTestNow('2026-08-10 09:30');
        if ($decision === 'rejected') {
            $service->review($request, 'rejected', null, $teacher);
            $this->assertDatabaseCount('attendance_logs', 0);
        }
        $this->assertSame($decision === 'approved' ? 'rejected' : 'confirmed', $this->upload($this->event('2026-08-10 08:05'))[0]['status']);
    }

    public function test_instructor_leave_rejects_personnel_and_keeps_student_class_open(): void
    {
        [$student,$schedule] = $this->studentWithSchedule();
        $this->start();
        LeaveRequest::create(['user_id' => $schedule->instructor->user_id, 'leave_type' => 'vacation',
            'start_date' => '2026-08-10', 'end_date' => '2026-08-10', 'status' => 'approved', 'reason' => 'Fixture']);
        Carbon::setTestNow('2026-08-10 09:00');
        $result = $this->upload($this->event('2026-08-10 08:02'), $this->event('2026-08-10 08:05', 'INS-001', 2));
        $this->assertSame(['confirmed', 'review_required'], array_column($result, 'status'));
    }

    public function test_approved_leave_and_weekend_policy_and_inferred_absence(): void
    {
        $staff = $this->createPersonnel('staff', 'EMP-2');
        LeaveRequest::create(['user_id' => $staff->id, 'leave_type' => 'vacation', 'start_date' => '2026-08-10',
            'end_date' => '2026-08-10', 'status' => 'approved', 'reason' => 'Fixture']);
        $this->start('2026-08-08 06:30');
        Carbon::setTestNow('2026-08-08 08:30');
        $this->assertSame('personnel_calendar_exclusion', $this->upload($this->event('2026-08-08 07:30', 'EMP-2'))[0]['reason']);
        $this->start('2026-08-10 06:30');
        Carbon::setTestNow('2026-08-10 09:00');
        $this->assertSame('rejected', $this->upload($this->event('2026-08-10 07:30', 'EMP-2'))[0]['status']);
        $this->start('2026-08-11 06:30');
        Carbon::setTestNow('2026-08-12 09:00');
        $date = Carbon::parse('2026-08-11');
        $service = app(PersonnelAttendanceSummary::class);
        $this->assertSame(0, $service->totals($service->days($staff, $date, $date, true))['absentDays']);
    }
}
