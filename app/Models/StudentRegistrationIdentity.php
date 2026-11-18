<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentRegistrationIdentity extends Model
{
    protected $guarded = ['id'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public static function normalize(string $number): string
    {
        return mb_strtoupper(trim($number));
    }
}
