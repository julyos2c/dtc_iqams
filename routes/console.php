<?php

use Illuminate\Foundation\Inspiring;
use App\Jobs\RecordQueueHeartbeat;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use App\Services\ScannerCoverage;

Artisan::command('scanner:invalidate-attendance-policy', function () {
    if (!\App\Services\OperationalAttendance::enabled()) return 1;
    DB::transaction(function () {
        $state = DB::table('scanner_history_state')->where('id',1);
        $state->increment('revision');
        DB::table('scanner_history_versions')->updateOrInsert(
            ['subject'=>'global'], ['revision'=>$state->value('revision')]
        );
    });
    return 0;
})->purpose('Record an attendance policy change before changing rules during a local operational trial');

Schedule::call(fn () => app(ScannerCoverage::class)->reconcile())->name('scanner:reconcile-coverage')
    ->everyMinute()->onOneServer()->withoutOverlapping(5);

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('attendance:mark-student-absences')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping(5);

Schedule::command('attendance:mark-school-event-absences')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping(5);

Schedule::command('ops:scheduler-heartbeat')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping(2);

Schedule::job(new RecordQueueHeartbeat)
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping(2);

Schedule::command('reports:prune-exports')
    ->daily()
    ->onOneServer()
    ->withoutOverlapping(15);
