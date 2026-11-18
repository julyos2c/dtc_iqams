<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('student_excuse_decisions', 'schedule_id')) {
            Schema::table('student_excuse_decisions', fn (Blueprint $table) => $table->foreignId('schedule_id')->nullable());
        }
        if (! Schema::hasColumn('student_excuse_decisions', 'occurrence_date')) {
            Schema::table('student_excuse_decisions', fn (Blueprint $table) => $table->date('occurrence_date')->nullable());
        }
        Schema::table('student_excuse_decisions', fn (Blueprint $table) => $table->foreignId('attendance_log_id')->nullable()->change());
        $foreignKeys = Schema::getForeignKeys('student_excuse_decisions');
        if (! collect($foreignKeys)->contains(fn ($key) => $key['columns'] === ['schedule_id'])) {
            Schema::table('student_excuse_decisions', fn (Blueprint $table) => $table->foreign('schedule_id')->references('id')->on('schedules'));
        }
        if (! Schema::hasIndex('student_excuse_decisions', 'excuse_schedule_date_status_idx')) {
            Schema::table('student_excuse_decisions', fn (Blueprint $table) => $table->index(['schedule_id', 'occurrence_date', 'status'], 'excuse_schedule_date_status_idx'));
        }
        DB::table('student_excuse_decisions')->orderBy('id')->chunkById(500, function ($decisions) {
            foreach ($decisions as $decision) {
                $log = DB::table('attendance_logs')->where('id', $decision->attendance_log_id)->first();
                if ($log) DB::table('student_excuse_decisions')->where('id', $decision->id)->update(['schedule_id' => $log->schedule_id, 'occurrence_date' => $log->attendance_date]);
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Advance excuse requests cannot be safely rolled back.');
    }
};
