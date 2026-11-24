<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\OperationalAttendance;
use App\Services\ScannerCoverage;
use App\Services\ScannerRehearsalReceipts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ScannerRehearsalController extends Controller
{
    private function terminal(Request $r): int
    {
        $enabled = $r->is('attendance-scanner/operational/*') ? OperationalAttendance::enabled()
            : app()->environment('local', 'testing') && config('attendance.scanner_queue_preview') && config('attendance.rehearsal_sync');
        abort_unless($enabled, 404);
        abort_unless($r->isJson() && ! $r->query->count() && strlen($r->getContent()) <= 65536, 422);
        return $r->attributes->get('scanner_terminal')->id;
    }

    private function reply(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'private, no-store');
    }

    public function challenge(Request $r): JsonResponse
    {
        $terminal = $this->terminal($r);
        $live = $r->is('attendance-scanner/operational/*');
        $r->validate(['purpose' => 'required|in:pair,lease,heartbeat,sync'.($live ? ',checkpoint' : '')]);
        $nonce = bin2hex(random_bytes(32));
        $table = DB::table('scanner_device_challenges');
        $table->where('expires_at', '<=', now())->delete();
        $table->insert(['nonce' => $nonce, 'operator_id' => $r->user()->id, 'terminal_id' => $terminal,
            'login_hash' => hash('sha256', $r->session()->getId()), 'purpose' => ($live ? 'op-' : '').$r->purpose, 'expires_at' => now()->addMinute()]);
        return $this->reply(['nonce' => $nonce]);
    }

    /** A fresh challenge authenticates the purpose and the exact request bytes. */
    private function signed(Request $r, string $purpose, callable $action): JsonResponse
    {
        $terminal = $this->terminal($r);
        return DB::transaction(function () use ($r, $purpose, $action, $terminal) {
            abort_unless(DB::table('scanner_terminals')->where('id', $terminal)->where('is_active', true)->lockForUpdate()->first(), 403);
            $nonce = (string) $r->header('X-Scanner-Challenge');
            $challenges = DB::table('scanner_device_challenges')->where('nonce', $nonce)
                ->where('operator_id', $r->user()->id)->where('terminal_id', $terminal)
                ->where('login_hash', hash('sha256', $r->session()->getId()))->where('purpose', ($r->is('attendance-scanner/operational/*') ? 'op-' : '').$purpose)->where('expires_at', '>', now());
            $challenge = $challenges->lockForUpdate()->first();
            abort_unless(strlen($nonce) === 64 && $challenge, 403);
            $r->attributes->set('barrier_ms', \Illuminate\Support\Carbon::parse($challenge->expires_at)->subMinute()->getTimestampMs());
            $device = DB::table('scanner_devices')->where('terminal_id', $terminal)->whereNull('revoked_at')->first();
            if ($purpose === 'pair') {
                $r->validate(['public_key' => 'required|string|max:1024']);
                $der = base64_decode($r->public_key, true);
                abort_unless($der, 422);
                $key = openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----");
                $details = $key ? openssl_pkey_get_details($key) : [];
                abort_unless(($details['type'] ?? null) === OPENSSL_KEYTYPE_RSA && $details['bits'] === 2048, 422);
                $pem = $details['key'];
            } else {
                abort_unless($device && $device->fingerprint === $r->input('device'), 403);
                $pem = $device->public_key;
            }
            $message = $nonce."\n".$purpose."\n".hash('sha256', $r->getContent());
            $signature = base64_decode((string) $r->header('X-Scanner-Signature'), true);
            abort_unless($signature && openssl_verify($message, $signature, $pem, OPENSSL_ALGO_SHA256) === 1, 403);
            $challenges->delete();
            return $this->reply($action($device, $terminal, $pem));
        }, 3);
    }

    public function pair(Request $r): JsonResponse
    {
        return $this->signed($r, 'pair', function ($device, $terminal, $pem) use ($r) {
            $fingerprint = hash('sha256', $pem);
            if ($device && $device->fingerprint === $fingerprint) return ['device' => $fingerprint];
            abort_if($device || DB::table('scanner_devices')->where('fingerprint', $fingerprint)->exists(), 409, 'Revoke the old pairing and use a fresh device key.');
            DB::table('scanner_devices')->insert(['terminal_id' => $terminal, 'paired_by' => $r->user()->id,
                'fingerprint' => $fingerprint, 'public_key' => $pem, 'paired_at' => now()]);
            app(AuditLogger::class)->record('scanner.device_paired', $r->attributes->get('scanner_terminal'), ['device' => $fingerprint], $r->user(), $r);
            return ['device' => $fingerprint];
        });
    }

    public function revoke(Request $r): JsonResponse
    {
        $terminal = $this->terminal($r);
        $r->validate(['device' => 'required|string|size:64', 'session_id' => 'sometimes|uuid']);
        return DB::transaction(function () use ($terminal, $r) {
            DB::table('scanner_terminals')->where('id', $terminal)->lockForUpdate()->first();
            $query = DB::table('scanner_devices')->where('terminal_id', $terminal)->where('fingerprint', $r->device);
            $id = $query->value('id');
            abort_unless($id, 404);
            $sessions = DB::table('scanner_offline_sessions')->where('device_id', $id);
            if ($r->has('session_id')) {
                $sessions->where('id', $r->session_id);
                abort_unless($sessions->exists(), 404);
            } else {
                $query->update(['revoked_at' => now()]);
            }
            $sessions->whereNull('revoked_at')->update(['revoked_at' => now()]);
            app(AuditLogger::class)->record('scanner.authorization_revoked', $r->attributes->get('scanner_terminal'), $r->only('device', 'session_id'), $r->user(), $r);
            return $this->reply(['revoked' => true]);
        });
    }

    public function lease(Request $r): JsonResponse
    {
        return $this->signed($r, 'lease', function ($device, $terminal) use ($r) {
            $r->validate(['clock_id' => 'required|uuid']);
            $anchor = now()->getTimestampMs();
            $expires = $anchor + max(1, config('attendance.lease_hours')) * 3600000;
            $id = (string) Str::uuid();
            DB::table('scanner_offline_sessions')->insert(['id' => $id, 'operator_id' => $r->user()->id,
                'device_id' => $device->id, 'clock_id' => $r->clock_id, 'anchor_ms' => $anchor, 'expires_ms' => $expires]);
            if ($r->is('attendance-scanner/operational/*')) app(ScannerCoverage::class)->initialize($id,$anchor,$expires);
            return ['offline_session_id' => $id, 'terminal_id' => $terminal, 'anchor_ms' => $anchor, 'expires_at' => intdiv($expires, 1000)];
        });
    }

    private function session(Request $r, object $device): object
    {
        $r->validate(['session_id' => 'required|uuid']);
        $lease = DB::table('scanner_offline_sessions')->where('id', $r->session_id)
            ->where('operator_id', $r->user()->id)->where('device_id', $device->id)->lockForUpdate()->first();
        abort_unless($lease, 403);
        abort_unless(isset($lease->history_revision) === $r->is('attendance-scanner/operational/*'), 403);
        abort_if($lease->revoked_at, 403, 'Offline authorization revoked.', ['X-Scanner-Session' => 'revoked']);
        return $lease;
    }

    public function heartbeat(Request $r): JsonResponse
    {
        return $this->signed($r, 'heartbeat', function ($device, $terminal) use ($r) {
            $r->validate(['pending' => 'required|integer|min:0|max:2147483647']);
            $lease = $this->session($r, $device);
            if (isset($lease->history_revision)) DB::table('scanner_offline_sessions')->where('id',$lease->id)->update(['heartbeat_at'=>now()]);
            DB::table('scanner_terminals')->where('id', $terminal)->update(['last_heartbeat_at' => now(), 'reported_pending' => $r->pending]);
            return ['server_ms' => now()->getTimestampMs(), 'terminal_active' => true, 'device_authorized' => true,
                'session_valid' => $lease->expires_ms >= now()->getTimestampMs(), 'ack_sequence' => (int) $lease->ack_sequence, 'sync_available' => true];
        });
    }

    public function sync(Request $r, ScannerRehearsalReceipts $receipts): JsonResponse
    {
        return $this->signed($r, 'sync', function ($device, $terminal) use ($r, $receipts) {
            $lease = $this->session($r, $device);
            if ($lease->expires_ms < now()->getTimestampMs()) {
                $confirmed = (int) $r->session()->get('auth.password_confirmed_at', 0);
                abort_if(now()->timestamp - $confirmed > config('auth.password_timeout', 10800), 423, 'Confirm your password to submit an expired capture session.');
            }
            $r->validate(['events' => 'required|array|list|min:1|max:'.max(1, min(25, config('attendance.batch_size')))]);
            $key = 'scanner-rehearsal-events:'.$terminal;
            $count = count($r->events);
            if (RateLimiter::attempts($key) + $count > 240) {
                abort(429, 'Event rate limit reached.', ['Retry-After' => max(1, RateLimiter::availableIn($key))]);
            }
            RateLimiter::increment($key, 60, $count);
            // Fingerprint original JSON values before Laravel's global string trimming.
            $results = $receipts->batch($lease, json_decode($r->getContent(), true, flags: JSON_THROW_ON_ERROR)['events']);
            $live = isset($lease->history_revision);
            if ($live && collect($results)->contains(fn ($result) => !$result['receipt_id'] || $result['status'] === 'review_required'
                || ($result['reason'] ?? null) === 'invalid_operational_card')) {
                DB::table('scanner_offline_sessions')->where('id',$lease->id)->update(['coverage_disputed'=>true]);
                if (collect($results)->contains(fn ($result) => !$result['receipt_id'] || in_array($result['reason'] ?? null,
                    ['prior_to_verified_coverage','uuid_conflict','sequence_conflict','backward_clock','clock_uncertain','clock_discrepancy'],true))) {
                    $reopened = DB::table('scanner_absence_obligations')->where('session_id',$lease->id)
                        ->whereIn('state',['covered','reconciled'])->update(['state'=>'escalated','decided_at'=>now()]);
                    if ($reopened) DB::afterCommit(fn () => app(\App\Services\AttendanceSummaryCache::class)->invalidateAll());
                }
            }
            if (!$live) DB::table('scanner_terminals')->where('id', $terminal)->update(['last_rehearsal_sync_at' => now()]);
            return ['mode' => $live ? 'operational' : 'rehearsal', 'session_id' => $lease->id, 'receipts' => $results, 'ack_sequence' => (int) $lease->ack_sequence];
        });
    }

    public function checkpoint(Request $r): JsonResponse
    {
        return $this->signed($r,'checkpoint',function ($device) use ($r) {
            $lease = $this->session($r,$device);
            $r->validate(['watermark'=>'required|integer|min:0|max:2147483647']);
            return app(ScannerCoverage::class)->checkpoint($lease,(int)$r->watermark,$r->attributes->get('barrier_ms'));
        });
    }

    public function review(Request $request, string $session): JsonResponse
    {
        abort_unless(OperationalAttendance::enabled(), 404);
        $lease = DB::table('scanner_offline_sessions')->where('id',$session)->whereNotNull('history_revision')
            ->first(['id','device_id','ack_sequence','watermark','covered_ms','expires_ms','coverage_disputed']);
        abort_unless($lease,404);
        $resource = $request->validate(['resource'=>'sometimes|in:receipts,interruptions,obligations,conflicts'])['resource'] ?? 'receipts';
        [$table,$fields] = match ($resource) {
            'receipts'=>['scanner_attendance_receipts',['id','sequence','event_uuid','captured_ms','status','reason','attendance_log_id','audit_log_id']],
            'interruptions'=>['scanner_interruptions',['id','from_ms','until_ms','state','escalate_at','resolved_at']],
            'obligations'=>['scanner_absence_obligations',['id','kind','target','session_date','state','required_ms','decided_at']],
            'conflicts'=>['scanner_receipt_conflicts',['id','event_uuid','reason']],
        };
        return $this->reply(['session'=>$lease,'records'=>DB::table($table)->where('session_id',$session)
            ->orderBy('id')->simplePaginate(50,$fields)]);
    }
}
