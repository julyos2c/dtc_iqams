<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnrollmentRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function identity()
    {
        return $this->belongsTo(StudentRegistrationIdentity::class, 'identity_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function registration()
    {
        return $this->belongsTo(StudentRegistrationRequest::class, 'registration_request_id');
    }

    public function instructor()
    {
        return $this->belongsTo(Instructor::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function batch()
    {
        return $this->belongsTo(ClassListSubmissionBatch::class, 'batch_id');
    }
}
