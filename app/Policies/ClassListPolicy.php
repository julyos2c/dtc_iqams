<?php

namespace App\Policies;

use App\Models\ClassList;
use App\Models\User;

class ClassListPolicy
{
    public function update(User $user, ClassList $list): bool
    {
        return $user->isInstructor() && $user->instructor?->id === $list->instructor_id;
    }
}
