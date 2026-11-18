<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentRegistrationRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['details' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function identity()
    {
        return $this->belongsTo(StudentRegistrationIdentity::class, 'identity_id');
    }
}
