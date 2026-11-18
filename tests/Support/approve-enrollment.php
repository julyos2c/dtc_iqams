<?php

use App\Models\EnrollmentRequest;
use App\Models\User;
use App\Services\EnrollmentReviewService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql'
    || config('database.connections.mysql.database') !== 'iqams_enrollment_test'
    || config('database.connections.mysql.host') !== '127.0.0.1'
    || (string) config('database.connections.mysql.port') !== '13317'
    || config('database.connections.mysql.url')) {
    throw new RuntimeException('Concurrency helper requires the disposable enrollment test database.');
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
while (microtime(true) < $input['start_at']) {
    usleep(1000);
}
try {
    $result = app(EnrollmentReviewService::class)->review(EnrollmentRequest::findOrFail($input['request_id']), User::findOrFail($input['actor_id']), 'approved', null);
    echo json_encode(['status' => $result['status'], 'activated' => isset($result['temporary_password'])], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    echo json_encode(['status' => 'failed', 'activated' => false]);
    exit(1);
}
