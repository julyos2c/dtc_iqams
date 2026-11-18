<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentWorkflow
{
    private ?bool $enabled = null;

    public function enabled(): bool
    {
        return $this->enabled ??= DB::table('enrollment_workflow_states')->where('id', 1)->value('enabled_at') !== null;
    }

    public function refresh(): void
    {
        $this->enabled = null;
    }

    public function requireEnabled(): void
    {
        if (! $this->enabled()) {
            throw ValidationException::withMessages(['enrollment' => 'Enrollment approval is not enabled. An administrator must complete the legacy roster backfill first.']);
        }
    }
}
