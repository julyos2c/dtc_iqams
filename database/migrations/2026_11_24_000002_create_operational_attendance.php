<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Track only attendance inputs. The counter also serializes reads with bulk SQL writes.
    private const INPUTS = [
        'users' => ['status', 'role_id'], 'roles' => ['name', 'role_name'], 'model_has_roles' => [],
        'students' => ['status', 'user_id', 'qr_code', 'section_id', 'course_id', 'enrollment_type'],
        'instructors' => ['user_id', 'qr_code', 'created_at'], 'non_teaching_staff' => ['user_id', 'qr_code', 'created_at'],
        'qr_credentials' => ['status', 'user_id', 'code_hash'], 'schedules' => [], 'subjects' => [], 'sections' => [],
        'student_schedule_enrollments' => [], 'enrollment_workflow_states' => [], 'leave_requests' => [], 'courses' => ['archived_at'],
        'class_cancellation_requests' => [], 'school_events' => ['status', 'starts_at', 'ends_at', 'attendance_mode', 'target_scope'],
        'school_event_targets' => [], 'student_excuse_decisions' => [], 'student_excuse_requests' => [],
    ];

    private const OWNERS = ['users' => 'id', 'model_has_roles' => 'model_id', 'students' => 'user_id',
        'instructors' => 'user_id', 'non_teaching_staff' => 'user_id', 'qr_credentials' => 'user_id',
        'student_schedule_enrollments' => 'student_id', 'leave_requests' => 'user_id', 'student_excuse_requests' => 'user_id'];

    public function up(): void
    {
        Schema::create('scanner_history_state', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary();
            $t->unsignedBigInteger('revision')->default(0);
            $t->boolean('tracking')->default(false);
        });
        DB::table('scanner_history_state')->insert(['id' => 1]);
        Schema::create('scanner_history_versions', function (Blueprint $t) {
            $t->string('subject', 64)->primary();
            $t->unsignedBigInteger('revision')->index();
        });
        Schema::table('scanner_offline_sessions', function (Blueprint $t) {
            $t->unsignedBigInteger('history_revision')->nullable()->index();
            $t->char('policy_hash', 64)->nullable();
            $t->unsignedBigInteger('covered_ms')->nullable();
            $t->unsignedBigInteger('watermark')->nullable();
            $t->boolean('coverage_disputed')->default(false);
            $t->timestamp('heartbeat_at')->nullable();
        });
        Schema::create('scanner_attendance_receipts', function (Blueprint $t) {
            $t->id();
            $t->uuid('event_uuid')->unique();
            $t->foreignUuid('session_id')->constrained('scanner_offline_sessions');
            $t->unsignedBigInteger('sequence');
            $t->char('payload_hash', 64);
            $t->char('credential_hash', 64);
            $t->unsignedBigInteger('captured_ms');
            $t->string('status', 24);
            $t->string('reason', 255)->nullable();
            $t->foreignId('attendance_log_id')->nullable()->constrained();
            $t->foreignId('audit_log_id')->nullable()->constrained();
            $t->json('before_record')->nullable();
            $t->json('after_record')->nullable();
            $t->timestamp('received_at');
            $t->unique(['session_id', 'sequence']);
        });
        Schema::create('scanner_interruptions', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('session_id')->constrained('scanner_offline_sessions');
            $t->unsignedBigInteger('from_ms');
            $t->unsignedBigInteger('until_ms');
            $t->string('state', 24);
            $t->timestamp('escalate_at');
            $t->timestamp('resolved_at')->nullable();
            $t->unique(['session_id', 'from_ms']);
        });
        Schema::create('scanner_absence_obligations', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('session_id')->constrained('scanner_offline_sessions');
            $t->string('kind', 24);
            $t->string('target', 64);
            $t->date('session_date');
            $t->dateTime('opens_at');
            $t->dateTime('ends_at');
            $t->unsignedBigInteger('required_ms');
            $t->string('state', 24)->default('provisional');
            $t->timestamp('decided_at')->nullable();
            $t->unique(['session_id', 'kind', 'target', 'session_date'], 'scanner_obligation_once');
            $t->index(['kind', 'target', 'state', 'session_date'], 'scanner_absence_lookup');
        });
        $sqlite = DB::getDriverName() === 'sqlite';
        foreach (self::INPUTS as $table => $columns) {
            foreach (['INSERT', 'UPDATE', 'DELETE'] as $action) {
                $changes = '';
                if ($action === 'UPDATE' && $columns) {
                    $conditions = array_map(fn ($c) => $sqlite ? "OLD.$c IS NOT NEW.$c" : "NOT (OLD.$c <=> NEW.$c)", $columns);
                    $changes = ' AND ('.implode(' OR ', $conditions).')';
                }
                if ($table === 'class_cancellation_requests') {
                    // Pending and rejected decisions do not remove scan eligibility.
                    $approved = match ($action) {
                        'INSERT' => "NEW.status='approved'", 'DELETE' => "OLD.status='approved'",
                        default => "(OLD.status='approved' OR NEW.status='approved')",
                    };
                    $changes .= " AND $approved";
                }
                $condition = 'id=1 AND tracking=1'.$changes;
                $body = "UPDATE scanner_history_state SET revision=revision+1 WHERE $condition;";
                $owner = self::OWNERS[$table] ?? null;
                $prefix = $table === 'student_schedule_enrollments' ? 'student:' : 'user:';
                $sources = $owner && $action === 'UPDATE' ? ['OLD', 'NEW'] : [$action === 'DELETE' ? 'OLD' : 'NEW'];
                foreach ($sources as $source) {
                    $subject = ! $owner ? "'global'" : ($sqlite ? "'$prefix' || $source.$owner" : "CONCAT('$prefix',$source.$owner)");
                    $body .= " INSERT INTO scanner_history_versions(subject,revision) SELECT COALESCE($subject,'global'), revision FROM scanner_history_state WHERE $condition ".
                        ($sqlite ? 'ON CONFLICT(subject) DO UPDATE SET revision=excluded.revision;' : 'ON DUPLICATE KEY UPDATE scanner_history_versions.revision=VALUES(revision);');
                }
                $trigger = 'iqams_hist_'.$table.'_'.strtolower($action);
                DB::unprepared("CREATE TRIGGER $trigger AFTER $action ON $table FOR EACH ROW BEGIN $body END");
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::INPUTS) as $table) {
            foreach (['insert', 'update', 'delete'] as $action) {
                DB::statement("DROP TRIGGER IF EXISTS iqams_hist_{$table}_{$action}");
            }
        }
        Schema::dropIfExists('scanner_absence_obligations');
        Schema::dropIfExists('scanner_interruptions');
        Schema::dropIfExists('scanner_attendance_receipts');
        Schema::table('scanner_offline_sessions', function (Blueprint $t) {
            $t->dropIndex(['history_revision']);
            $t->dropColumn(['history_revision', 'policy_hash', 'covered_ms', 'watermark', 'coverage_disputed', 'heartbeat_at']);
        });
        Schema::dropIfExists('scanner_history_versions');
        Schema::dropIfExists('scanner_history_state');
    }
};
