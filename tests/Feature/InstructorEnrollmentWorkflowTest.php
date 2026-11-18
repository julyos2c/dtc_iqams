<?php

namespace Tests\Feature;

use App\Models\ClassList;
use App\Models\Course;
use App\Models\Department;
use App\Models\EnrollmentRequest;
use App\Models\Instructor;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\SchoolEvent;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\AttendanceAbsenceWriter;
use App\Services\EnrollmentReviewService;
use App\Services\EnrollmentWorkflow;
use App\Services\InstructorEnrollmentService;
use App\Services\QrAttendanceService;
use App\Services\QrCredentialService;
use App\Services\ScheduleOccurrenceResolver;
use App\Services\SchoolEventAttendanceService;
use App\Services\SchoolEventResolver;
use App\Services\StudentAbsenceService;
use App\Services\StudentAttendanceSummary;
use App\Services\StudentScheduleEligibility;
use App\Services\SubjectRosterService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InstructorEnrollmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $admin;

    private Section $section;

    private ClassList $list;

    private array $meetings;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-09 08:20:00', config('app.timezone')));
        $this->seed(RolePermissionSeeder::class);
        $this->teacher = $this->user('instructor');
        $this->admin = $this->user('admin');
        $department = Department::create(['department_code' => 'ENR', 'department_name' => 'Enrollment']);
        $course = Course::create(['department_id' => $department->id, 'course_code' => 'ENR', 'course_name' => 'Enrollment Course']);
        $this->section = Section::create(['course_id' => $course->id, 'section_name' => 'ENR-A', 'school_year' => '2026-2027', 'semester' => '1st']);
        $instructor = Instructor::create(['user_id' => $this->teacher->id, 'department_id' => $department->id, 'employee_no' => 'ENR-I', 'first_name' => 'Class', 'last_name' => 'Teacher']);
        $this->teacher->unsetRelation('instructor');
        $this->meetings = [];
        for ($i = 1; $i <= 3; $i++) {
            $subject = Subject::create(['subject_code' => 'ENR'.$i, 'subject_name' => 'Subject '.$i, 'units' => 3]);
            $group = (string) Str::uuid();
            foreach (['monday', 'wednesday'] as $day) {
                $schedule = Schedule::create(['subject_id' => $subject->id, 'section_id' => $this->section->id, 'instructor_id' => $instructor->id, 'recurring_schedule_group_id' => $group, 'day' => $day, 'start_time' => '08:00', 'end_time' => '10:00', 'room' => 'R'.$i]);
                if ($day === 'monday') {
                    $this->meetings[] = $schedule;
                }
            }
        }
        $this->list = ClassList::create(['instructor_id' => $instructor->id, 'section_id' => $this->section->id, 'name' => 'Official list']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::findByName($role)->id, 'status' => 'active']);
    }

    private function enable(): void
    {
        DB::table('enrollment_workflow_states')->where('id', 1)->update(['enabled_at' => now()]);
        app(EnrollmentWorkflow::class)->refresh();
    }

    private function details(string $number = 'NEW-001'): array
    {
        return ['student_no' => $number, 'first_name' => 'New', 'last_name' => 'Student', 'email' => strtolower($number).'@example.test', 'course_id' => $this->section->course_id, 'section_id' => $this->section->id];
    }

    private function pending(string $number = 'NEW-001'): EnrollmentRequest
    {
        $service = app(InstructorEnrollmentService::class);
        $entry = $service->addEntry($this->list, $this->teacher, null, $this->details($number));

        return $service->submit($this->list, $this->teacher, [['group_id' => $this->meetings[0]->recurring_schedule_group_id, 'entry_ids' => [$entry->id], 'confirmed' => true]])->requests()->first();
    }

    public function test_drafts_and_pending_requests_do_not_create_accounts_and_pages_render(): void
    {
        $request = $this->pending();
        $this->assertDatabaseMissing('students', ['student_no' => 'NEW-001']);
        $this->assertDatabaseMissing('users', ['username' => 'NEW-001']);
        $this->assertDatabaseCount('qr_credentials', 0);
        $this->assertDatabaseCount('student_schedule_enrollments', 0);
        $this->actingAs($this->teacher)->get(route('instructor.schedule'))->assertOk()->assertSee('My Teaching Schedule');
        $this->get(route('instructor.subjects.show', $this->meetings[0]->recurring_schedule_group_id))->assertOk()->assertSee('Pending registration');
        $this->actingAs($this->admin)->get(route('admin.enrollment-requests.index'))->assertOk()->assertSee('NEW-001');
    }

    public function test_admin_submission_review_defaults_to_pending_and_allows_history_filtering(): void
    {
        $submission = $this->pending();
        $this->actingAs($this->admin)->get(route('admin.students.submissions'))
            ->assertOk()->assertSee('1 pending')->assertSee('NEW-001')->assertSee('Clear filters');

        $submission->update(['status' => 'rejected', 'review_notes' => 'Please correct the name.']);
        $this->get(route('admin.students.submissions'))
            ->assertOk()->assertSee('0 pending')->assertDontSee('NEW-001');
        $this->get(route('admin.students.submissions', ['status' => 'rejected']))
            ->assertOk()->assertSee('NEW-001')->assertSee('Please correct the name.');
        $this->get(route('admin.students.submissions', ['status' => 'all']))
            ->assertOk()->assertSee('NEW-001');
        $this->actingAs($this->teacher)->get(route('admin.students.submissions'))->assertForbidden();
    }

    public function test_approval_creates_one_account_and_qr_and_is_effective_for_every_meeting(): void
    {
        $this->enable();
        $request = $this->pending();
        $service = app(EnrollmentReviewService::class);
        $result = $service->review($request, $this->admin, 'approved', null);
        $student = Student::where('student_no', 'NEW-001')->firstOrFail();
        $this->assertSame('Student@NEW-001', $result['temporary_password']);
        $this->assertTrue($student->user->must_change_password);
        $this->assertDatabaseCount('qr_credentials', 1);
        $this->assertDatabaseCount('student_schedule_enrollments', 1);
        $eligibility = app(StudentScheduleEligibility::class);
        $this->assertCount(2, $eligibility->schedulesFor($student)->get());
        $this->assertFalse($eligibility->isEligibleForSchedule($student, $this->meetings[0], now()->subSecond()));
        $this->assertFalse($eligibility->isEligibleForSchedule($student, $this->meetings[1]));
        $again = $service->review($request, $this->admin, 'approved', null);
        $this->assertArrayNotHasKey('temporary_password', $again);
        $this->assertDatabaseCount('qr_credentials', 1);
        $this->assertDatabaseCount('student_schedule_enrollments', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'registration.approved']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.activated']);
        $this->assertStringNotContainsString('Student@NEW-001', DB::table('audit_logs')->pluck('metadata')->implode(' '));
    }

    public function test_one_list_can_submit_independent_rosters_without_duplicate_identity(): void
    {
        $this->enable();
        $service = app(InstructorEnrollmentService::class);
        $a = $service->addEntry($this->list, $this->teacher, null, $this->details('NEW-A'));
        $b = $service->addEntry($this->list, $this->teacher, null, $this->details('NEW-B'));
        $batch = $service->submit($this->list, $this->teacher, [
            ['group_id' => $this->meetings[0]->recurring_schedule_group_id, 'entry_ids' => [$a->id, $b->id], 'confirmed' => true],
            ['group_id' => $this->meetings[1]->recurring_schedule_group_id, 'entry_ids' => [$a->id], 'confirmed' => true],
            ['group_id' => $this->meetings[2]->recurring_schedule_group_id, 'entry_ids' => [$a->id, $b->id], 'confirmed' => true],
        ]);
        $this->assertSame(5, $batch->requests()->count());
        foreach ($batch->requests as $request) {
            app(EnrollmentReviewService::class)->review($request, $this->admin, 'approved', null);
        }
        $this->assertDatabaseCount('students', 2);
        $this->assertDatabaseCount('qr_credentials', 2);
        $this->assertDatabaseCount('student_schedule_enrollments', 5);
    }

    public function test_existing_student_keeps_credentials_and_profile_edits_preserve_enrollment(): void
    {
        $this->enable();
        $request = $this->pending();
        app(EnrollmentReviewService::class)->review($request, $this->admin, 'approved', null);
        $student = Student::firstOrFail();
        $qr = $student->user->qrCredentials()->first();
        $otherList = ClassList::create(['instructor_id' => $this->list->instructor_id, 'section_id' => $this->section->id, 'name' => 'Second list']);
        $service = app(InstructorEnrollmentService::class);
        $entry = $service->addEntry($otherList, $this->teacher, $student, null);
        $next = $service->submit($otherList, $this->teacher, [['group_id' => $this->meetings[1]->recurring_schedule_group_id, 'entry_ids' => [$entry->id], 'confirmed' => true]])->requests()->first();
        $result = app(EnrollmentReviewService::class)->review($next, $this->admin, 'approved', null);
        $this->assertArrayNotHasKey('temporary_password', $result);
        $this->assertSame($qr->code_hash, $student->user->qrCredentials()->first()->code_hash);
        $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => now()->timestamp])->put(route('students.update', $student), [
            'first_name' => 'Edited', 'last_name' => 'Student', 'course_id' => $student->course_id, 'section_id' => $student->section_id,
            'status' => 'active', 'enrollment_type' => 'irregular', 'enrollment_group_ids' => [],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, $student->scheduleEnrollments()->count());
        $this->post(route('students.store'), $this->details('BYPASS'))->assertForbidden();
        $this->get(route('students.index'))->assertOk()->assertDontSee('+ Add Student');
    }

    public function test_rejection_requires_reason_and_resubmission_preserves_previous_decision(): void
    {
        $this->enable();
        $request = $this->pending();
        app(EnrollmentReviewService::class)->review($request, $this->admin, 'rejected', 'Correct the first name.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'enrollment.rejected']);
        $this->assertStringNotContainsString('Correct the first name.', DB::table('audit_logs')->pluck('metadata')->implode(' '));
        $this->assertDatabaseCount('students', 0);
        $this->actingAs($this->teacher)->get(route('instructor.enrollment-requests.edit', $request))->assertOk()->assertSee('Correct the first name.');
        $this->post(route('instructor.enrollment-requests.resubmit', $request), $this->details() + ['confirmed' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertDatabaseHas('enrollment_requests', ['previous_request_id' => $request->id, 'status' => 'pending']);
    }

    public function test_duplicate_pending_requests_are_rejected_atomically(): void
    {
        $this->pending();
        $entry = $this->list->entries()->first();
        try {
            app(InstructorEnrollmentService::class)->submit($this->list, $this->teacher, [['group_id' => $this->meetings[0]->recurring_schedule_group_id, 'entry_ids' => [$entry->id], 'confirmed' => true]]);
            $this->fail('Duplicate submission was accepted.');
        } catch (ValidationException $exception) {
            $this->assertDatabaseCount('enrollment_requests', 1);
            $this->assertDatabaseCount('class_list_submission_batches', 1);
        }
    }

    public function test_private_lists_and_review_permissions_are_enforced(): void
    {
        $request = $this->pending();
        $other = $this->user('instructor');
        $this->actingAs($other)->get(route('instructor.class-lists.show', $this->list))->assertForbidden();
        $this->post(route('instructor.class-lists.entries.store', $this->list), ['student_id' => 1])->assertForbidden();
        $this->post(route('admin.enrollment-requests.review'), ['request_ids' => [$request->id], 'decision' => 'approved'])->assertForbidden();
        $student = $this->user('student');
        $this->actingAs($student)->get(route('instructor.class-lists.show', ['classList' => $this->list, 'search' => 'NEW']))->assertForbidden();
        $this->admin->syncRoles([]);
        $this->admin->assignRole('admin');
        Role::findByName('admin')->revokePermissionTo('review-enrollment-requests');
        $this->actingAs($this->admin)->get(route('admin.enrollment-requests.index'))->assertForbidden();
    }

    public function test_bulk_review_keeps_valid_results_when_an_offering_is_archived(): void
    {
        $this->enable();
        $good = $this->pending('GOOD');
        $bad = $this->pending('BAD');
        $bad->update(['recurring_schedule_group_id' => $this->meetings[1]->recurring_schedule_group_id, 'snapshot' => array_replace($bad->snapshot, ['subject_id' => $this->meetings[1]->subject_id])]);
        Schedule::where('recurring_schedule_group_id', $bad->recurring_schedule_group_id)->update(['archived_at' => now()]);
        $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => now()->timestamp])->post(route('admin.enrollment-requests.review'), ['request_ids' => [$good->id, $bad->id], 'decision' => 'approved'])
            ->assertRedirect()->assertSessionHas('enrollment_review_results');
        $this->assertSame('approved', $good->fresh()->status);
        $this->assertSame('pending', $bad->fresh()->status);
        $this->assertDatabaseCount('students', 1);
    }

    public function test_new_regular_student_is_not_enrolled_by_section_and_no_retroactive_absence_is_written(): void
    {
        $this->enable();
        $request = $this->pending();
        app(EnrollmentReviewService::class)->review($request, $this->admin, 'approved', null);
        $student = Student::firstOrFail();
        $occurrence = app(ScheduleOccurrenceResolver::class)->forDate($this->meetings[0], now());
        $this->assertSame(0, app(AttendanceAbsenceWriter::class)->forSchedule($occurrence));
        $this->assertSame(0, app(StudentAttendanceSummary::class)->forStudent($student)['absent']);
        $this->assertFalse(app(StudentScheduleEligibility::class)->isEligibleForSchedule($student, $this->meetings[1]));
    }

    public function test_cutover_backfills_once_and_preserves_regular_rosters(): void
    {
        $user = $this->user('student');
        $student = Student::create(collect($this->details('LEGACY'))->except('email')->all() + ['user_id' => $user->id, 'status' => 'active']);
        $this->artisan('enrollment:enable')->assertSuccessful();
        $this->assertDatabaseCount('student_schedule_enrollments', 0);
        $this->artisan('enrollment:enable', ['--apply' => true])->assertSuccessful();
        $this->assertCount(6, app(StudentScheduleEligibility::class)->schedulesFor($student)->get());
        $this->assertSame(3, $student->scheduleEnrollments()->count());
        $this->artisan('enrollment:enable', ['--apply' => true])->assertSuccessful();
        $this->assertSame(3, $student->scheduleEnrollments()->count());
        $this->assertDatabaseCount('qr_credentials', 0);
    }

    public function test_two_instructors_resolve_the_same_registration_to_one_account(): void
    {
        $this->enable();
        $first = $this->pending();
        $secondTeacher = $this->user('instructor');
        $secondInstructor = Instructor::create(['user_id' => $secondTeacher->id, 'department_id' => $this->teacher->instructor->department_id, 'employee_no' => 'ENR-II', 'first_name' => 'Second', 'last_name' => 'Teacher']);
        Schedule::where('recurring_schedule_group_id', $this->meetings[1]->recurring_schedule_group_id)->update(['instructor_id' => $secondInstructor->id]);
        $secondList = ClassList::create(['instructor_id' => $secondInstructor->id, 'section_id' => $this->section->id, 'name' => 'Other teacher list']);
        $service = app(InstructorEnrollmentService::class);
        $entry = $service->addEntry($secondList, $secondTeacher, null, $this->details());
        $second = $service->submit($secondList, $secondTeacher, [['group_id' => $this->meetings[1]->recurring_schedule_group_id, 'entry_ids' => [$entry->id], 'confirmed' => true]])->requests()->first();
        app(EnrollmentReviewService::class)->review($first, $this->admin, 'approved', null);
        app(EnrollmentReviewService::class)->review($second, $this->admin, 'approved', null);
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('qr_credentials', 1);
        $this->assertDatabaseCount('student_schedule_enrollments', 2);
        $this->assertSame($first->fresh()->student_id, $second->fresh()->student_id);
    }

    public function test_thirty_students_across_three_offerings_produce_ninety_requests(): void
    {
        $this->enable();
        $service = app(InstructorEnrollmentService::class);
        $ids = [];
        for ($i = 1; $i <= 30; $i++) {
            $ids[] = $service->addEntry($this->list, $this->teacher, null, $this->details('CLASS-'.$i))->id;
        }
        $rosters = array_map(fn ($meeting) => ['group_id' => $meeting->recurring_schedule_group_id, 'entry_ids' => $ids, 'confirmed' => true], $this->meetings);
        $batch = $service->submit($this->list, $this->teacher, $rosters);
        $this->assertSame(90, $batch->requests()->count());
        $this->assertDatabaseCount('student_registration_identities', 30);
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('qr_credentials', 0);
    }

    public function test_submission_requires_assigned_offerings_and_confirmed_rosters(): void
    {
        $groupId = $this->meetings[0]->recurring_schedule_group_id;
        $roster = app(SubjectRosterService::class)->roster($this->teacher, $groupId, true);
        $entry = app(InstructorEnrollmentService::class)->addEntry($roster, $this->teacher, null, $this->details());
        $this->actingAs($this->teacher)->post(route('instructor.subjects.submit', $groupId), ['entry_ids' => [$entry->id]])->assertSessionHasErrors('confirmed');
        $other = $this->user('instructor');
        $instructor = Instructor::create(['user_id' => $other->id, 'department_id' => $this->teacher->instructor->department_id, 'employee_no' => 'OTHER', 'first_name' => 'Other', 'last_name' => 'Teacher']);
        Schedule::where('recurring_schedule_group_id', $this->meetings[0]->recurring_schedule_group_id)->update(['instructor_id' => $instructor->id]);
        $this->post(route('instructor.subjects.submit', $groupId), ['entry_ids' => [$entry->id], 'confirmed' => 1])->assertForbidden();
        $this->assertDatabaseCount('enrollment_requests', 0);
    }

    public function test_review_requires_recent_password_confirmation_and_rejection_notes(): void
    {
        $request = $this->pending();
        $this->actingAs($this->admin)->post(route('admin.enrollment-requests.review'), [
            'request_ids' => [$request->id], 'decision' => 'approved',
        ])->assertRedirect(route('password.confirm'));
        $this->withSession(['auth.password_confirmed_at' => now()->timestamp])->post(route('admin.enrollment-requests.review'), [
            'request_ids' => [$request->id], 'decision' => 'rejected',
        ])->assertSessionHasErrors('review_notes');
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_approval_is_blocked_until_cutover_and_when_assignment_changes(): void
    {
        $request = $this->pending();
        try {
            app(EnrollmentReviewService::class)->review($request, $this->admin, 'approved', null);
            $this->fail('Approval bypassed cutover.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('students', 0);
        }
        $this->enable();
        $this->teacher->update(['status' => 'inactive']);
        $this->expectException(ValidationException::class);
        app(EnrollmentReviewService::class)->review($request, $this->admin, 'approved', null);
    }

    public function test_conflicting_identity_details_do_not_overwrite_an_existing_account(): void
    {
        $this->enable();
        $first = $this->pending();
        $entry = $this->list->entries()->first();
        $second = app(InstructorEnrollmentService::class)->submit($this->list, $this->teacher, [[
            'group_id' => $this->meetings[1]->recurring_schedule_group_id, 'entry_ids' => [$entry->id], 'confirmed' => true,
        ]])->requests()->first();
        $snapshot = $second->snapshot;
        $snapshot['student']['first_name'] = 'Different';
        $second->update(['snapshot' => $snapshot]);
        app(EnrollmentReviewService::class)->review($first, $this->admin, 'approved', null);
        try {
            app(EnrollmentReviewService::class)->review($second, $this->admin, 'approved', null);
            $this->fail('Conflicting registration was approved.');
        } catch (ValidationException) {
            $this->assertSame('New', Student::first()->first_name);
            $this->assertSame('pending', $second->fresh()->status);
            $this->assertDatabaseCount('student_schedule_enrollments', 1);
        }
    }

    public function test_cutover_refuses_missing_offering_groups(): void
    {
        $this->meetings[0]->update(['recurring_schedule_group_id' => null]);
        $this->artisan('enrollment:enable', ['--apply' => true])->assertFailed();
        $this->assertFalse(app(EnrollmentWorkflow::class)->enabled());
        $this->assertDatabaseCount('student_schedule_enrollments', 0);
    }

    public function test_admin_student_creation_is_unavailable_before_and_after_cutover(): void
    {
        $usersBefore = User::count();
        foreach ([false, true] as $enabled) {
            DB::table('enrollment_workflow_states')->where('id', 1)->update(['enabled_at' => $enabled ? now() : null]);
            app(EnrollmentWorkflow::class)->refresh();
            $this->actingAs($this->admin)->get(route('students.index'))->assertOk()->assertDontSee('+ Add Student')->assertDontSee('showCreateModal');
            $this->post(route('students.store'), $this->details('ADMIN-BYPASS'))->assertForbidden();
            $this->assertSame($usersBefore, User::count());
            $this->assertDatabaseCount('students', 0);
            $this->assertDatabaseCount('qr_credentials', 0);
        }
    }

    public function test_existing_regular_roster_can_be_submitted_before_cutover(): void
    {
        $user = $this->user('student');
        $student = Student::create(collect($this->details('EXISTING'))->except('email')->all() + ['user_id' => $user->id, 'status' => 'active']);
        $service = app(InstructorEnrollmentService::class);
        $groupId = $this->meetings[0]->recurring_schedule_group_id;
        $roster = app(SubjectRosterService::class)->roster($this->teacher, $groupId, true);
        $entry = $service->addEntry($roster, $this->teacher, $student, null);
        $this->assertFalse(app(EnrollmentWorkflow::class)->enabled());
        $this->assertTrue(app(StudentScheduleEligibility::class)->isEligibleForSchedule($student, $this->meetings[0]));
        $this->actingAs($this->teacher)->post(route('instructor.subjects.submit', $groupId), ['entry_ids' => [$entry->id], 'confirmed' => '1'])
            ->assertRedirect(route('instructor.subjects.show', $groupId))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('enrollment_requests', ['student_id' => $student->id, 'status' => 'pending']);
        $this->assertDatabaseCount('student_schedule_enrollments', 0);
    }

    public function test_registration_rejects_email_owned_by_another_account(): void
    {
        $details = $this->details();
        $details['email'] = $this->admin->email;
        try {
            app(InstructorEnrollmentService::class)->addEntry($this->list, $this->teacher, null, $details);
            $this->fail('An account email collision was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
            $this->assertDatabaseCount('student_registration_requests', 0);
            $this->assertDatabaseCount('students', 0);
        }
    }

    public function test_approved_student_can_scan_mid_session_and_appears_in_the_dated_report(): void
    {
        $this->enable();
        $request = $this->pending();
        app(EnrollmentReviewService::class)->review($request, $this->admin, 'approved', null);
        $student = Student::firstOrFail();
        $credential = $student->user->qrCredentials()->firstOrFail();
        $this->actingAs($this->teacher)->getJson(route('instructor.schedule.attendance', ['schedule' => $this->meetings[0], 'date' => '2026-11-09']))
            ->assertOk()->assertJsonPath('has_students', false);
        $qr = app(QrCredentialService::class)->plainText($credential);
        $log = app(QrAttendanceService::class)->record($qr, 'Enrollment test', now()->addMinute());
        $this->assertSame($this->meetings[0]->id, $log->schedule_id);
        $this->getJson(route('instructor.schedule.attendance', ['schedule' => $this->meetings[0], 'date' => '2026-11-09']))
            ->assertOk()->assertJsonPath('students.0.student_no', 'NEW-001')->assertJsonPath('students.0.recorded', true);
        Carbon::setTestNow(now()->addDays(2)->setTime(10, 5));
        $this->assertSame(1, app(StudentAbsenceService::class)->markDue());
        $this->assertSame(1, app(StudentAttendanceSummary::class)->forStudent($student)['absent']);
    }

    public function test_approving_another_offering_does_not_replace_a_revoked_qr(): void
    {
        $this->enable();
        $request = $this->pending();
        app(EnrollmentReviewService::class)->review($request, $this->admin, 'approved', null);
        $student = Student::firstOrFail();
        app(QrCredentialService::class)->revoke($student->user, $this->admin, 'Lost card');
        $entry = $this->list->entries()->first();
        $next = app(InstructorEnrollmentService::class)->submit($this->list, $this->teacher, [[
            'group_id' => $this->meetings[1]->recurring_schedule_group_id, 'entry_ids' => [$entry->id], 'confirmed' => true,
        ]])->requests()->first();
        app(EnrollmentReviewService::class)->review($next, $this->admin, 'approved', null);
        $this->assertDatabaseCount('qr_credentials', 1);
        $this->assertSame('revoked', $student->user->qrCredentials()->first()->status);
    }

    public function test_section_event_rosters_respect_enrollment_effective_time(): void
    {
        $this->enable();
        $request = $this->pending();
        app(EnrollmentReviewService::class)->review($request, $this->admin, 'approved', null);
        $student = Student::firstOrFail();
        $event = SchoolEvent::create([
            'title' => 'Section activity', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(),
            'attendance_mode' => 'event_attendance', 'target_scope' => 'sections', 'status' => 'published', 'published_at' => now(),
        ]);
        $event->targets()->create(['section_id' => $this->section->id]);
        $event->load('targets.schedule');
        $this->assertTrue(app(SchoolEventResolver::class)->targetsStudent($event, $student, now()));
        $this->assertFalse(app(SchoolEventAttendanceService::class)->studentsFor($event)->whereKey($student->id)->exists());
        $event->update(['starts_at' => now()->addMinute()]);
        $this->assertTrue(app(SchoolEventAttendanceService::class)->studentsFor($event)->whereKey($student->id)->exists());
    }

    public function test_opening_subjects_needs_no_separate_list_and_creates_no_drafts(): void
    {
        $this->actingAs($this->teacher)->get(route('instructor.subjects.index'))->assertRedirect(route('instructor.schedule'));
        $response = $this->get(route('instructor.schedule'))->assertOk()->assertSee('Manage Students')->assertSee('View Attendance')->assertDontSee('My Subjects')->assertDontSee('Create list');
        foreach ($this->meetings as $meeting) {
            $url = route('instructor.subjects.show', $meeting->recurring_schedule_group_id);
            $this->assertSame(1, substr_count($response->getContent(), 'href="'.$url.'"'));
        }
        $this->get(route('instructor.subjects.show', $this->meetings[0]->recurring_schedule_group_id))->assertOk()->assertSee('Copy Students');
        $this->assertDatabaseCount('class_lists', 1);
        $this->assertDatabaseCount('class_list_entries', 0);
        $this->post(route('instructor.class-lists.store'), ['name' => 'Old list', 'section_id' => $this->section->id])->assertRedirect(route('instructor.schedule'));
        $this->assertDatabaseCount('class_lists', 1);
    }

    public function test_enrollment_requests_are_scoped_to_manage_students_and_absent_from_attendance(): void
    {
        $rejected = $this->pending();
        app(EnrollmentReviewService::class)->review($rejected, $this->admin, 'rejected', 'Correct the official roster.');
        $this->pending('OTHER-STUDENT');
        $this->actingAs($this->teacher)->get(route('instructor.schedule'))
            ->assertOk()->assertDontSee('Enrollment requests')->assertDontSee('Correct the official roster.');
        $this->get(route('instructor.subjects.show', ['groupId' => $this->meetings[0]->recurring_schedule_group_id, 'status' => 'rejected']))
            ->assertOk()->assertDontSee('Enrollment requests for this subject')->assertSee('Correct the official roster.')->assertSee('Correct and resubmit')
            ->assertViewHas('rows', fn ($rows) => $rows->get('NEW-001')['status'] === 'rejected' && $rows->get('OTHER-STUDENT')['status'] === 'pending');
        $this->get(route('instructor.subjects.index', ['status' => 'rejected']))->assertRedirect(route('instructor.schedule', ['status' => 'rejected']));
    }

    public function test_registration_submission_and_approval_start_inside_a_subject(): void
    {
        $this->enable();
        $group = $this->meetings[0]->recurring_schedule_group_id;
        $this->actingAs($this->teacher)->post(route('instructor.subjects.students.store', $group), $this->details())->assertRedirect(route('instructor.subjects.show', $group))->assertSessionHasNoErrors();
        $entry = ClassList::where('recurring_schedule_group_id', $group)->firstOrFail()->entries()->firstOrFail();
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('enrollment_requests', 0);
        $this->get(route('instructor.subjects.show', $group))->assertOk()->assertSee('Submit for Approval');
        $this->post(route('instructor.subjects.submit', $group), ['entry_ids' => [$entry->id], 'confirmed' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($group, EnrollmentRequest::first()->recurring_schedule_group_id);
        $this->get(route('instructor.subjects.show', $group))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->get('NEW-001')['status'] === 'pending');
        app(EnrollmentReviewService::class)->review(EnrollmentRequest::first(), $this->admin, 'approved', null);
        $this->get(route('instructor.subjects.show', $group))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->get('NEW-001')['status'] === 'approved');
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('qr_credentials', 1);
        $this->assertFalse(app(StudentScheduleEligibility::class)->isEligibleForSchedule(Student::first(), $this->meetings[1]));
    }

    public function test_copy_previews_only_selected_students_and_requires_separate_submission(): void
    {
        $this->enable();
        $sourceGroup = $this->meetings[0]->recurring_schedule_group_id;
        $targetGroup = $this->meetings[1]->recurring_schedule_group_id;
        $this->actingAs($this->teacher)->post(route('instructor.subjects.students.store', $sourceGroup), $this->details('COPY-A'))->assertSessionHasNoErrors();
        $this->post(route('instructor.subjects.students.store', $sourceGroup), $this->details('COPY-B'))->assertSessionHasNoErrors();
        $source = ClassList::where('recurring_schedule_group_id', $sourceGroup)->firstOrFail();
        $entry = $source->entries()->firstOrFail();
        $this->getJson(route('instructor.subjects.copy-preview', ['groupId' => $targetGroup, 'copy_from' => 'subject:'.$sourceGroup]))->assertOk()->assertJsonCount(2, 'students');
        $payload = ['copy_from' => 'subject:'.$sourceGroup, 'students' => ['entry:'.$entry->id]];
        $this->post(route('instructor.subjects.copy', $targetGroup), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('instructor.subjects.copy', $targetGroup), $payload)->assertSessionHasNoErrors();
        $target = ClassList::where('recurring_schedule_group_id', $targetGroup)->firstOrFail();
        $this->assertSame(1, $target->entries()->count());
        $this->assertSame(2, $source->entries()->count());
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('enrollment_requests', 0);
        $this->assertDatabaseCount('student_registration_requests', 2);
        $this->post(route('instructor.subjects.submit', $targetGroup), ['entry_ids' => [$target->entries()->first()->id], 'confirmed' => 1])->assertSessionHasNoErrors();
        $this->post(route('instructor.subjects.submit', $sourceGroup), ['entry_ids' => [$entry->id], 'confirmed' => 1])->assertSessionHasNoErrors();
        foreach (EnrollmentRequest::all() as $request) {
            app(EnrollmentReviewService::class)->review($request, $this->admin, 'approved', null);
        }
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('qr_credentials', 1);
        $this->assertDatabaseCount('student_schedule_enrollments', 2);
    }

    public function test_saved_students_are_preserved_and_can_be_copied_into_a_subject(): void
    {
        $entry = app(InstructorEnrollmentService::class)->addEntry($this->list, $this->teacher, null, $this->details());
        $target = $this->meetings[0]->recurring_schedule_group_id;
        $this->actingAs($this->teacher)->get(route('instructor.class-lists.show', $this->list))->assertRedirect(route('instructor.schedule'));
        $this->getJson(route('instructor.subjects.copy-preview', ['groupId' => $target, 'copy_from' => 'saved:'.$this->list->id]))->assertOk()->assertJsonPath('students.0.number', 'NEW-001');
        $this->post(route('instructor.subjects.copy', $target), ['copy_from' => 'saved:'.$this->list->id, 'students' => ['entry:'.$entry->id]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $this->list->entries()->count());
        $this->assertSame(1, ClassList::where('recurring_schedule_group_id', $target)->first()->entries()->count());
        $this->assertDatabaseCount('enrollment_requests', 0);
    }

    public function test_approved_students_can_be_copied_without_an_encoded_source_draft(): void
    {
        $this->enable();
        $student = Student::create(collect($this->details('APPROVED'))->except('email')->all() + ['user_id' => $this->user('student')->id, 'status' => 'active']);
        $source = $this->meetings[0]->recurring_schedule_group_id;
        $target = $this->meetings[1]->recurring_schedule_group_id;
        $student->scheduleEnrollments()->create(['recurring_schedule_group_id' => $source]);
        $this->actingAs($this->teacher)->post(route('instructor.subjects.copy', $target), ['copy_from' => 'subject:'.$source, 'students' => ['student:'.$student->id]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('student_schedule_enrollments', 1);
        $this->assertDatabaseCount('enrollment_requests', 0);
        $this->assertFalse(app(StudentScheduleEligibility::class)->isEligibleForSchedule($student, $this->meetings[1]));
    }

    public function test_subject_copy_rejects_other_instructors_and_forged_selection(): void
    {
        $group = $this->meetings[0]->recurring_schedule_group_id;
        $this->actingAs($this->user('instructor'))->get(route('instructor.subjects.show', $group))->assertForbidden();
        $this->post(route('instructor.subjects.students.store', $group), $this->details())->assertForbidden();
        $this->actingAs($this->teacher)->post(route('instructor.subjects.copy', $group), ['copy_from' => 'subject:'.$this->meetings[1]->recurring_schedule_group_id, 'students' => ['entry:999999']])->assertForbidden();
        $this->assertDatabaseCount('class_lists', 1);
        $this->assertDatabaseCount('class_list_entries', 0);
    }

    public function test_subject_submission_rejects_entries_from_another_roster(): void
    {
        $source = $this->meetings[0]->recurring_schedule_group_id;
        $target = $this->meetings[1]->recurring_schedule_group_id;
        $this->actingAs($this->teacher)->post(route('instructor.subjects.students.store', $source), $this->details())->assertSessionHasNoErrors();
        $entry = ClassList::where('recurring_schedule_group_id', $source)->first()->entries()->first();
        app(SubjectRosterService::class)->roster($this->teacher, $target, true);
        $this->post(route('instructor.subjects.submit', $target), ['entry_ids' => [$entry->id], 'confirmed' => 1])->assertNotFound();
        $this->assertDatabaseCount('enrollment_requests', 0);
        $this->assertDatabaseCount('class_list_submission_batches', 0);
    }

    public function test_pending_and_approved_students_cannot_be_removed_as_drafts(): void
    {
        $this->enable();
        $group = $this->meetings[0]->recurring_schedule_group_id;
        $this->actingAs($this->teacher)->post(route('instructor.subjects.students.store', $group), $this->details())->assertSessionHasNoErrors();
        $entry = ClassList::where('recurring_schedule_group_id', $group)->first()->entries()->first();
        $this->post(route('instructor.subjects.submit', $group), ['entry_ids' => [$entry->id], 'confirmed' => 1])->assertSessionHasNoErrors();
        $this->delete(route('instructor.subjects.students.destroy', [$group, $entry]))->assertSessionHasErrors('student');
        app(EnrollmentReviewService::class)->review(EnrollmentRequest::first(), $this->admin, 'approved', null);
        $this->delete(route('instructor.subjects.students.destroy', [$group, $entry]))->assertSessionHasErrors('student');
        $this->assertDatabaseCount('student_schedule_enrollments', 1);
        $this->assertSame('approved', EnrollmentRequest::first()->status);
    }
    public function test_existing_student_search_and_draft_creation_recheck_eligibility(): void
    {
        $group = $this->meetings[0]->recurring_schedule_group_id;
        $student = Student::create(collect($this->details('SEARCH-123'))->except('email')->all() + ['user_id' => $this->user('student')->id, 'status' => 'active']);
        $search = route('instructor.subjects.students.search', $group);
        $add = route('instructor.subjects.students.store', $group);

        $this->actingAs($this->teacher)->getJson($search.'?q=SEARCH')->assertOk()->assertJsonPath('students.0.number', 'SEARCH-123')->assertJsonPath('students.0.status', 'available');
        $this->getJson($search.'?q=stud')->assertOk()->assertJsonPath('students.0.name', $student->fullName());
        $this->postJson($add, ['student_id' => $student->id])->assertOk()->assertJsonPath('status', 'draft');
        $this->getJson($search.'?q=SEARCH')->assertJsonPath('students.0.status', 'draft');
        $this->postJson($add, ['student_id' => $student->id])->assertStatus(409);
        $this->assertDatabaseCount('student_schedule_enrollments', 0);
        $this->assertDatabaseCount('enrollment_requests', 0);
        $this->actingAs($this->user('instructor'))->getJson($search.'?q=SEARCH')->assertForbidden();
    }

    public function test_existing_student_search_reports_enrolled_pending_and_excludes_inactive(): void
    {
        $group = $this->meetings[0]->recurring_schedule_group_id;
        $enrolled = Student::create(collect($this->details('LOOK-ENROLLED'))->except('email')->all() + ['user_id' => $this->user('student')->id, 'status' => 'active']);
        $pending = Student::create(collect($this->details('LOOK-PENDING'))->except('email')->all() + ['user_id' => $this->user('student')->id, 'status' => 'active']);
        Student::create(collect($this->details('LOOK-INACTIVE'))->except('email')->all() + ['user_id' => $this->user('student')->id, 'status' => 'inactive']);
        $enrolled->scheduleEnrollments()->create(['recurring_schedule_group_id' => $group]);
        $entry = app(InstructorEnrollmentService::class)->addEntry($this->list, $this->teacher, $pending, null);
        app(InstructorEnrollmentService::class)->submit($this->list, $this->teacher, [['group_id' => $group, 'entry_ids' => [$entry->id], 'confirmed' => true]]);
        $url = route('instructor.subjects.students.search', $group);
        $this->actingAs($this->teacher)->getJson($url.'?q=LOOK-ENROLLED')->assertOk()->assertJsonPath('students.0.status', 'enrolled');
        $this->getJson($url.'?q=LOOK-PENDING')->assertOk()->assertJsonPath('students.0.status', 'pending');
        $this->getJson($url.'?q=LOOK-INACTIVE')->assertOk()->assertJsonCount(0, 'students');
        $this->getJson($url.'?q=x')->assertUnprocessable();
        $this->postJson(route('instructor.subjects.students.store', $group), ['student_id' => $enrolled->id])->assertStatus(409);
        $this->postJson(route('instructor.subjects.students.store', $group), ['student_id' => $pending->id])->assertStatus(409);
    }

    public function test_unified_roster_preserves_rejected_history_and_approved_enrollment_precedence(): void
    {
        $group = $this->meetings[0]->recurring_schedule_group_id;
        $student = Student::create(collect($this->details('ROSTER-1'))->except('email')->all() + ['user_id' => $this->user('student')->id, 'status' => 'active']);
        $entry = app(InstructorEnrollmentService::class)->addEntry($this->list, $this->teacher, $student, null);
        $request = app(InstructorEnrollmentService::class)->submit($this->list, $this->teacher, [['group_id' => $group, 'entry_ids' => [$entry->id], 'confirmed' => true]])->requests()->first();
        app(EnrollmentReviewService::class)->review($request, $this->admin, 'rejected', 'Please correct this record.');
        $response = $this->actingAs($this->teacher)->get(route('instructor.subjects.show', $group))->assertOk()
            ->assertSee('Correct and resubmit')->assertSee('Please correct this record.')
            ->assertDontSee('Enrollment requests for this subject');
        $response->assertViewHas('rows', fn ($rows) => $rows->count() === 1 && $rows->get('ROSTER-1')['status'] === 'rejected');
        $student->scheduleEnrollments()->create(['recurring_schedule_group_id' => $group]);
        $this->get(route('instructor.subjects.show', $group))->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 1 && $rows->get('ROSTER-1')['status'] === 'approved' && count($rows->get('ROSTER-1')['history']) === 1);
    }

}
