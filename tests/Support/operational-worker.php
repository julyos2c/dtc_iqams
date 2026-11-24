<?php

use App\Services\ScannerCoverage;
use App\Services\ScannerRehearsalReceipts;
use App\Services\StudentAbsenceService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql' || config('database.connections.mysql.host') !== '127.0.0.1'
    || (string) config('database.connections.mysql.port') !== '13329' || config('database.connections.mysql.database') !== 'iqams_phase4_verification'
    || config('database.connections.mysql.url')) {
    exit(2);
}
config(['attendance.operational_sync' => true, 'app.timezone' => 'Asia/Manila']);
Carbon::setTestNow(Carbon::parse('2026-08-10 10:30', 'Asia/Manila'));
$args = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
usleep((int) max(0, ($args['start'] - microtime(true)) * 1000000));
if ($args['action'] === 'absence') {
    app(StudentAbsenceService::class)->markDue();
} elseif ($args['action'] === 'reconcile') {
    app(ScannerCoverage::class)->reconcile();
} else {
    $result = DB::transaction(function () use ($args) {
        $session = DB::table('scanner_offline_sessions')->where('id', $args['id'])->lockForUpdate()->firstOrFail();
        $receipt = app(ScannerRehearsalReceipts::class)->batch($session, [$args['event']])[0];
        app(ScannerCoverage::class)->checkpoint($session, 1, now()->getTimestampMs());

        return $receipt;
    }, 3);
    echo json_encode($result,JSON_THROW_ON_ERROR);
}
