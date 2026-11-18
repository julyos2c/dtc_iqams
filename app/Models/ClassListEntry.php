<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassListEntry extends Model
{
    protected $guarded = ['id'];

    public function classList()
    {
        return $this->belongsTo(ClassList::class);
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
}
