<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassListSubmissionBatch extends Model
{
    protected $guarded = ['id'];

    public function requests()
    {
        return $this->hasMany(EnrollmentRequest::class, 'batch_id');
    }
}
