<?php

namespace Tests\Integration;

use App\Models\ClassList;
use App\Models\Course;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use App\Services\EnrollmentWorkflow;
use App\Services\InstructorEnrollmentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Opt-in, destructive only inside the explicitly named disposable database. */
class EnrollmentApprovalConcurrencyTest extends TestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if (! $app->environment('testing') || config('database.default') !== 'mysql'
            || config('database.connections.mysql.database') !== 'iqams_enrollment_test'
            || config('database.connections.mysql.host') !== '127.0.0.1'
            || (string) config('database.connections.mysql.port') !== '13317'
            || config('database.connections.mysql.url')) {
            throw new \RuntimeException('This opt-in test requires the disposable iqams_enrollment_test MySQL database on 127.0.0.1:13317.');
        }

        return $app;
    }

    public function test_simultaneous_first_approvals_create_one_canonical_account_and_qr(): void
    {
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        $this->seed(RolePermissionSeeder::class);
        DB::table('enrollment_workflow_states')->where('id', 1)->update(['enabled_at' => now()]);
        app(EnrollmentWorkflow::class)->refresh();
        $admin = User::factory()->create(['role_id' => Role::findByName('admin')->id]);
        $department = Department::create(['department_code' => 'RACE', 'department_name' => 'Concurrency']);
        $course = Course::create(['department_id' => $department->id, 'course_code' => 'RACE', 'course_name' => 'Concurrency']);
        $section = Section::create(['course_id' => $course->id, 'section_name' => 'Race A', 'school_year' => '2026-2027', 'semester' => '1st']);
        $requests = [];
        foreach ([1, 2] as $index) {
            $teacher = User::factory()->create(['role_id' => Role::findByName('instructor')->id]);
            $instructor = Instructor::create(['user_id' => $teacher->id, 'department_id' => $department->id, 'employee_no' => 'RACE-'.$index, 'first_name' => 'Teacher', 'last_name' => (string) $index]);
            $subject = Subject::create(['subject_code' => 'RACE-'.$index, 'subject_name' => 'Race '.$index, 'units' => 3]);
            $schedule = Schedule::create(['subject_id' => $subject->id, 'section_id' => $section->id, 'instructor_id' => $instructor->id, 'recurring_schedule_group_id' => (string) Str::uuid(), 'day' => 'monday', 'start_time' => '08:00', 'end_time' => '09:00', 'room' => 'R'.$index]);
            $list = ClassList::create(['name' => 'Race '.$index, 'section_id' => $section->id, 'instructor_id' => $instructor->id]);
            $service = app(InstructorEnrollmentService::class);
            $entry = $service->addEntry($list, $teacher, null, ['student_no' => 'SAME-001', 'first_name' => 'Same', 'last_name' => 'Student', 'email' => 'same@example.test', 'course_id' => $course->id, 'section_id' => $section->id, 'enrollment_type' => 'regular']);
            $requests[] = $service->submit($list, $teacher, [['group_id' => $schedule->recurring_schedule_group_id, 'entry_ids' => [$entry->id], 'confirmed' => true]])->requests()->first()->id;
        }
        // Two different approvals plus a repeated approval contend for one identity.
        $workers = [];
        $startAt = microtime(true) + 3;
        foreach ([$requests[0], $requests[1], $requests[0]] as $id) {
            $process = proc_open([PHP_BINARY, base_path('tests/Support/approve-enrollment.php')], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
            $this->assertIsResource($process);
            fwrite($pipes[0], json_encode(['start_at' => $startAt, 'request_id' => $id, 'actor_id' => $admin->id], JSON_THROW_ON_ERROR));
            fclose($pipes[0]);
            $workers[] = [$process, $pipes];
        }
        $activations = 0;
        foreach ($workers as [$process, $pipes]) {
            stream_set_timeout($pipes[1], 30);
            $output = stream_get_contents($pipes[1]);
            $metadata = stream_get_meta_data($pipes[1]);
            if ($metadata['timed_out']) {
                proc_terminate($process);
            }
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($process);
            $this->assertFalse($metadata['timed_out'], 'Approval worker timed out.');
            $this->assertSame(0, $exit, 'Approval worker failed.');
            $result = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('approved', $result['status']);
            $activations += (int) $result['activated'];
        }
        $this->assertSame(1, $activations);
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('qr_credentials', 1);
        $this->assertDatabaseCount('student_schedule_enrollments', 2);
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'enrollment.approved')->count());
    }
}
