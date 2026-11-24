<?php

namespace Tests\Integration;

use App\Models\Role;
use App\Models\ScannerTerminal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScannerReceiptConcurrencyTest extends TestCase
{
    public function test_two_mysql_connections_replay_one_committed_receipt(): void
    {
        $this->assertTrue(app()->environment('testing') && config('database.default') === 'mysql'
            && config('database.connections.mysql.host') === '127.0.0.1'
            && (string) config('database.connections.mysql.port') === '13328'
            && config('database.connections.mysql.database') === 'iqams_phase3_disposable'
            && !config('database.connections.mysql.url'));
        $operator = User::factory()->create(['role_id' => Role::firstOrCreate(['role_name' => 'admin'])->id]);
        $terminal = ScannerTerminal::create(['name' => 'Concurrency rehearsal', 'location' => 'Disposable']);
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($key, $private);
        $pem = openssl_pkey_get_details($key)['key'];
        $fingerprint = hash('sha256', $pem);
        $device = DB::table('scanner_devices')->insertGetId(['terminal_id' => $terminal->id, 'paired_by' => $operator->id,
            'public_key' => $pem, 'fingerprint' => $fingerprint, 'paired_at' => now()]);
        $session = (string) Str::uuid();
        $clock = (string) Str::uuid();
        DB::table('scanner_offline_sessions')->insert(['id' => $session, 'device_id' => $device, 'operator_id' => $operator->id,
            'anchor_ms' => now()->getTimestampMs(), 'clock_id' => $clock, 'expires_ms' => now()->addHour()->getTimestampMs()]);
        $event = ['event_uuid' => (string) Str::uuid(), 'sequence' => 1, 'clock_id' => $clock, 'elapsed_ms' => 0,
            'captured_at' => now()->utc()->format('Y-m-d\TH:i:s.v\Z'), 'qr_code' => 'TEST-race'];
        $start = microtime(true) + 3;
        $workers = [];
        foreach ([1, 2] as $worker) {
            $process = proc_open([PHP_BINARY, base_path('tests/Support/sync-rehearsal.php')], [0 => ['pipe','r'],1 => ['pipe','w'],2 => ['pipe','w']], $pipes, base_path());
            fwrite($pipes[0], json_encode(['operator' => $operator->id, 'terminal' => $terminal->id, 'start' => $start,
                'key' => $private, 'body' => ['device' => $fingerprint, 'session_id' => $session, 'events' => [$event]]]));
            fclose($pipes[0]);
            $workers[] = [$process, $pipes];
        }
        $results = [];
        foreach ($workers as [$process, $pipes]) {
            $result = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $this->assertSame(0, proc_close($process), $errors.' '.$result);
            $results[] = json_decode($result, true, flags: JSON_THROW_ON_ERROR);
        }
        $this->assertSame($results[0], $results[1]);
        $this->assertSame('synchronized', $results[0]['receipts'][0]['status']);
        $this->assertSame(1, $results[0]['ack_sequence']);
        $this->assertSame(1, DB::table('scanner_rehearsal_receipts')->where('session_id', $session)->count());
        $this->assertDatabaseCount('attendance_logs', 0);
    }
}
