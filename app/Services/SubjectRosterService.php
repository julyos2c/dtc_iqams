<?php

namespace App\Services;

use App\Models\ClassList;
use App\Models\EnrollmentRequest;
use App\Models\StudentScheduleEnrollment;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudentRegistrationIdentity;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubjectRosterService
{
    public function __construct(private InstructorEnrollmentService $enrollments) {}

    public function meetings(User $actor, string $groupId): Collection
    {
        $meetings = $this->enrollments->offerings($actor)->get($groupId);
        abort_unless($meetings && $meetings->isNotEmpty(), 403);

        return $meetings;
    }

    public function roster(User $actor, string $groupId, bool $create = false): ?ClassList
    {
        $meetings = $this->meetings($actor, $groupId);
        $attributes = ['instructor_id' => $actor->instructor->id, 'recurring_schedule_group_id' => $groupId];
        if (! $create) {
            return ClassList::where($attributes)->first();
        }

        return DB::transaction(function () use ($actor, $groupId, $attributes, $meetings) {
            Schedule::where('recurring_schedule_group_id', $groupId)->lockForUpdate()->get();
            $this->meetings($actor, $groupId);
            $first = $meetings->first();

            return ClassList::firstOrCreate($attributes, [
                'section_id' => $first->section_id,
                'name' => mb_substr($first->subject->subject_code.' - '.$first->section->section_name, 0, 150),
            ]);
        }, 3);
    }

    public function enrolledStudents(string $groupId)
    {
        return Student::where('status', 'active')
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->whereHas('scheduleEnrollments', fn ($query) => $query->where('recurring_schedule_group_id', $groupId))
            ->orderBy('last_name')->orderBy('first_name');
    }

    /** Candidates are derived only from this instructor's subjects or preserved old drafts. */
    public function copyCandidates(User $actor, string $targetGroupId, string $source): Collection
    {
        $this->meetings($actor, $targetGroupId);
        [$kind, $value] = array_pad(explode(':', $source, 2), 2, '');
        $students = collect();
        if ($kind === 'subject') {
            abort_if($value === $targetGroupId, 422, 'Choose a different subject.');
            $this->meetings($actor, $value);
            $list = $this->roster($actor, $value);
            $students = $this->enrolledStudents($value)->get();
        } elseif ($kind === 'saved' && ctype_digit($value)) {
            $list = ClassList::where('instructor_id', $actor->instructor->id)->whereNull('recurring_schedule_group_id')->findOrFail($value);
        } else {
            throw ValidationException::withMessages(['copy_from' => 'Choose one of your other subjects.']);
        }
        $candidates = collect();
        foreach ($list?->entries()->with(['identity.student.user', 'student.user', 'registration'])->get() ?? [] as $entry) {
            $student = $entry->identity->student ?? $entry->student;
            if (! $student && ! $entry->registration) {
                continue;
            }
            if ($student && ($student->status !== 'active' || $student->user?->status !== 'active')) {
                continue;
            }
            $details = $entry->registration?->details ?? [];
            $candidates->push([
                'key' => 'entry:'.$entry->id,
                'number' => $entry->identity->student_no,
                'name' => $student?->fullName() ?? trim(($details['first_name'] ?? '').' '.($details['last_name'] ?? '')),
                'student' => $student, 'entry' => $entry,
            ]);
        }
        foreach ($students as $student) {
            $candidates->push(['key' => 'student:'.$student->id, 'number' => StudentRegistrationIdentity::normalize($student->student_no), 'name' => $student->fullName(), 'student' => $student, 'entry' => null]);
        }

        return $candidates->unique('number')->values();
    }

    /** Determine existing-student availability using the target offering's authoritative records. */
    public function studentStatuses(User $actor, string $groupId, Collection $students): array
    {
        $this->meetings($actor, $groupId);
        $numbers = $students->map(fn ($student) => StudentRegistrationIdentity::normalize($student->student_no))->all();
        $identities = StudentRegistrationIdentity::whereIn('student_no', $numbers)->get()->keyBy('student_no');
        $identityIds = $identities->pluck('id')->all();
        $roster = $this->roster($actor, $groupId);
        $drafts = $roster ? $roster->entries()->whereIn('identity_id', $identityIds)->pluck('identity_id')->all() : [];
        $pending = EnrollmentRequest::where('recurring_schedule_group_id', $groupId)->where('status', 'pending')->whereIn('identity_id', $identityIds)->pluck('identity_id')->all();
        $enrolled = StudentScheduleEnrollment::where('recurring_schedule_group_id', $groupId)->whereIn('student_id', $students->pluck('id'))->pluck('student_id')->all();
        $statuses = [];
        foreach ($students as $student) {
            $identity = $identities->get(StudentRegistrationIdentity::normalize($student->student_no));
            $status = 'available';
            if (in_array($student->id, $enrolled)) $status = 'enrolled';
            elseif ($identity && in_array($identity->id, $pending)) $status = 'pending';
            elseif ($identity && in_array($identity->id, $drafts)) $status = 'draft';
            elseif ($identity && $identity->student_id && $identity->student_id !== $student->id) $status = 'ineligible';
            $statuses[$student->id] = $status;
        }

        return $statuses;
    }

    public function eligibleCopyCandidates(User $actor, string $targetGroupId, string $source): Collection
    {
        $candidates = $this->copyCandidates($actor, $targetGroupId, $source);
        $roster = $this->roster($actor, $targetGroupId);
        $existing = $roster?->entries()->with('identity')->get()->pluck('identity.student_no')->all() ?? [];
        $requested = EnrollmentRequest::where('recurring_schedule_group_id', $targetGroupId)->with('identity')->get()->pluck('identity.student_no')->all();
        $enrolled = StudentScheduleEnrollment::where('recurring_schedule_group_id', $targetGroupId)->with('student')->get()
            ->map(fn ($enrollment) => $enrollment->student ? StudentRegistrationIdentity::normalize($enrollment->student->student_no) : null)->all();
        $excluded = array_fill_keys(array_filter(array_merge($existing, $requested, $enrolled)), true);

        return $candidates->reject(fn ($candidate) => isset($excluded[$candidate['number']]))->values();
    }

    public function copy(User $actor, string $targetGroupId, string $source, array $keys): int
    {
        // Validate source membership before filtering out records whose eligibility changed.
        $sourceCandidates = $this->copyCandidates($actor, $targetGroupId, $source)->keyBy('key');
        foreach ($keys as $key) {
            abort_unless($sourceCandidates->has($key), 403);
        }

        return DB::transaction(function () use ($actor, $targetGroupId, $source, $keys) {
            Schedule::where('recurring_schedule_group_id', $targetGroupId)->lockForUpdate()->get();
            $candidates = $this->eligibleCopyCandidates($actor, $targetGroupId, $source)->keyBy('key');
            $roster = $this->roster($actor, $targetGroupId, true);
            $count = 0;
            foreach (array_unique($keys) as $key) {
                if (! $candidates->has($key)) {
                    continue;
                }
                $candidate = $candidates[$key];
                if ($candidate['student']) {
                    $this->enrollments->addEntry($roster, $actor, $candidate['student'], null);
                    $count++;
                } else {
                    $sourceEntry = $candidate['entry'];
                    $entry = $roster->entries()->firstOrCreate(['identity_id' => $sourceEntry->identity_id], [
                        'registration_request_id' => $sourceEntry->registration_request_id,
                    ]);
                    $count += (int) $entry->wasRecentlyCreated;
                }
            }
            app(AuditLogger::class)->record('roster.students_copied', $roster, ['students_added' => $count], $actor);

            return $count;
        }, 3);
    }
}
