<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\ScannerTerminal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScannerRehearsalTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/attendance-scanner/rehearsal/';
    private $key;
    private array $lease;
    private string $device;
    private string $clock;
    private array $lastProof;

    private function login(): void
    {
        config(['attendance.scanner_queue_preview' => true, 'attendance.rehearsal_sync' => true]);
        $admin = User::factory()->create(['role_id' => Role::firstOrCreate(['role_name' => 'admin'])->id]);
        $terminal = ScannerTerminal::create(['name' => 'Test', 'location' => 'Test']);
        $this->actingAs($admin)->withSession(['scanner_terminal_id' => $terminal->id, 'auth.password_confirmed_at' => time()]);
        $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());
        $opensslConfig = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        $options = is_file($opensslConfig) ? ['config' => $opensslConfig] : [];
        $this->key = openssl_pkey_new(['private_key_bits' => 2048] + $options);
        $this->clock = (string) Str::uuid();
    }

    private function signed(string $action, array $body, bool $bad = false, string $prefix = self::URL)
    {
        $nonce = $this->postJson($prefix.'challenge', ['purpose' => $action])->assertOk()->json('nonce');
        $content = json_encode($body);
        openssl_sign($nonce."\n".$action."\n".hash('sha256', $content), $signature, $this->key, OPENSSL_ALGO_SHA256);
        $this->lastProof = [
            'X-Scanner-Challenge' => $nonce, 'X-Scanner-Signature' => $bad ? 'bad' : base64_encode($signature),
        ];
        return $this->postJson($prefix.$action, $body, $this->lastProof);
    }

    private function pair(): void
    {
        $this->login();
        $pem = openssl_pkey_get_details($this->key)['key'];
        $body = ['public_key' => preg_replace('/-----[^-]+-----|\s+/', '', $pem)];
        $this->signed('pair', $body, true)->assertForbidden();
        $this->device = $this->signed('pair', $body)->assertOk()->json('device');
        $this->lease = $this->signed('lease', ['device' => $this->device, 'clock_id' => $this->clock])->assertOk()->json();
    }

    private function event(int $seq): array
    {
        return ['event_uuid' => (string) Str::uuid(), 'sequence' => $seq, 'clock_id' => $this->clock, 'elapsed_ms' => 0,
            'captured_at' => now()->utc()->format('Y-m-d\TH:i:s.v\Z'), 'qr_code' => 'TEST-one'];
    }

    public function test_operational_mode_binds_device_proof_and_isolates_rehearsal(): void
    {
        $this->pair();
        $live='/attendance-scanner/operational/';
        $this->postJson($live.'challenge',['purpose'=>'lease'])->assertNotFound();
        config(['attendance.operational_sync'=>true]);
        $args=['device'=>$this->device,'session_id'=>$this->lease['offline_session_id']];
        $this->signed('sync',$args+['events'=>[$this->event(1)]],prefix:$live)->assertForbidden();
        $this->lease=$this->signed('lease',['device'=>$this->device,'clock_id'=>$this->clock],prefix:$live)->assertOk()->json();
        $args['session_id']=$this->lease['offline_session_id'];
        $event=$this->event(1);
        $this->signed('sync',$args+['events'=>[$event]])->assertForbidden();
        $this->signed('sync',$args+['events'=>[$event]],prefix:$live)->assertOk()->assertJsonPath('mode','operational')
            ->assertJsonPath('receipts.0.reason','invalid_operational_card');
        $this->signed('checkpoint',$args+['watermark'=>1],prefix:$live)->assertOk()->assertJsonPath('verified',false);
        $this->getJson('/scanner-security/offline-sessions/'.$args['session_id'])
            ->assertOk()->assertJsonPath('records.data.0.reason','invalid_operational_card');
        $this->assertDatabaseCount('attendance_logs',0);
        $this->assertDatabaseCount('scanner_rehearsal_receipts',0);
    }

    private function upload(array $events)
    {
        return $this->signed('sync', ['device' => $this->device, 'session_id' => $this->lease['offline_session_id'], 'events' => $events]);
    }

    public function test_receipt_retries_conflicts_partial_failure_and_review(): void
    {
        $this->pair();
        $a = $this->event(1);
        $b = $this->event(3);
        $response = $this->upload([$b, [], $a])->assertOk()->assertJsonPath('ack_sequence', 1)
            ->assertJsonPath('receipts.0.reason', 'sequence_gap')->assertJsonPath('receipts.1.reason', 'malformed_event')
            ->assertJsonPath('receipts.2.status', 'synchronized');
        $this->upload([$a])->assertOk()->assertJsonPath('receipts.0.receipt_id', $response->json('receipts.2.receipt_id'));
        $this->upload([array_replace($a, ['qr_code' => 'TEST-other']), $this->event(1)])
            ->assertOk()->assertJsonPath('receipts.0.reason', 'uuid_conflict')->assertJsonPath('receipts.1.reason', 'sequence_conflict');
        $this->upload([array_replace($this->event(2), ['clock_id' => (string) Str::uuid()])])
            ->assertOk()->assertJsonPath('receipts.0.reason', 'clock_uncertain')->assertJsonPath('ack_sequence', 3);
        $this->upload([array_replace($this->event(4), ['captured_at' => now()->addMinutes(10)->utc()->format('Y-m-d\TH:i:s.v\Z')])])
            ->assertOk()->assertJsonPath('receipts.0.reason', 'future_capture');
        $this->upload([array_replace($this->event(5), ['qr_code' => 'actual-id'])])
            ->assertOk()->assertJsonPath('receipts.0.status', 'rejected');
        $this->assertDatabaseCount('scanner_rehearsal_receipts', 5);
        $this->assertDatabaseCount('scanner_receipt_conflicts', 2);
        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_expiry_heartbeat_and_revocation(): void
    {
        $this->pair();
        $old = $this->event(1);
        $args = ['device' => $this->device, 'session_id' => $this->lease['offline_session_id']];
        $this->travel(9)->hours();
        $this->signed('heartbeat', $args + ['pending' => 2])->assertOk()->assertJsonPath('session_valid', false);
        $this->assertDatabaseHas('scanner_terminals', ['reported_pending' => 2]);
        $this->upload([$old])->assertStatus(423);
        session()->put('auth.password_confirmed_at', now()->timestamp);
        session()->save();
        $this->upload([$old, $this->event(2)])->assertOk()->assertJsonPath('receipts.0.status', 'synchronized')
            ->assertJsonPath('receipts.1.reason', 'outside_lease');
        $this->travelBack();
        $this->postJson(self::URL.'revoke', ['device' => $this->device])->assertOk();
        $this->upload([$old])->assertForbidden();
        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_requires_login_feature_and_device_proof(): void
    {
        $this->postJson(self::URL.'pair')->assertUnauthorized();
        $this->login();
        $this->postJson(self::URL.'sync', ['events' => []])->assertForbidden();
        config(['attendance.rehearsal_sync' => false]);
        $this->postJson(self::URL.'challenge', ['purpose' => 'pair'])->assertNotFound();
    }

    public function test_reauthentication_device_replacement_and_one_use_challenges(): void
    {
        $this->pair();
        $args = ['device' => $this->device, 'clock_id' => $this->clock];
        $this->postJson(self::URL.'lease', $args, $this->lastProof)->assertForbidden();
        $oldDevice = $this->device;
        $this->postJson(self::URL.'revoke', ['device' => $oldDevice])->assertOk();
        $opensslConfig = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        $this->key = openssl_pkey_new(['private_key_bits' => 2048] + (is_file($opensslConfig) ? ['config' => $opensslConfig] : []));
        $body = ['public_key' => preg_replace('/-----[^-]+-----|\s+/', '', openssl_pkey_get_details($this->key)['key'])];
        $this->device = $this->signed('pair', $body)->assertOk()->json('device');
        $this->assertNotSame($oldDevice, $this->device);
        $this->upload([$this->event(1)])->assertForbidden();
        $this->signed('lease', ['device' => $this->device, 'clock_id' => $this->clock])->assertOk();
        $this->app['env'] = 'production';
        $this->postJson(self::URL.'challenge', ['purpose' => 'sync'])->assertStatus(419);
        $this->postJson(self::URL.'challenge', ['purpose' => 'sync'], ['X-CSRF-TOKEN' => csrf_token()])->assertNotFound();
    }

    public function test_clock_changes_event_limits_and_session_revocation(): void
    {
        $this->pair();
        $first = $this->event(1);
        $this->travel(3)->minutes();
        $later = array_replace($this->event(2), ['elapsed_ms' => 180000]);
        $this->upload([$first, $later])->assertOk()->assertJsonPath('receipts.1.status', 'synchronized');
        $this->upload([array_replace($first, ['event_uuid' => (string) Str::uuid(), 'sequence' => 3])])
            ->assertOk()->assertJsonPath('receipts.0.reason', 'backward_clock');
        $this->upload([$this->event(4)])->assertOk()->assertJsonPath('receipts.0.reason', 'clock_discrepancy');
        $this->upload(array_fill(0, 26, $first))->assertUnprocessable();
        \Illuminate\Support\Facades\RateLimiter::increment('scanner-rehearsal-events:'.$this->lease['terminal_id'], 60, 240);
        $this->upload([$first])->assertTooManyRequests();
        $this->postJson(self::URL.'revoke', ['device' => $this->device, 'session_id' => $this->lease['offline_session_id']])->assertOk();
        $this->upload([$first])->assertForbidden();
        $this->signed('lease', ['device' => $this->device, 'clock_id' => $this->clock])->assertOk();
    }
}
