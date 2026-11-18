<?php
namespace Tests\Feature;

use App\Models\{AttendanceLog, Course, Department, Instructor, Role, Schedule, Section, Student, StudentExcuseDecision, StudentExcuseRequest, Subject, User};
use App\Services\StudentAttendanceSummary;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentExcuseRequestTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(RolePermissionSeeder::class); Storage::fake('local'); }
    private function fixture(): array
    {
        $department = Department::create(['department_code'=>'EX', 'department_name'=>'Excuse']);
        $course = Course::create(['department_id'=>$department->id, 'course_code'=>'EX', 'course_name'=>'Excuse']);
        $section = Section::create(['course_id'=>$course->id, 'section_name'=>'EX-A', 'school_year'=>'2026-2027', 'semester'=>'1st']);
        $subject = Subject::create(['subject_code'=>'EX101', 'subject_name'=>'Excuse', 'units'=>3]);
        $teacher = User::factory()->create(['role_id'=>Role::findByName('instructor','web')->id]);
        $instructor = Instructor::create(['user_id'=>$teacher->id, 'department_id'=>$department->id, 'employee_no'=>'EX-I', 'first_name'=>'Test', 'last_name'=>'Teacher']);
        $schedule = Schedule::create(['subject_id'=>$subject->id, 'instructor_id'=>$instructor->id, 'section_id'=>$section->id, 'day'=>'monday', 'start_time'=>'08:00', 'end_time'=>'10:00', 'room'=>'1']);
        $user = User::factory()->create(['role_id'=>Role::findByName('student','web')->id]);
        $student = Student::create(['user_id'=>$user->id, 'student_no'=>'EX-S', 'first_name'=>'Test', 'last_name'=>'Student', 'course_id'=>$course->id, 'section_id'=>$section->id, 'status'=>'active']);
        $log = AttendanceLog::create(['user_id'=>$user->id, 'schedule_id'=>$schedule->id, 'attendance_type'=>'time_in', 'scan_time'=>'2026-08-03 09:00:00', 'status'=>'absent']);
        return [$student,$teacher,$log];
    }
    public function test_submission_review_and_private_attachment(): void
    {
        [$student,$teacher,$log] = $this->fixture();
        $this->actingAs($student->user)->get(route('student.excuses.index'))->assertOk();
        $date = now()->addWeeks(2)->next('Monday')->toDateString();
        $this->post(route('student.excuses.store'), ['schedule_ids'=>[$log->schedule_id], 'occurrence_date'=>$date, 'reason_type'=>'illness', 'explanation'=>'Fever', 'attachment'=>UploadedFile::fake()->create('note.pdf', 20, 'application/pdf')])->assertRedirect(route('student.excuses.index'));
        $excuse = StudentExcuseRequest::firstOrFail(); $decision = $excuse->decisions()->firstOrFail();
        Storage::disk('local')->assertExists($excuse->attachment_path);
        $this->post(route('student.excuses.store'), ['schedule_ids'=>[$log->schedule_id], 'occurrence_date'=>$date, 'reason_type'=>'illness', 'explanation'=>'Again'])->assertSessionHasErrors('schedule_ids');
        $other = User::factory()->create(['role_id'=>Role::findByName('student','web')->id]);
        $this->actingAs($other)->get(route('student.excuses.attachment',$excuse))->assertForbidden();
        $this->actingAs($teacher)->get(route('instructor.excuses.index'))->assertOk()->assertSee('Fever');
        $this->get(route('instructor.excuses.attachment',$excuse))->assertOk();
        $this->patch(route('instructor.excuses.decide',$decision), ['status'=>'approved'])->assertSessionHasNoErrors();
        $this->assertSame('absent', $log->fresh()->status);
        $this->assertDatabaseHas('student_excuse_decisions', ['id'=>$decision->id, 'status'=>'approved']);
        $this->assertDatabaseHas('audit_logs', ['action'=>'student_excuse.approved']);
    }
    public function test_rejection_requires_remarks_and_allows_resubmission(): void
    {
        [$student,$teacher,$log] = $this->fixture();
        $payload = ['schedule_ids'=>[$log->schedule_id], 'occurrence_date'=>now()->addWeeks(2)->next('Monday')->toDateString(), 'reason_type'=>'emergency', 'explanation'=>'Emergency'];
        $this->actingAs($student->user)->post(route('student.excuses.store'),$payload)->assertSessionHasNoErrors();
        $decision = StudentExcuseDecision::firstOrFail();
        $this->actingAs($teacher)->patch(route('instructor.excuses.decide',$decision),['status'=>'rejected'])->assertSessionHasErrors('remarks');
        $this->patch(route('instructor.excuses.decide',$decision),['status'=>'rejected','remarks'=>'Please provide more detail'])->assertSessionHasNoErrors();
        $this->assertSame('absent',$log->fresh()->status);
        $this->actingAs($student->user)->post(route('student.excuses.store'),$payload)->assertSessionHasNoErrors();
        $this->assertSame(2, StudentExcuseDecision::count());
    }

    public function test_other_instructors_cannot_review_and_changed_attendance_cannot_be_approved(): void
    {
        [$student, $teacher, $log] = $this->fixture();
        $this->actingAs($student->user)->post(route('student.excuses.store'), ['schedule_ids'=>[$log->schedule_id], 'occurrence_date'=>now()->addWeeks(2)->next('Monday')->toDateString(), 'reason_type'=>'illness', 'explanation'=>'Ill']);
        $decision = StudentExcuseDecision::firstOrFail();
        $other = User::factory()->create(['role_id'=>Role::findByName('instructor','web')->id]);
        Instructor::create(['user_id'=>$other->id, 'department_id'=>$teacher->instructor->department_id, 'employee_no'=>'EX-OTHER', 'first_name'=>'Other', 'last_name'=>'Teacher']);
        $this->actingAs($other)->patch(route('instructor.excuses.decide', $decision), ['status'=>'approved'])->assertForbidden();
        $this->get(route('instructor.excuses.attachment', $decision->excuseRequest))->assertForbidden();
        $log->update(['status'=>'present']);
        $this->actingAs($teacher)->patch(route('instructor.excuses.decide', $decision), ['status'=>'approved'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $decision->fresh()->status);
        $this->assertSame('present', $log->fresh()->status);
    }

    public function test_approved_advance_request_marks_the_occurrence_excused(): void
    {
        [$student, $teacher, $log] = $this->fixture();
        $date = now()->addWeeks(2)->next('Monday')->startOfDay();
        $this->actingAs($student->user)->post(route('student.excuses.store'), [
            'schedule_ids' => [$log->schedule_id], 'occurrence_date' => $date->toDateString(),
            'reason_type' => 'emergency', 'explanation' => 'Emergency meeting',
        ])->assertSessionHasNoErrors();
        $decision = StudentExcuseDecision::latest('id')->firstOrFail();
        $this->actingAs($teacher)->patch(route('instructor.excuses.decide', $decision), ['status' => 'approved'])->assertSessionHasNoErrors();
        $occurrence = app(\App\Services\ScheduleOccurrenceResolver::class)->forDate($log->schedule, $date);
        app(\App\Services\StudentAbsenceService::class)->markOccurrenceDue($occurrence, $date->copy()->setTime(9, 30));
        $this->assertDatabaseHas('attendance_logs', ['user_id' => $student->user_id, 'schedule_id' => $log->schedule_id, 'attendance_date' => $date->toDateString(), 'status' => 'excused']);
    }

    public function test_late_approval_changes_the_recorded_absence_to_excused(): void
    {
        [$student, $teacher, $log] = $this->fixture();
        $date = now()->addWeeks(2)->next('Monday')->startOfDay();
        $this->actingAs($student->user)->post(route('student.excuses.store'), [
            'schedule_ids' => [$log->schedule_id], 'occurrence_date' => $date->toDateString(),
            'reason_type' => 'emergency', 'explanation' => 'Emergency meeting',
        ])->assertSessionHasNoErrors();
        $decision = StudentExcuseDecision::latest('id')->firstOrFail();
        $occurrence = app(\App\Services\ScheduleOccurrenceResolver::class)->forDate($log->schedule, $date);
        app(\App\Services\StudentAbsenceService::class)->markOccurrenceDue($occurrence, $date->copy()->setTime(9, 30));
        $this->assertDatabaseHas('attendance_logs', ['user_id' => $student->user_id, 'attendance_date' => $date->toDateString(), 'status' => 'absent']);
        $this->actingAs($teacher)->patch(route('instructor.excuses.decide', $decision), ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attendance_logs', ['user_id' => $student->user_id, 'attendance_date' => $date->toDateString(), 'status' => 'excused']);
    }
}
