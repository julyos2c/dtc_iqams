<?php

namespace App\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ScannerRehearsalReceipts
{
    /** The caller holds the terminal and session row locks throughout this batch. */
    public function batch(object $lease, array $events): array
    {
        $results = [];
        $valid = [];
        foreach ($events as $index => $event) {
            $rules = [
                'event_uuid' => 'required|uuid', 'sequence' => 'required|integer|min:1|max:2147483647',
                'qr_code' => 'required|string|max:255', 'captured_at' => 'required|date_format:Y-m-d\\TH:i:s.v\\Z',
                'clock_id' => 'required|uuid', 'elapsed_ms' => 'present|nullable|integer|min:0|max:2147483647',
            ];
            if (! is_array($event) || array_diff_key($event, $rules) || Validator::make($event, $rules)->fails()) {
                $results[$index] = ['index' => $index, 'status' => 'rejected', 'reason' => 'malformed_event', 'receipt_id' => null];
                continue;
            }
            $valid[$index] = $event;
        }
        // Sequence gives stable capture order when wall clocks move backwards.
        uasort($valid, fn ($a, $b) => $a['sequence'] <=> $b['sequence']);
        foreach ($valid as $index => $event) {
            $results[$index] = $this->receive($lease, $event) + ['index' => $index];
        }
        ksort($results);

        return array_values($results);
    }

    private function receive(object $lease, array $event): array
    {
        $operational = isset($lease->history_revision);
        $name = $operational ? 'scanner_attendance_receipts' : 'scanner_rehearsal_receipts';
        $sequence = (int) $event['sequence'];
        $uuid = strtolower($event['event_uuid']);
        ksort($event);
        $digest = hash('sha256', json_encode([$lease->id, $event], JSON_THROW_ON_ERROR));
        $table = DB::table($name);
        $scope = $table->clone()->where('session_id', $lease->id);
        $existing = $table->clone()->where('event_uuid', $uuid)->first();
        if ($existing && hash_equals($existing->payload_hash, $digest)) {
            return $this->receipt($existing);
        }
        if ($existing || $scope->clone()->where('sequence', $sequence)->exists()) {
            $reason = $existing ? 'uuid_conflict' : 'sequence_conflict';
            return $this->conflict($lease->id, $uuid, $digest, $reason);
        }

        $capture = (int) (new DateTimeImmutable($event['captured_at']))->format('Uv');
        $now = now()->getTimestampMs();
        $tolerance = max(0, config('attendance.clock_tolerance_seconds')) * 1000;
        $reason = match (true) {
            $operational && (str_starts_with(strtoupper(trim($event['qr_code'])), 'TEST-') || preg_match('/[\x00-\x1F\x7F]/', $event['qr_code'])) => 'invalid_operational_card',
            !$operational && ! preg_match('/^TEST-[A-Za-z0-9_-]{1,100}$/D', $event['qr_code']) => 'invalid_test_card',
            $capture < $lease->anchor_ms || $capture > $lease->expires_ms => 'outside_lease',
            $operational && $capture < $lease->covered_ms => 'prior_to_verified_coverage',
            $capture > $now + $tolerance => 'future_capture',
            $event['clock_id'] !== $lease->clock_id || $event['elapsed_ms'] === null => 'clock_uncertain',
            abs($capture - ($lease->anchor_ms + $event['elapsed_ms'])) > $tolerance => 'clock_discrepancy',
            $sequence > $lease->ack_sequence + 1 => 'sequence_gap',
            $scope->clone()->where('sequence', '<', $sequence)->where('captured_ms', '>', $capture)->exists(),
            $scope->clone()->where('sequence', '>', $sequence)->where('captured_ms', '<', $capture)->exists() => 'backward_clock',
            default => null,
        };
        $status = in_array($reason, ['invalid_test_card','invalid_operational_card'], true) ? 'rejected'
            : ($reason ? 'review_required' : ($operational ? 'received' : 'synchronized'));
        $identity = $operational ? ['credential_hash'=>hash_hmac('sha256',trim($event['qr_code']),config('app.key'))] : [];
        $inserted = $table->insertOrIgnore($identity + ['session_id' => $lease->id, 'event_uuid' => $uuid,
            'sequence' => $sequence, 'captured_ms' => max(0, $capture), 'payload_hash' => $digest,
            'status' => $status, 'reason' => $reason, 'received_at' => now()]);
        // A concurrent claim of the same UUID on another terminal may win.
        $row = $table->clone()->where('event_uuid', $uuid)->lockForUpdate()->firstOrFail();
        if (!$inserted && !hash_equals($row->payload_hash, $digest)) {
            return $this->conflict($lease->id, $uuid, $digest, 'uuid_conflict');
        }
        if ($sequence === (int) $lease->ack_sequence + 1) {
            $lease->ack_sequence = (int) DB::table("$name as r")
                ->where('r.session_id', $lease->id)->where('r.sequence', '>=', $sequence)
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from("$name as successor")
                    ->whereColumn('successor.session_id', 'r.session_id')->whereRaw('successor.sequence = r.sequence + 1'))
                ->min('r.sequence');
            DB::table('scanner_offline_sessions')->where('id', $lease->id)->update(['ack_sequence' => $lease->ack_sequence]);
        }

        if ($operational && $inserted) {
            app(OperationalAttendance::class)->decide($row, $lease, $event);
            $row = $table->where('id', $row->id)->firstOrFail();
        }
        return $this->receipt($row);
    }

    private function conflict(string $session, string $uuid, string $digest, string $reason): array
    {
        $conflicts = DB::table('scanner_receipt_conflicts');
        $conflicts->insertOrIgnore(['event_uuid' => $uuid, 'session_id' => $session,
            'payload_hash' => $digest, 'reason' => $reason, 'received_at' => now()]);
        return ['event_uuid' => $uuid, 'receipt_id' => 'conflict-'.$conflicts->where('payload_hash', $digest)->value('id'),
            'status' => 'rejected', 'reason' => $reason];
    }

    private function receipt(object $row): array
    {
        return ['event_uuid' => $row->event_uuid, 'receipt_id' => (property_exists($row,'attendance_log_id') ? 'attendance-' : 'receipt-').$row->id,
            'status' => $row->status, 'reason' => $row->reason];
    }
}
