<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_workflow_states', function (Blueprint $table) {
            $table->id();
            $table->timestamp('enabled_at')->nullable();
        });
        DB::table('enrollment_workflow_states')->insert(['id' => 1]);
        Schema::table('student_schedule_enrollments', function (Blueprint $table) {
            $table->timestamp('effective_at')->nullable();
            $table->string('origin', 30)->default('legacy');
        });
        Schema::create('student_registration_identities', function (Blueprint $table) {
            $table->id();
            $table->string('student_no', 50)->unique();
            $table->foreignId('student_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('student_registration_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('identity_id')->constrained('student_registration_identities')->restrictOnDelete();
            $table->foreignId('instructor_id')->constrained()->restrictOnDelete();
            $table->json('details');
            $table->string('status', 20)->default('draft');
            $table->foreignId('student_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['instructor_id', 'status']);
        });
        Schema::create('class_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->timestamps();
            $table->index(['instructor_id', 'section_id']);
        });
        Schema::create('class_list_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('identity_id')->constrained('student_registration_identities')->restrictOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('registration_request_id')->nullable()->constrained('student_registration_requests')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['class_list_id', 'identity_id']);
        });
        Schema::create('class_list_submission_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_list_id')->constrained()->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('enrollment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('class_list_submission_batches')->restrictOnDelete();
            $table->foreignId('identity_id')->constrained('student_registration_identities')->restrictOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('registration_request_id')->nullable()->constrained('student_registration_requests')->restrictOnDelete();
            $table->foreignId('instructor_id')->constrained()->restrictOnDelete();
            $table->uuid('recurring_schedule_group_id')->index();
            $table->json('snapshot');
            $table->string('status', 20)->default('pending');
            $table->string('pending_key', 64)->nullable()->unique();
            $table->foreignId('previous_request_id')->nullable()->constrained('enrollment_requests')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->index(['instructor_id', 'status']);
            $table->index(['status', 'created_at']);
        });
        DB::table('permissions')->insertOrIgnore(['name' => 'review-enrollment-requests', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        $permissionId = DB::table('permissions')->where('name', 'review-enrollment-requests')->where('guard_name', 'web')->value('id');
        foreach (DB::table('roles')->where('name', 'admin')->where('guard_name', 'web')->pluck('id') as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        throw new RuntimeException('Enrollment workflow contains identity and approval history. Use a reviewed forward migration or restore.');
    }
};
