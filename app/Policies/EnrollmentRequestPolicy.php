<?php

namespace App\Policies;

use App\Models\EnrollmentRequest;
use App\Models\User;

class EnrollmentRequestPolicy
{
    public function review(User $user, EnrollmentRequest $request): bool
    {
        return $user->isAdmin() && $user->can('review-enrollment-requests') && $user->instructor?->id !== $request->instructor_id;
    }

    public function resubmit(User $user, EnrollmentRequest $request): bool
    {
        return $user->isInstructor() && $user->instructor?->id === $request->instructor_id && $request->status === 'rejected';
    }
}
