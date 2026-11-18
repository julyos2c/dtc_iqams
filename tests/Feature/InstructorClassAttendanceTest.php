<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Course;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InstructorClassAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancellation_class_choices_and_requests_are_limited_to_assigned_schedules(): void
    {
        [$teacher, $otherTeacher, $subject, $section, $otherSection] = $this->classroom();
        $assigned = $this->schedule($teacher->instructor, $subject, $section, 'monday');
        $other = $this->schedule($otherTeacher->instructor, $subject, $otherSection, 'monday');

        $this->actingAs($teacher)->get(route('instructor.schedule'))
            ->assertOk()
            ->assertSee('value="'.$assigned->id.'" data-day="monday"', false)
            ->assertDontSee('value="'.$other->id.'" data-day="monday"', false);

        $this->post(route('instructor.schedule.cancellation', $other), [
            'occurrence_date' => '2026-08-24', 'reason' => 'Unavailable',
        ])->assertForbidden();
    }

    public function test_active_cancellation_is_unique_and_pending_allows_student_attendance(): void
    {
        Carbon::setTestNow('2026-08-24 09:00:00');
        [$teacher, , $subject, $section] = $this->classroom();
        $schedule = $this->schedule($teacher->instructor, $subject, $section, 'monday');
        $student = $this->student($section, 'STU-CANCEL-3', 'Test', 'Student');
        $service = app(\App\Services\ClassCancellationService::class);
        $service->submit($schedule, Carbon::parse('2026-08-24'), 'Unavailable', $teacher);
        $this->assertNotNull(app(\App\Services\AttendanceScheduleValidator::class)->validate($student->user, $schedule, Carbon::parse('2026-08-24 10:01:00')));
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->submit($schedule, Carbon::parse('2026-08-24'), 'Duplicate', $teacher);
    }

    public function test_reconciliation_failure_rolls_back_review(): void
    {
        Carbon::setTestNow('2026-08-24 12:00:00');
        [$teacher, , $subject, $section] = $this->classroom();
        $schedule = $this->schedule($teacher->instructor, $subject, $section, 'monday');
        $service = app(\App\Services\ClassCancellationService::class);
        $item = $service->submit($schedule, Carbon::parse('2026-08-24'), 'Unavailable', $teacher);
        $this->mock(\App\Services\StudentAbsenceService::class)->shouldReceive('markOccurrenceDue')->once()->andThrow(new \RuntimeException('write failed'));
        try {
            $service->review($item, 'rejected', null, User::factory()->create());
            $this->fail('Expected reconciliation to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('write failed', $exception->getMessage());
        }
        $this->assertDatabaseHas('class_cancellation_requests', ['id' => $item->id, 'status' => 'pending', 'reviewer_id' => null]);
    }

    public function test_rejection_before_cutoff_waits_for_normal_absence_processing(): void
    {
        Carbon::setTestNow('2026-08-24 09:00:00');
        [$teacher, , $subject, $section] = $this->classroom();
        $schedule = $this->schedule($teacher->instructor, $subject, $section, 'monday');
        $student = $this->student($section, 'STU-CANCEL-4', 'Test', 'Student');
        $service = app(\App\Services\ClassCancellationService::class);
        $item = $service->submit($schedule, Carbon::parse('2026-08-24'), 'Unavailable', $teacher);
        $service->review($item, 'rejected', null, User::factory()->create());
        $this->assertDatabaseMissing('attendance_logs', ['user_id' => $student->user_id, 'schedule_id' => $schedule->id]);
        Carbon::setTestNow('2026-08-24 12:00:00');
        app(\App\Services\StudentAbsenceService::class)->markDue();
        $this->assertDatabaseHas('attendance_logs', ['user_id' => $student->user_id, 'schedule_id' => $schedule->id, 'status' => 'absent']);
    }

    public function test_pending_cancellation_holds_absence_and_rejection_reconciles(): void
    {
        Carbon::setTestNow('2026-08-24 09:00:00');
        [$teacher, , $subject, $section] = $this->classroom();
        $schedule = $this->schedule($teacher->instructor, $subject, $section, 'monday');
        $student = $this->student($section, 'STU-CANCEL', 'Test', 'Student');
        $service = app(\App\Services\ClassCancellationService::class);
        $item = $service->submit($schedule, Carbon::parse('2026-08-24'), 'Unavailable', $teacher);
        Carbon::setTestNow('2026-08-24 12:00:00');
        $this->assertSame(0, app(\App\Services\StudentAbsenceService::class)->markDue());
        $service->review($item, 'rejected', null, User::factory()->create());
        $this->assertDatabaseHas('attendance_logs', ['user_id' => $student->user_id, 'schedule_id' => $schedule->id, 'status' => 'absent']);
        $this->assertSame(0, app(\App\Services\StudentAbsenceService::class)->markDue());
        $this->assertNotNull($service->submit($schedule, Carbon::parse('2026-08-24'), 'Another request', $teacher));
    }

    public function test_approved_cancellation_excludes_existing_attendance_from_summary(): void
    {
        Carbon::setTestNow('2026-08-24 09:00:00');
        [$teacher, , $subject, $section] = $this->classroom();
        $schedule = $this->schedule($teacher->instructor, $subject, $section, 'monday');
        $student = $this->student($section, 'STU-CANCEL-2', 'Test', 'Student');
        $this->log($student, $schedule, '2026-08-24 10:01:00', 'present');
        $service = app(\App\Services\ClassCancellationService::class);
        $item = $service->submit($schedule, Carbon::parse('2026-08-24'), 'Unavailable', $teacher);
        $service->review($item, 'approved', null, User::factory()->create());
        $this->assertSame(0, app(\App\Services\StudentAttendanceSummary::class)->forStudent($student)['scheduled']);
        $this->assertDatabaseHas('attendance_logs', ['user_id' => $student->user_id, 'status' => 'present']);
        $this->actingAs($teacher)->getJson(route('instructor.schedule.attendance', $schedule).'?date=2026-08-24')
            ->assertOk()->assertJsonPath('class.cancellation_status', 'approved');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_recurring_schedule_rows_are_grouped_without_losing_day_schedule_ids(): void
    {
        [$instructorUser, , $subject, $section] = $this->classroom();
        $schedules = collect(['monday', 'wednesday', 'friday'])->map(fn (string $day) => $this->schedule(
            $instructorUser->instructor, $subject, $section, $day,
        ));

        $response = $this->actingAs($instructorUser)->get(route('instructor.schedule'));

        $response->assertOk()->assertViewHas('scheduleGroups', function ($groups) use ($schedules) {
            $group = $groups->sole();

            return $group['days_label'] === 'MWF'
                && collect($group['days'])->pluck('schedule_id')->all() === $schedules->pluck('id')->all();
        });
    }

    public function test_page_uses_the_centrally_registered_alpine_component(): void
    {
        Carbon::setTestNow('2026-08-28 12:00:00');
        [$instructorUser] = $this->classroom();

        $this->actingAs($instructorUser)->get(route('instructor.schedule'))
            ->assertOk()
            ->assertSee('x-data="classAttendanceBrowser()"', false)
            ->assertSee('data-today="2026-08-28"', false)
            ->assertSee('data-attendance-endpoint=', false)
            ->assertDontSee('function classAttendanceBrowser()', false);
    }

    public function test_attendance_is_filtered_by_schedule_section_and_actual_date(): void
    {
        Carbon::setTestNow('2026-08-28 12:00:00');
        [$instructorUser, $otherInstructorUser, $subject, $section, $otherSection] = $this->classroom();
        $monday = $this->schedule($instructorUser->instructor, $subject, $section, 'monday');
        $wednesday = $this->schedule($instructorUser->instructor, $subject, $section, 'wednesday');
        $otherSchedule = $this->schedule($otherInstructorUser->instructor, $subject, $otherSection, 'monday');

        $present = $this->student($section, 'STU-001', 'Ana', 'Present');
        $late = $this->student($section, 'STU-002', 'Ben', 'Late');
        $absent = $this->student($section, 'STU-003', 'Cara', 'Absent');
        $excused = $this->student($section, 'STU-004', 'Dan', 'Excused');
        $missing = $this->student($section, 'STU-005', 'Ella', 'Missing');
        $outsider = $this->student($otherSection, 'STU-999', 'Other', 'Section');

        $this->log($present, $monday, '2026-08-24 10:02:00', 'present');
        $this->log($late, $monday, '2026-08-24 10:20:00', 'late');
        $this->log($absent, $monday, '2026-08-24 10:16:00', 'absent');
        $this->log($excused, $monday, '2026-08-24 10:16:00', 'excused');
        $this->log($present, $wednesday, '2026-08-26 10:03:00', 'present');
        $this->log($outsider, $otherSchedule, '2026-08-24 10:01:00', 'present');

        $this->actingAs($instructorUser)
            ->getJson(route('instructor.schedule.attendance', $monday).'?date=2026-08-24')
            ->assertOk()
            ->assertJsonPath('class.date', '2026-08-24')
            ->assertJsonPath('summary.present', 2)
            ->assertJsonPath('summary.absent', 2)
            ->assertJsonPath('summary.excused', 1)
            ->assertJsonPath('summary.pending', 0)
            ->assertJsonCount(5, 'students')
            ->assertJsonMissing(['student_no' => 'STU-999'])
            ->assertJsonFragment(['student_no' => $absent->student_no, 'status' => 'absent', 'time_in' => null, 'recorded' => false])
            ->assertJsonFragment(['student_no' => $missing->student_no, 'status' => 'absent', 'recorded' => false]);
    }

    public function test_missing_scans_remain_pending_before_the_class_cutoff(): void
    {
        Carbon::setTestNow('2026-08-24 12:00:00');
        [$instructorUser, , $subject, $section] = $this->classroom();
        $wednesday = $this->schedule($instructorUser->instructor, $subject, $section, 'wednesday');
        $student = $this->student($section, 'STU-010', 'Future', 'Student');

        $this->actingAs($instructorUser)
            ->getJson(route('instructor.schedule.attendance', $wednesday).'?date=2026-08-26')
            ->assertOk()
            ->assertJsonPath('has_records', false)
            ->assertJsonPath('summary.pending', 1)
            ->assertJsonFragment(['student_no' => $student->student_no, 'status' => 'pending']);
    }

    public function test_instructor_cannot_view_another_instructors_schedule(): void
    {
        [$instructorUser, $otherInstructorUser, $subject, , $otherSection] = $this->classroom();
        $schedule = $this->schedule($otherInstructorUser->instructor, $subject, $otherSection, 'monday');

        $this->actingAs($instructorUser)
            ->getJson(route('instructor.schedule.attendance', $schedule).'?date=2026-08-24')
            ->assertForbidden();
    }

    public function test_instructor_can_download_their_subject_attendance_as_an_excel_report(): void
    {
        Carbon::setTestNow('2026-08-28 12:00:00');
        [$instructorUser, , $subject, $section] = $this->classroom();
        $schedule = $this->schedule($instructorUser->instructor, $subject, $section, 'monday');
        $student = $this->student($section, 'STU-011', 'Export', 'Student');
        $this->log($student, $schedule, '2026-08-24 10:02:00', 'present');

        $response = $this->actingAs($instructorUser)
            ->get(route('instructor.schedule.attendance.download', $schedule).'?date=2026-08-24');

        $response
            ->assertOk()
            ->assertDownload('class-attendance-it301-bsit-3a-2026-08-24.xlsx')
            ->assertHeader('cache-control', 'no-store, private');

        $this->assertStringStartsWith('PK', $response->streamedContent());
    }

    public function test_instructor_sees_subject_dropout_risk_warning_at_five_absences(): void
    {
        [$instructorUser, $otherInstructorUser, $subject, $section, $otherSection] = $this->classroom();
        $schedule = $this->schedule($instructorUser->instructor, $subject, $section, 'monday');
        $otherSchedule = $this->schedule($otherInstructorUser->instructor, $subject, $otherSection, 'monday');
        $student = $this->student($section, 'STU-RISK', 'Risk', 'Student');
        $otherStudent = $this->student($otherSection, 'STU-OTHER', 'Other', 'Student');

        for ($day = 3; $day <= 7; $day++) {
            $this->log($student, $schedule, "2026-08-{$day} 10:05:00", 'absent');
            $this->log($otherStudent, $otherSchedule, "2026-08-{$day} 10:05:00", 'absent');
        }

        $this->actingAs($instructorUser)
            ->get(route('instructor.issues'))
            ->assertOk()
            ->assertSee('Student dropout-risk warnings')
            ->assertSee('Risk Student')
            ->assertSee('5 absences')
            ->assertSee('At Risk')
            ->assertDontSee('Other Student');
    }

    public function test_date_must_match_the_selected_schedule_weekday(): void
    {
        [$instructorUser, , $subject, $section] = $this->classroom();
        $monday = $this->schedule($instructorUser->instructor, $subject, $section, 'monday');

        $this->actingAs($instructorUser)
            ->getJson(route('instructor.schedule.attendance', $monday).'?date=2026-08-26')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date');
    }

    private function classroom(): array
    {
        $instructorRole = Role::firstOrCreate(['role_name' => 'instructor']);
        $department = Department::create(['department_code' => 'IT', 'department_name' => 'Information Technology']);
        $course = Course::create(['department_id' => $department->id, 'course_code' => 'BSIT', 'course_name' => 'BS Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'section_name' => 'BSIT-3A', 'school_year' => '2026-2027', 'semester' => '1st']);
        $otherSection = Section::create(['course_id' => $course->id, 'section_name' => 'BSIT-3B', 'school_year' => '2026-2027', 'semester' => '1st']);
        $subject = Subject::create(['subject_code' => 'IT301', 'subject_name' => 'Systems Analysis', 'units' => 3]);

        $users = collect(['One', 'Two'])->map(function (string $lastName) use ($instructorRole, $department) {
            $user = User::factory()->create(['role_id' => $instructorRole->id, 'status' => 'active']);
            Instructor::create(['user_id' => $user->id, 'department_id' => $department->id, 'employee_no' => 'INS-'.$lastName, 'first_name' => 'Instructor', 'last_name' => $lastName]);

            return $user->load('instructor');
        });

        return [$users[0], $users[1], $subject, $section, $otherSection];
    }

    private function schedule(Instructor $instructor, Subject $subject, Section $section, string $day): Schedule
    {
        return Schedule::create(['subject_id' => $subject->id, 'instructor_id' => $instructor->id, 'section_id' => $section->id, 'day' => $day, 'start_time' => '10:00', 'end_time' => '11:00', 'room' => '204']);
    }

    private function student(Section $section, string $number, string $firstName, string $lastName): Student
    {
        $user = User::factory()->create(['role_id' => Role::firstOrCreate(['role_name' => 'student'])->id, 'status' => 'active']);

        return Student::create(['user_id' => $user->id, 'student_no' => $number, 'first_name' => $firstName, 'last_name' => $lastName, 'section_id' => $section->id, 'course_id' => $section->course_id, 'status' => 'active']);
    }

    private function log(Student $student, Schedule $schedule, string $time, string $status): void
    {
        AttendanceLog::create(['user_id' => $student->user_id, 'schedule_id' => $schedule->id, 'attendance_type' => 'time_in', 'scan_time' => $time, 'status' => $status]);
    }
}
