<?php

namespace Tests\Support;

use App\Models\Course;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\NonTeachingStaff;
use App\Models\OfficeUnit;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;

trait ScannerAttendanceFixtures
{
    private function studentWithSchedule(): array
    {
        $department = Department::create(['department_code' => 'IT', 'department_name' => 'Information Technology']);
        $course = Course::create(['department_id' => $department->id, 'course_code' => 'BSIT', 'course_name' => 'BS Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'section_name' => 'BSIT-4A', 'school_year' => '2026-2027', 'semester' => '1st']);
        $studentRole = Role::create(['role_name' => 'student']);
        $user = $this->createUser(['role_id' => $studentRole->id, 'status' => 'active']);
        Student::create([
            'user_id' => $user->id,
            'student_no' => 'STU-001',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'qr_code' => 'STU-001',
            'section_id' => $section->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        $instructorUser = $this->createUser(['role_id' => Role::create(['role_name' => 'instructor'])->id]);
        $instructor = Instructor::create([
            'user_id' => $instructorUser->id,
            'department_id' => $department->id,
            'employee_no' => 'INS-001',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'qr_code' => 'INS-001',
        ]);
        $subject = Subject::create(['subject_code' => 'CAP2', 'subject_name' => 'Capstone 2', 'units' => 3]);
        $schedule = Schedule::create([
            'subject_id' => $subject->id,
            'instructor_id' => $instructor->id,
            'section_id' => $section->id,
            'day' => 'monday',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'room' => 'Lab 1',
        ]);

        return [$user, $schedule];
    }

    private function createPersonnel(string $roleName, string $qrCode): User
    {
        $role = Role::firstOrCreate(['role_name' => $roleName]);
        $user = $this->createUser(['role_id' => $role->id, 'status' => 'active']);

        if ($roleName === 'instructor') {
            $department = Department::firstOrCreate(
                ['department_code' => 'GEN'],
                ['department_name' => 'General Department'],
            );
            Instructor::create([
                'user_id' => $user->id,
                'department_id' => $department->id,
                'employee_no' => 'INS-'.str_pad((string) $user->id, 3, '0', STR_PAD_LEFT),
                'first_name' => 'Test',
                'last_name' => 'Instructor',
                'qr_code' => $qrCode,
            ]);
        } else {
            $officeUnit = OfficeUnit::firstOrCreate(
                ['code' => 'REG'],
                ['name' => 'Registrar', 'is_active' => true],
            );
            NonTeachingStaff::create([
                'user_id' => $user->id,
                'office_unit_id' => $officeUnit->id,
                'employee_no' => 'EMP-'.str_pad((string) $user->id, 3, '0', STR_PAD_LEFT),
                'first_name' => 'Test',
                'last_name' => 'Staff',
                'qr_code' => $qrCode,
            ]);
        }

        return $user;
    }

    private function createUser(array $attributes): User
    {
        /** @var User $user */
        $user = User::factory()->create($attributes);

        return $user;
    }
}
