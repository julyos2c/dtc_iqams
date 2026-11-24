<?php

namespace App\Services;

use App\Exceptions\AttendanceAlreadyRecordedException;
use App\Models\AttendanceLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationalAttendance
{
    public static function enabled(): bool
    {
        return app()->environment('local', 'testing') && config('attendance.operational_sync');
    }

    public static function policyHash(): string
    {
        return hash('sha256', json_encode([config('app.timezone'), config('attendance')], JSON_THROW_ON_ERROR));
    }

    public static function historyMatches(object $lease, ?User $user = null): bool
    {
        $history = DB::table('scanner_history_state')->where('id', 1)->lockForUpdate()->firstOrFail();
        if (! $history->tracking || $lease->history_revision === null || ! hash_equals($lease->policy_hash, self::policyHash())) {
            return false;
        }
        if ((int) $history->revision === (int) $lease->history_revision) {
            return true;
        }
        if (! $user) {
            return false;
        }
        $subjects = ['global', 'user:'.$user->id];
        if ($user->student) {
            $subjects[] = 'student:'.$user->student->id;
        }

        return ! DB::table('scanner_history_versions')->whereIn('subject', $subjects)->where('revision', '>', $lease->history_revision)->lockForUpdate()->first();
    }

    /** Called inside the signed request's transaction, after reserving the UUID. */
    public function decide(object $receipt, object $lease, array $event): void
    {
        $at = Carbon::createFromTimestampMs($receipt->captured_ms, config('app.timezone'));
        $status = $receipt->status;
        $reason = $receipt->reason;
        $log = null;
        $before = null;
        if ($status === 'received') {
            $matches = self::historyMatches($lease);
            if (! $matches) {
                try {
                    $matches = self::historyMatches($lease, app(QrIdentityResolver::class)->resolve($event['qr_code'], $at));
                } catch (ValidationException) {
                    // A removed or rotated credential cannot establish historical identity.
                }
            }
            if (! $matches) {
                $status = 'review_required';
                $reason = 'eligibility_changed';
            } elseif (DB::table('scanner_attendance_receipts')->where('session_id', $lease->id)
                ->where('credential_hash', $receipt->credential_hash)->where('sequence', '<', $receipt->sequence)
                ->whereIn('status', ['received', 'review_required'])->exists()) {
                $status = 'review_required';
                $reason = 'earlier_unresolved';
            } else {
                try {
                    $log = DB::transaction(function () use ($event, $at, $lease, &$before) {
                        $user = app(QrIdentityResolver::class)->resolve($event['qr_code'], $at);
                        $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                        $day = AttendanceLog::where('user_id', $user->id)->whereBetween('scan_time', [
                            $at->copy()->startOfDay(), $at->copy()->endOfDay(),
                        ]);
                        if ($day->clone()->where(fn ($q) => $q->where('record_origin', 'admin')
                            ->orWhereIn('record_state', ['void', 'voided', 'superseded']))->lockForUpdate()->first()) {
                            throw new OfflineAttendanceReview('manual_record_conflict');
                        }
                        if ($user->isInstructor() || $user->isStaff()) {
                            $calendar = app(PersonnelWorkCalendar::class);
                            if ($calendar->exclusionReason($at, $calendar->context($user, $at, $at))) {
                                throw new OfflineAttendanceReview('personnel_calendar_exclusion');
                            }
                            if ($day->whereNull('schedule_id')->whereNull('school_event_id')->where('scan_time', '>', $at)->lockForUpdate()->first()) {
                                throw new OfflineAttendanceReview('later_personnel_scan');
                            }
                        }
                        $location = DB::table('scanner_devices')->join('scanner_terminals', 'scanner_terminals.id', '=', 'scanner_devices.terminal_id')
                            ->where('scanner_devices.id', $lease->device_id)->value('location');

                        return app(QrAttendanceService::class)->record($event['qr_code'], $location, $at, function ($existing) use (&$before) {
                            if ($existing->record_origin !== 'system' || DB::table('student_excuse_decisions')
                                ->where('attendance_log_id', $existing->id)->where('status', 'approved')->exists()) {
                                throw new OfflineAttendanceReview('protected_attendance');
                            }
                            $before = $existing->getRawOriginal();
                        });
                    });
                    $status = 'confirmed';
                } catch (AttendanceAlreadyRecordedException $duplicate) {
                    $log = $duplicate->attendanceLog;
                    $status = in_array($log->status, ['late', 'present'], true) && $log->record_origin === 'scanner'
                        ? 'duplicate' : 'review_required';
                    $reason = $status === 'duplicate' ? 'attendance_already_recorded' : 'protected_attendance';
                } catch (OfflineAttendanceReview $review) {
                    $status = 'review_required';
                    $reason = $review->getMessage();
                } catch (ValidationException $rejected) {
                    $status = 'rejected';
                    $reason = mb_substr(collect($rejected->errors())->flatten()->first() ?? 'Attendance rejected.', 0, 250);
                }
            }
        }
        $audit = app(AuditLogger::class)->record('offline_attendance.'.$status, $log, [], User::findOrFail($lease->operator_id));
        DB::table('scanner_attendance_receipts')->where('id', $receipt->id)->update([
            'status' => $status, 'reason' => $reason, 'attendance_log_id' => $log?->id, 'audit_log_id' => $audit->id,
            'before_record' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
            'after_record' => $log ? json_encode($log->getRawOriginal(), JSON_THROW_ON_ERROR) : null,
        ]);
        if ($log) {
            DB::afterCommit(fn () => app(AttendanceSummaryCache::class)->invalidateAttendance($log->user_id));
        }
    }
}
