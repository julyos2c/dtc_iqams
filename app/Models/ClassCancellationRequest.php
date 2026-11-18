<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassCancellationRequest extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['occurrence_date' => 'date', 'reviewed_at' => 'datetime']; }
    public function schedule() { return $this->belongsTo(Schedule::class); }
    public function requester() { return $this->belongsTo(User::class, 'requester_id'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewer_id'); }
}
