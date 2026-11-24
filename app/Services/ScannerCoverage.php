<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\SchoolEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ScannerCoverage
{
    public function initialize(string $id, int $start, int $end): void
    {
        DB::table('scanner_history_state')->where('id', 1)->update(['tracking' => true]);
        $revision = DB::table('scanner_history_state')->where('id', 1)->value('revision');
        DB::table('scanner_offline_sessions')->where('id', $id)->update([
            'history_revision' => $revision, 'policy_hash' => OperationalAttendance::policyHash(),
            'covered_ms' => $start, 'heartbeat_at' => now(),
        ]);
        $from = Carbon::createFromTimestampMs($start, config('app.timezone'));
        $to = Carbon::createFromTimestampMs($end, config('app.timezone'));
        $rows = [];
        $add = function ($kind, $target, $date, $opens, $ends) use ($start, $end, $id, &$rows) {
            if ($opens->getTimestampMs() > $end || $ends->getTimestampMs() < $start) {
                return;
            }
            $rows[] = ['session_id' => $id, 'kind' => $kind, 'target' => (string) $target, 'session_date' => $date->toDateString(),
                'opens_at' => $opens->format('Y-m-d H:i:s'), 'ends_at' => $ends->format('Y-m-d H:i:s'),
                'required_ms' => min($end, $ends->getTimestampMs())];
        };
        $schedules = Schedule::active()->get();
        $resolver = app(ScheduleOccurrenceResolver::class);
        for ($date = $from->copy()->subDay()->startOfDay(); $date->lte($to->copy()->addDay()); $date->addDay()) {
            foreach ($schedules as $schedule) {
                if ($o = $resolver->forDate($schedule, $date)) {
                    $add('class', $schedule->id, $date, $o->opensAt, $o->endsAt);
                }
            }
            if ($date->isWeekend()) {
                continue;
            }
            foreach (config('attendance.personnel_windows') as $role => $periods) {
                foreach ($periods as $name => $p) {
                    $add('personnel', "$role/$name", $date,
                        $date->copy()->setTimeFromTimeString($p['start']), $date->copy()->setTimeFromTimeString($p['end'])->endOfMinute());
                }
            }
        }
        foreach (SchoolEvent::where('status', 'published')->where('attendance_mode', 'event_attendance')
            ->where('starts_at', '<=', $to->copy()->addMinutes(config('attendance.early_scan_minutes')))
            ->where('ends_at', '>=', $from)->get() as $event) {
            $add('event', $event->id, $event->starts_at, $event->starts_at->copy()->subMinutes(config('attendance.early_scan_minutes')), $event->ends_at);
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('scanner_absence_obligations')->insert($chunk);
        }
        $this->invalidate();
    }

    private function invalidate(): void
    {
        DB::afterCommit(fn () => app(AttendanceSummaryCache::class)->invalidateAll());
    }

    public function checkpoint(object $lease, int $watermark, int $barrier): array
    {
        $expected = max((int) $lease->watermark, $watermark);
        $sessions = DB::table('scanner_offline_sessions')->where('id', $lease->id);
        $sessions->update(['watermark' => $expected]);
        $receipts = DB::table('scanner_attendance_receipts')->where('session_id', $lease->id);
        $contiguous = ! $lease->coverage_disputed && $watermark === $expected && $expected === (int) $lease->ack_sequence
            && $receipts->count() === $expected
            && ! DB::table('scanner_receipt_conflicts')->where('session_id', $lease->id)->exists()
            && OperationalAttendance::historyMatches($lease);
        $unresolved = $receipts->whereNotIn('status', ['confirmed', 'duplicate']);
        $verified = $contiguous && ! $unresolved->exists();
        $through = max($lease->covered_ms, min($barrier, $lease->expires_ms));
        if ($verified) {
            $sessions->update(['covered_ms' => $through]);
            DB::table('scanner_interruptions')->where('session_id', $lease->id)->whereNull('resolved_at')
                ->where('until_ms', '<=', $through)->update(['state' => 'resolved', 'resolved_at' => now()]);
        }
        if ($contiguous) {
            $due = DB::table('scanner_absence_obligations')->where('session_id', $lease->id)->where('required_ms', '<=', $through)
                ->whereIn('state', ['provisional', 'escalated']);
            if (! $verified) {
                // Only business-rule rejections have a verified occurrence time.
                if ($unresolved->clone()->where(fn ($q) => $q->where('status', '<>', 'rejected')
                    ->orWhere('reason', 'invalid_operational_card'))->exists()) {
                    $due->whereRaw('1 = 0');
                } else {
                    foreach ($due->clone()->get(['id', 'opens_at', 'ends_at']) as $occurrence) {
                        $bounds = [Carbon::parse($occurrence->opens_at)->getTimestampMs(),
                            Carbon::parse($occurrence->ends_at)->endOfSecond()->getTimestampMs()];
                        if ($unresolved->clone()->whereBetween('captured_ms', $bounds)->exists()) {
                            $due->where('id', '<>', $occurrence->id);
                        }
                    }
                }
            }
            $changed = $due->update(['state' => 'covered', 'decided_at' => now()]);
            if ($changed) {
                $this->invalidate();
            }
        }

        return ['verified' => $verified, 'complete' => $verified && $through >= $lease->expires_ms,
            'ack_sequence' => (int) $lease->ack_sequence, 'watermark' => $expected];
    }

    public function reconcile(): void
    {
        if (! OperationalAttendance::enabled()) {
            return;
        }
        $now = now();
        DB::table('scanner_offline_sessions')->whereNotNull('history_revision')->whereColumn('covered_ms', '<', 'expires_ms')
            ->where(fn ($q) => $q->where('heartbeat_at', '<=', $now->copy()->subSeconds(config('attendance.suspect_after_seconds')))
                ->orWhere('covered_ms', '<=', $now->copy()->subSeconds(config('attendance.suspect_after_seconds'))->getTimestampMs()))
            ->orderBy('id')->chunkById(100, function ($leases) use ($now) {
                foreach ($leases as $item) {
                    DB::transaction(function () use ($item, $now) {
                        $lease = DB::table('scanner_offline_sessions')->where('id', $item->id)->lockForUpdate()->firstOrFail();
                        $end = min($lease->expires_ms, $now->getTimestampMs());
                        if ($end - $lease->covered_ms < 1 || $now->getTimestampMs() - $lease->covered_ms < config('attendance.suspect_after_seconds') * 1000) {
                            return;
                        }
                        $from = Carbon::createFromTimestampMs($lease->covered_ms, config('app.timezone'));
                        $deadline = $from->copy();
                        for ($remaining = max(1, config('attendance.escalation_operating_days')); $remaining > 0;) {
                            $deadline->addWeekday();
                            $closure = SchoolEvent::where('status', 'published')->where('target_scope', 'school')
                                ->where('attendance_mode', 'cancelled')->where('starts_at', '<=', $deadline->copy()->endOfDay())
                                ->where('ends_at', '>=', $deadline->copy()->startOfDay())->exists();
                            if (! $closure) {
                                $remaining--;
                            }
                        }
                        $state = $deadline->lte($now) ? 'escalated' : 'suspected';
                        $key = ['session_id' => $lease->id, 'from_ms' => $lease->covered_ms];
                        DB::table('scanner_interruptions')->updateOrInsert($key, ['until_ms' => $end, 'state' => $state, 'escalate_at' => $deadline]);
                        if ($state === 'escalated') {
                            DB::table('scanner_absence_obligations')->where('session_id', $lease->id)
                                ->where('state', 'provisional')->update(['state' => 'escalated']);
                        }
                    }, 3);
                }
            });
        // Replay each covered obligation through its original business-rule entry point.
        DB::table('scanner_absence_obligations')->where('state', 'covered')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $item) {
                DB::transaction(function () use ($item) {
                    $ob = DB::table('scanner_absence_obligations')->where('id', $item->id)->lockForUpdate()->firstOrFail();
                    if ($ob->state !== 'covered') {
                        return;
                    }
                    $lease = DB::table('scanner_offline_sessions')->where('id', $ob->session_id)->firstOrFail();
                    if (! OperationalAttendance::historyMatches($lease)) {
                        DB::table('scanner_absence_obligations')->where('id', $ob->id)->update(['state' => 'escalated']);
                        $this->invalidate();

                        return;
                    }
                    DB::table('scanner_absence_obligations')->where('id', $ob->id)->update(['state' => 'reconciling']);
                    if ($ob->kind === 'class') {
                        $schedule = Schedule::whereKey($ob->target)->lockForUpdate()->first();
                        $occurrence = $schedule ? app(ScheduleOccurrenceResolver::class)->forDate($schedule, Carbon::parse($ob->session_date)) : null;
                        if ($occurrence) {
                            app(StudentAbsenceService::class)->markOccurrenceDue($occurrence, now());
                        }
                    } elseif ($ob->kind === 'event') {
                        app(SchoolEventAttendanceService::class)->markDue(now(), (int) $ob->target);
                    }
                    DB::table('scanner_absence_obligations')->where('id', $ob->id)->update(['state' => 'reconciled', 'decided_at' => now()]);
                    $this->invalidate();
                }, 3);
            }
        });
    }

    public static function protect(string $kind, string $target, CarbonInterface $date, CarbonInterface $opens, CarbonInterface $ends): bool
    {
        if (! OperationalAttendance::enabled()) {
            return false;
        }
        DB::table('scanner_history_state')->where('id', 1)->lockForUpdate()->firstOrFail();
        // Include newly created or moved occurrences that were absent at initialization.
        $leases = DB::table('scanner_offline_sessions')->whereNotNull('history_revision')
            ->where('anchor_ms', '<=', $ends->getTimestampMs())->where('expires_ms', '>=', $opens->getTimestampMs())->get();
        foreach ($leases as $lease) {
            $end = min($ends->getTimestampMs(), $lease->expires_ms);
            if ($lease->covered_ms >= $end && OperationalAttendance::historyMatches($lease)) {
                continue;
            }
            DB::table('scanner_absence_obligations')->insertOrIgnore([
                'session_id' => $lease->id, 'kind' => $kind, 'target' => $target, 'session_date' => $date->toDateString(),
                'opens_at' => $opens, 'ends_at' => $ends, 'required_ms' => $end,
            ]);
        }

        return self::held($kind, $target, $date);
    }

    public static function held(string $kind, string $target, CarbonInterface $date): bool
    {
        return OperationalAttendance::enabled() && DB::table('scanner_absence_obligations')->where('kind', $kind)
            ->where('target', $target)->where('session_date', $date->toDateString())->whereIn('state', ['provisional', 'covered', 'escalated'])->exists();
    }

    public static function filterStored($query, string $alias): void
    {
        if (! OperationalAttendance::enabled()) {
            return;
        }
        $query->where(fn ($q) => $q->where("$alias.status", '<>', 'absent')->orWhereNull("$alias.record_origin")
            ->orWhere("$alias.record_origin", '<>', 'system')->orWhereNotExists(function ($holds) use ($alias) {
                $holds->selectRaw('1')->from('scanner_absence_obligations as hold')->whereIn('hold.state', ['provisional', 'covered', 'escalated'])
                    ->where(fn ($k) => $k->where(fn ($c) => $c->where('hold.kind', 'class')->whereColumn('hold.target', "$alias.schedule_id")
                        ->whereColumn('hold.opens_at', '<=', "$alias.scan_time")->whereColumn('hold.ends_at', '>=', "$alias.scan_time"))
                        ->orWhere(fn ($e) => $e->where('hold.kind', 'event')->whereColumn('hold.target', "$alias.school_event_id")));
            }));
    }
}
