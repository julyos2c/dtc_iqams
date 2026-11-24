<?php

namespace Tests\Integration;

use App\Models\AttendanceLog;
use App\Models\ScannerTerminal;
use App\Models\User;
use App\Services\ScannerCoverage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\ScannerAttendanceFixtures;
use Tests\TestCase;

class OperationalAttendanceRaceTest extends TestCase
{
    use ScannerAttendanceFixtures;

    public function test_sync_and_absence_workers_converge_on_one_original_time_record(): void
    {
        $this->assertTrue(app()->environment('testing') && config('database.default') === 'mysql'
            && config('database.connections.mysql.host') === '127.0.0.1' && (string) config('database.connections.mysql.port') === '13329'
            && config('database.connections.mysql.database') === 'iqams_phase4_verification' && ! config('database.connections.mysql.url'));
        config(['attendance.operational_sync' => true, 'app.timezone' => 'Asia/Manila']);
        Carbon::setTestNow(Carbon::parse('2026-08-10 07:30', 'Asia/Manila'));
        [$student,$schedule] = $this->studentWithSchedule();
        $terminal = ScannerTerminal::create(['name' => 'Race', 'location' => 'Disposable']);
        $operator = User::factory()->create();
        $device = DB::table('scanner_devices')->insertGetId(['terminal_id' => $terminal->id, 'paired_by' => $operator->id, 'public_key' => 'service-test', 'fingerprint' => hash('sha256', Str::random()), 'paired_at' => now()]);
        $id = (string) Str::uuid();
        $clock = (string) Str::uuid();
        DB::transaction(function () use ($id, $clock, $device, $operator) {
            $anchor = now()->getTimestampMs();
            $end = now()->addHours(8)->getTimestampMs();
            DB::table('scanner_offline_sessions')->insert(['id' => $id, 'operator_id' => $operator->id, 'device_id' => $device,
                'clock_id' => $clock, 'anchor_ms' => $anchor, 'expires_ms' => $end]);
            app(ScannerCoverage::class)->initialize($id, $anchor, $end);
        });
        $start = microtime(true) + 3;
        $event = ['event_uuid' => (string) Str::uuid(), 'sequence' => 1, 'clock_id' => $clock, 'elapsed_ms' => 35 * 60000,
            'captured_at' => '2026-08-10T00:05:00.000Z', 'qr_code' => 'STU-001'];
        $workers = [];
        foreach (['sync', 'sync', 'absence', 'reconcile'] as $action) {
            $proc = proc_open([PHP_BINARY, base_path('tests/Support/operational-worker.php')], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
            fwrite($pipes[0], json_encode(compact('id', 'event', 'start', 'action')));
            fclose($pipes[0]);
            $workers[] = [$proc, $pipes, $action];
        }
        $acks = [];
        foreach ($workers as [$proc,$pipes,$action]) {
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->assertSame(0, proc_close($proc), $output.$errors);
            if ($action === 'sync') {
                $acks[] = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
            }
        }
        $this->assertEquals($acks[0], $acks[1]);
        $this->assertSame('confirmed', $acks[0]['status']);
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:30', 'Asia/Manila'));
        app(ScannerCoverage::class)->reconcile();
        $this->assertDatabaseHas('scanner_absence_obligations', ['session_id' => $id, 'kind' => 'class', 'state' => 'reconciled']);
        $this->assertDatabaseHas('attendance_logs', ['user_id' => $student->id, 'schedule_id' => $schedule->id, 'status' => 'present', 'scan_time' => '2026-08-10 08:05:00']);
        $this->assertSame(1, AttendanceLog::where('user_id', $student->id)->count());
        $this->assertSame(1, DB::table('scanner_attendance_receipts')->where('session_id', $id)->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'offline_attendance.confirmed')->where('actor_id',$operator->id)->count());
        Carbon::setTestNow();
    }
}
