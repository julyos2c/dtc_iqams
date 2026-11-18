<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentExcuseRequest extends Model
{
    protected $guarded = [];
    public function studentUser(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function decisions(): HasMany { return $this->hasMany(StudentExcuseDecision::class); }
}
