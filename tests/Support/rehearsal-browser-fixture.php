<?php

use App\Models\Role;
use App\Models\ScannerTerminal;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = realpath(config('database.connections.sqlite.database'));
if (!$app->environment('local') || config('database.default') !== 'sqlite' || !$db
    || dirname(dirname($db)) !== realpath(sys_get_temp_dir()) || !str_starts_with(basename(dirname($db)), 'iqams-rehearsal-')
    || filesize($db) !== 0 || config('database.connections.sqlite.url')) exit(2);
if (Artisan::call('migrate', ['--force' => true]) !== 0) exit(3);
User::factory()->create(['role_id' => Role::firstOrCreate(['role_name' => 'admin'])->id, 'username' => 'rehearsal-test',
    'password' => trim(stream_get_contents(STDIN)), 'must_change_password' => false]);
ScannerTerminal::create(['name' => 'Browser test', 'location' => 'Disposable']);
(new class {
    use \Tests\Support\ScannerAttendanceFixtures;

    public function seed(): void
    {
        [, $schedule] = $this->studentWithSchedule();
        $schedule->update(['day'=>strtolower(now()->englishDayOfWeek),'start_time'=>'00:00','end_time'=>'23:59:59']);
    }
})->seed();
