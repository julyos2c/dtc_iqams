<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentExcuseDecision extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['decided_at' => 'datetime', 'occurrence_date' => 'date']; }
    public function excuseRequest(): BelongsTo { return $this->belongsTo(StudentExcuseRequest::class, 'student_excuse_request_id'); }
    public function attendanceLog(): BelongsTo { return $this->belongsTo(AttendanceLog::class); }
    public function schedule(): BelongsTo { return $this->belongsTo(Schedule::class); }
    public function instructor(): BelongsTo { return $this->belongsTo(Instructor::class); }
}
