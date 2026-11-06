<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\NonTeachingStaff;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Tests\TestCase;

class UserManagementSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_search_and_authorization_across_management_pages(): void
    {
        $admin = $this->user('admin');
        $department = Department::create(['department_code' => 'DTC', 'department_name' => 'Department']);
        $course = Course::create(['department_id' => $department->id, 'course_code' => 'TEST', 'course_name' => 'Test']);
        foreach ([['students.index', Student::class, 'student_no', ['course_id' => $course->id]], ['instructors.index', Instructor::class, 'employee_no', ['department_id' => $department->id]], ['non-teaching-staff.index', NonTeachingStaff::class, 'employee_no', []]] as [$route, $model, $number, $extra]) {
            $user = $this->user('student', ['email' => uniqid().'@example.test']);
            $other = $this->user('student');
            $record = $model::create(array_merge($extra, ['user_id' => $user->id, $number => 'ABC-123', 'first_name' => 'Maria', 'middle_name' => null, 'last_name' => 'Reyes']));
            $model::create(array_merge($extra, ['user_id' => $other->id, $number => 'XYZ-456', 'first_name' => 'Other', 'last_name' => 'Person']));
            foreach (['maria reyes', 'MARIA', 'abc-123', 'BC-12', strtoupper($user->email), substr($user->email, 2, 5)] as $term) {
                $response = $this->actingAs($admin)->get(route($route, ['search' => $term]));
                $response->assertOk()->assertSee('ABC-123')->assertDontSee('XYZ-456');
                $this->assertSame([$user->id], $response->viewData(match ($route) { 'students.index' => 'studentUserIds', 'instructors.index' => 'instructorUserIds', default => 'staffUserIds' })->all());
            }
            $this->actingAs($admin)->get(route($route, ['search' => 'nothing-matches']))->assertOk()->assertSee('No users found matching your search');
            $this->actingAs($admin)->get(route($route, ['search' => '']))->assertOk()->assertSee('ABC-123')->assertSee('XYZ-456');
            $this->actingAs($other)->get(route($route, ['search' => 'Maria']))->assertForbidden();
        }
    }

    public function test_search_pagination_retains_query(): void
    {
        $admin = $this->user('admin');
        for ($i = 0; $i < 12; $i++) {
            $user = $this->user('staff');
            NonTeachingStaff::create(['user_id' => $user->id, 'employee_no' => 'MATCH-'.$i, 'first_name' => 'Sample', 'last_name' => 'Person']);
        }
        $response = $this->actingAs($admin)->get(route('non-teaching-staff.index', ['search' => 'MATCH', 'page' => 2]));
        $response->assertOk()->assertSee('search=MATCH', false);
        $this->assertSame(2, $response->viewData('staffMembers')->currentPage());
        $this->assertCount(2, $response->viewData('staffMembers')->items());
    }

    private function user(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role_id' => Role::firstOrCreate(['role_name' => $role])->id, 'status' => 'active'], $attributes));
    }
}

