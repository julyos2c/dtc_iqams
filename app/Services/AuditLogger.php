<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    private const MAX_METADATA_BYTES = 16384;

    private const SENSITIVE_KEY_FRAGMENTS = [
        'password',
        'token',
        'secret',
        'credential',
        'code',
        'qr_code',
        'code_hash',
        'encrypted_code',
        'remember_token',
    ];

    public function record(
        string $action,
        ?Model $subject = null,
        array $metadata = [],
        ?Model $actor = null,
        ?Request $request = null,
    ): AuditLog {
        $consoleEvent = $request === null && app()->runningInConsole();
        $request ??= $consoleEvent ? null : request();
        $actor ??= Auth::user();

        $metadata = $this->eventMetadata($action, $this->sanitize($metadata));
        if ($consoleEvent) {
            $metadata['source'] ??= 'console';
        }
        $metadata = $this->limitMetadata($metadata);

        return AuditLog::create([
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'route_name' => $request?->route()?->getName(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    private function sanitize(array $metadata): array
    {
        $sanitized = [];

        foreach ($metadata as $key => $value) {
            $key = (string) $key;
            $normalizedKey = strtolower(str_replace(['-', ' '], '_', $key));

            if (Arr::first([...self::SENSITIVE_KEY_FRAGMENTS, 'session', 'cookie', 'authorization', 'bearer'], fn (string $fragment) => str_contains($normalizedKey, $fragment))) {
                continue;
            }

            if (is_object($value)) {
                $value = method_exists($value, 'toArray') ? $value->toArray() : '[object]';
            }

            if (is_array($value)) {
                $value = $this->sanitize($value);
            } elseif (is_resource($value)) {
                $value = '[resource]';
            }

            $sanitized[$key] = is_string($value) ? mb_substr($value, 0, 1000) : $value;
        }

        return $sanitized;
    }

    /** Only known event fields and bounded values may enter the audit trail. */
    private function eventMetadata(string $action, array $metadata): array
    {
        $fields = match ($action) {
            'auth.login_failed', 'auth.lockout' => ['reason'],
            'auth.login_succeeded' => ['remember'],
            'account.created' => ['role'],
            'account.profile_updated' => ['profile', 'profile_id'],
            'account.status_changed' => ['from', 'to', 'record', 'profile_id'],
            'account.password_changed', 'account.password_reset_required' => ['source', 'role'],
            'account.invitation_queued' => ['delivery', 'expires_in_minutes'],
            'account.password_link_delivery' => ['outcome', 'attempt'],
            'role.assigned' => ['role', 'compatibility_mirror_updated'],
            'qr.issued' => ['status', 'issued_by'],
            'qr.revoked', 'qr.regenerated' => ['count', 'reason', 'previous_status', 'updated_status'],
            'qr.batch_queued' => ['batch_id', 'filters'],
            'qr.batch_completed' => ['issued', 'skipped', 'failed', 'filters'],
            'enrollment.approved', 'enrollment.rejected', 'enrollment.submitted' => ['batch_id'],
            'enrollment.cutover_enabled' => ['legacy_relationships'],
            'registration.approved' => ['student_id'],
            'registration.enrollment_rejected' => ['enrollment_request_id'],
            'account.activated' => ['registration_request_id'],
            'roster.students_copied' => ['students_added'],
            'attendance.corrected' => ['operation'],
            'leave.reviewed', 'security_flag.reviewed', 'class_cancellation.reviewed' => ['status'],
            'leave.overlap_resolved' => ['retained_request_id', 'new_status'],
            'record.created', 'record.updated', 'record.archived', 'record.voided' => ['record', 'scope', 'ids'],
            'integrity.reconciled' => ['attendance_groups', 'leave_groups', 'placement_decisions', 'section_groups', 'schedule_groups'],
            'report.student_absences_exported' => ['type', 'filters', 'search_applied', 'result'],
            default => [],
        };

        $allowedStrings = ['invalid_credentials', 'inactive_account', 'rate_limited', 'admin', 'student', 'staff', 'instructor', 'active', 'inactive', 'revoked', 'reissuance', 'role_change', 'Lost ID', 'Stolen ID', 'Damaged ID', 'Suspected Compromise', 'Other', 'created', 'updated', 'approved', 'rejected', 'cancelled', 'reviewed', 'confirmed', 'dismissed', 'profile', 'student_profile', 'authenticated_change', 'email_link', 'console', 'password_reset_broker', 'inactive_or_missing', 'superseded', 'attempt_failed', 'failed', 'submitted_to_transport', 'throttled', 'recurring_group', 'success', 'summary', 'detailed', 'subject', 'section', 'course', 'department', 'schedule', 'school_event', 'office_unit', 'attendance_log', 'student_account', 'instructor_account', 'staff_account'];
        $numericFields = ['profile_id', 'issued_by', 'count', 'issued', 'skipped', 'failed', 'student_id', 'enrollment_request_id', 'registration_request_id', 'legacy_relationships', 'students_added', 'retained_request_id', 'expires_in_minutes', 'attempt', 'attendance_groups', 'leave_groups', 'placement_decisions', 'section_groups', 'schedule_groups'];
        $result = [];
        foreach ($fields as $field) {
            $value = $metadata[$field] ?? null;
            if (in_array($field, $numericFields, true) && is_int($value) && $value >= 0) {
                $result[$field] = $value;
            } elseif (in_array($field, ['remember', 'compatibility_mirror_updated', 'search_applied'], true) && is_bool($value)) {
                $result[$field] = $value;
            } elseif ($field === 'batch_id' && ((is_int($value) && $value > 0) || (is_string($value) && preg_match('/\A[0-9a-f-]{36}\z/i', $value)))) {
                $result[$field] = $value;
            } elseif ($field === 'ids' && is_array($value) && array_is_list($value) && count($value) <= 1000 && collect($value)->every(fn ($id) => is_int($id) && $id > 0)) {
                $result[$field] = $value;
            } elseif ($field === 'filters' && is_array($value)) {
                $result[$field] = $this->safeFilters($value);
            } elseif (is_string($value) && in_array($value, $allowedStrings, true)) {
                $result[$field] = $value;
            }
        }

        return $result;
    }

    private function safeFilters(array $filters): array
    {
        $safe = [];
        foreach (['department_id', 'office_unit_id', 'course_id', 'section_id', 'department', 'course', 'section', 'subject'] as $key) {
            $value = $filters[$key] ?? null;
            if ((is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value > 0) {
                $safe[$key] = (int) $value;
            }
        }
        if (in_array($filters['role'] ?? null, ['student', 'instructor', 'staff'], true)) {
            $safe['role'] = $filters['role'];
        }
        foreach (['risk' => ['normal', 'at-risk'], 'type' => ['summary', 'detailed']] as $key => $values) {
            if (in_array($filters[$key] ?? null, $values, true)) {
                $safe[$key] = $filters[$key];
            }
        }
        foreach (['from', 'to'] as $key) {
            if (is_string($filters[$key] ?? null) && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $filters[$key])) {
                $safe[$key] = $filters[$key];
            }
        }

        return $safe;
    }

    private function limitMetadata(array $metadata): array
    {
        if (strlen((string) json_encode($metadata, JSON_UNESCAPED_SLASHES)) <= self::MAX_METADATA_BYTES) {
            return $metadata;
        }

        $metadata['metadata_truncated'] = true;

        while (strlen((string) json_encode($metadata, JSON_UNESCAPED_SLASHES)) > self::MAX_METADATA_BYTES) {
            $keys = array_values(array_filter(array_keys($metadata), fn ($key) => $key !== 'metadata_truncated'));
            $removable = end($keys);

            if ($removable === false) {
                break;
            }

            unset($metadata[$removable]);
            $metadata['metadata_truncated'] = true;
        }

        return $metadata;
    }
}
