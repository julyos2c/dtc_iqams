<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditLogSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_and_reseeding_preserve_revocation(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $role = Role::findByName('admin', 'web');
        $admin = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $this->get(route('admin.audit-logs.index'))->assertRedirect();
        $this->actingAs($admin)->get(route('admin.audit-logs.index'))->assertOk();
        $student = User::factory()->create(['role_id' => Role::findByName('student', 'web')->id, 'status' => 'active']);
        $this->actingAs($student)->get(route('admin.audit-logs.index'))->assertForbidden();
        $role->revokePermissionTo('view-audit-logs');
        $this->seed(RolePermissionSeeder::class);
        $this->actingAs($admin->fresh())->get(route('admin.audit-logs.index'))->assertForbidden();
    }

    public function test_metadata_is_restricted_for_known_and_unknown_events(): void
    {
        $logger = app(AuditLogger::class);
        $log = $logger->record('qr.batch_completed', metadata: [
            'issued' => 2, 'skipped' => 1, 'failed' => 0,
            'filters' => ['role' => 'student', 'session_id' => 'session-secret', 'password' => 'password-secret', 'nested' => ['authorization' => 'Bearer secret']],
            'review_notes' => 'reset-token and password-secret',
        ]);
        $this->assertSame(2, $log->metadata['issued']);
        $this->assertSame(['role' => 'student'], $log->metadata['filters']);
        $this->assertStringNotContainsString('secret', json_encode($log->metadata));
        $unknown = $logger->record('unknown.event', metadata: ['session_cookie' => 'secret', 'notes' => 'password-secret']);
        $this->assertSame(['source' => 'console'], $unknown->metadata);
        $login = $logger->record('auth.login_failed', metadata: ['username' => 'password-secret', 'reason' => 'invalid_credentials']);
        $this->assertArrayNotHasKey('username', $login->metadata);
        $enrollment = $logger->record('enrollment.rejected', metadata: ['review_notes' => 'password-secret']);
        $this->assertArrayNotHasKey('review_notes', $enrollment->metadata);
    }

    public function test_bulk_eloquent_mutations_are_rejected(): void
    {
        $log = app(AuditLogger::class)->record('test.bulk');
        foreach (['update', 'delete', 'forceDelete', 'increment', 'incrementEach', 'decrementEach', 'upsert', 'updateOrInsert', 'truncate', 'updateFrom'] as $operation) {
            try {
                $query = AuditLog::whereKey($log->id);
                match ($operation) {
                    'update' => $query->update(['action' => 'changed']),
                    'delete' => $query->delete(),
                    'forceDelete' => $query->forceDelete(),
                    'increment' => $query->increment('id'),
                    'incrementEach' => $query->incrementEach(['id' => 1]),
                    'decrementEach' => $query->decrementEach(['id' => 1]),
                    'upsert' => $query->upsert([['id' => $log->id, 'action' => 'changed']], ['id']),
                    'updateOrInsert' => $query->updateOrInsert(['id' => $log->id], ['action' => 'changed']),
                    'truncate' => $query->truncate(),
                    'updateFrom' => $query->updateFrom(['action' => 'changed']),
                };
                $this->fail("{$operation} should be rejected");
            } catch (LogicException $exception) {
                $this->assertSame('Audit logs are append-only.', $exception->getMessage());
            }
        }
        $this->assertSame('test.bulk', $log->fresh()->action);
    }

    public function test_deactivation_preserves_actor_and_history_and_model_deletion_is_blocked(): void
    {
        $actor = User::factory()->create(['status' => 'active']);
        $log = app(AuditLogger::class)->record('auth.login_succeeded', $actor, [], $actor);
        $actor->update(['status' => 'inactive']);
        $this->assertSame($actor->id, $log->fresh()->actor_id);
        $this->assertSame($actor->name, $log->fresh()->actor_label);
        try {
            $actor->delete();
            $this->fail('Account deletion should be blocked.');
        } catch (LogicException $exception) {
            $this->assertSame('Accounts are deactivated and cannot be deleted.', $exception->getMessage());
        }
        $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'actor_id' => $actor->id]);
    }

    public function test_direct_actor_deletion_nulls_attribution_but_preserves_the_event(): void
    {
        $actor = User::factory()->create();
        $log = app(AuditLogger::class)->record('auth.login_succeeded', $actor, [], $actor);
        // This intentionally bypasses model guards in the isolated test database.
        DB::table('users')->where('id', $actor->id)->delete();
        $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'action' => 'auth.login_succeeded', 'actor_id' => null]);
    }
}
