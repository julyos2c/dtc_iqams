<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassList extends Model
{
    protected $guarded = ['id'];

    public function entries()
    {
        return $this->hasMany(ClassListEntry::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function instructor()
    {
        return $this->belongsTo(Instructor::class);
    }
}
