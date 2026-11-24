<?php

use App\Http\Controllers\ScannerRehearsalController;
use App\Models\ScannerTerminal;
use App\Models\User;
use App\Services\ScannerRehearsalReceipts;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (!$app->environment('testing') || config('database.default') !== 'mysql'
    || config('database.connections.mysql.database') !== 'iqams_phase3_disposable'
    || config('database.connections.mysql.host') !== '127.0.0.1'
    || (string) config('database.connections.mysql.port') !== '13328' || config('database.connections.mysql.url')) exit(2);
config(['attendance.scanner_queue_preview' => true, 'attendance.rehearsal_sync' => true]);
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$user = User::findOrFail($input['operator']);
$terminal = ScannerTerminal::findOrFail($input['terminal']);
$session = app('session')->driver();
$request = function (array $data) use ($user, $terminal, $session): Request {
    $r = Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($data));
    $r->setUserResolver(fn () => $user);
    $r->setLaravelSession($session);
    $r->attributes->set('scanner_terminal', $terminal);
    return $r;
};
$controller = app(ScannerRehearsalController::class);
$nonce = $controller->challenge($request(['purpose' => 'sync']))->getData()->nonce;
$r = $request($input['body']);
openssl_sign($nonce."\nsync\n".hash('sha256', $r->getContent()), $sig, $input['key'], OPENSSL_ALGO_SHA256);
$r->headers->set('X-Scanner-Challenge', $nonce);
$r->headers->set('X-Scanner-Signature', base64_encode($sig));
usleep((int) max(0, ($input['start'] - microtime(true))*1000000));
echo $controller->sync($r, app(ScannerRehearsalReceipts::class))->getContent();
