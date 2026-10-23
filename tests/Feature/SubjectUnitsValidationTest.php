<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectUnitsValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_subject_units_must_be_a_whole_number(): void
    {
        $admin = User::factory()->create([
            'role_id' => Role::findByName('admin', 'web')->id,
        ]);

        $this->actingAs($admin)->post(route('subjects.store'), [
            'subject_code' => 'WHOLE101',
            'subject_name' => 'Whole Number Units',
            'units' => '3.0',
        ])->assertSessionHasErrors('units');

        $this->actingAs($admin)->post(route('subjects.store'), [
            'subject_code' => 'WHOLE102',
            'subject_name' => 'Whole Number Units',
            'units' => 3,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(3, Subject::where('subject_code', 'WHOLE102')->firstOrFail()->units);
    }
}
