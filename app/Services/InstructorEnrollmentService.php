<?php

namespace App\Services;

use App\Models\ClassList;
use App\Models\ClassListEntry;
use App\Models\ClassListSubmissionBatch;
use App\Models\EnrollmentRequest;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentRegistrationIdentity;
use App\Models\StudentRegistrationRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InstructorEnrollmentService
{
    public function offerings(User $user)
    {
        abort_unless($user->isInstructor() && $user->instructor, 403);

        return Schedule::active()->where('instructor_id', $user->instructor->id)->with(['subject', 'section'])->orderBy('subject_id')->orderBy('day')->get()->groupBy('recurring_schedule_group_id');
    }

    public function registrationRules(): array
    {
        return [
            'student_no' => 'required|string|max:50', 'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255', 'middle_name' => 'nullable|string|max:255', 'name_suffix' => 'nullable|string|max:100',
            'email' => 'required|email|max:255', 'course_id' => ['required', Rule::exists('courses', 'id')->whereNull('archived_at')],
            'section_id' => ['nullable', Rule::exists('sections', 'id')->whereNull('archived_at')],
        ];
    }

    public function validateDetails(array $details): array
    {
        $details = validator($details, $this->registrationRules())->validate();
        $details['student_no'] = StudentRegistrationIdentity::normalize($details['student_no']);
        $details['email'] = mb_strtolower(trim($details['email']));
        if (! empty($details['section_id']) && ! Section::active()->whereKey($details['section_id'])->where('course_id', $details['course_id'])->exists()) {
            throw ValidationException::withMessages(['section_id' => 'The section must belong to the selected course.']);
        }

        return $details;
    }

    public function addEntry(ClassList $list, User $actor, ?Student $student, ?array $details): ClassListEntry
    {
        Gate::forUser($actor)->authorize('update', $list);
        $details = $student ? null : $this->validateDetails($details ?? []);
        if ($student) {
            $this->assertStudentActive($student);
        }

        return DB::transaction(function () use ($list, $student, $details) {
            $number = StudentRegistrationIdentity::normalize($student?->student_no ?? $details['student_no']);
            $identity = StudentRegistrationIdentity::firstOrCreate(['student_no' => $number]);
            $identity = StudentRegistrationIdentity::whereKey($identity->id)->lockForUpdate()->firstOrFail();
            $canonical = Student::whereRaw('UPPER(TRIM(student_no)) = ?', [$number])->lockForUpdate()->first();
            if (! $student && $canonical) {
                throw ValidationException::withMessages(['student_no' => 'This student is already registered. Search and select the existing record.']);
            }
            if (! $student && User::whereRaw('LOWER(email) = ?', [$details['email']])->orWhereRaw('UPPER(TRIM(username)) = ?', [$number])->exists()) {
                throw ValidationException::withMessages(['email' => 'The email or student number belongs to an existing account. Select the existing student or ask an administrator to resolve the identity.']);
            }
            if ($list->entries()->where('identity_id', $identity->id)->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['student_no' => 'This student has already been added to this roster.']);
            }
            if ($student) {
                $identity->update(['student_id' => $student->id]);
            }
            $registration = $student ? null : StudentRegistrationRequest::create([
                'identity_id' => $identity->id, 'instructor_id' => $list->instructor_id, 'details' => $details,
            ]);

            return $list->entries()->create(['identity_id' => $identity->id, 'student_id' => $student?->id, 'registration_request_id' => $registration?->id]);
        }, 3);
    }

    public function assertStudentActive(Student $student): void
    {
        if ($student->status !== 'active' || $student->user?->status !== 'active' || ! $student->user->isStudent()) {
            throw ValidationException::withMessages(['student' => 'The student account is not active or has an invalid role. Ask an administrator to resolve it.']);
        }
    }

    public function submit(ClassList $list, User $actor, array $rosters, ?int $previousRequestId = null): ClassListSubmissionBatch
    {
        Gate::forUser($actor)->authorize('update', $list);

        return DB::transaction(function () use ($list, $actor, $rosters, $previousRequestId) {
            $offerings = $this->offerings($actor);
            $batch = ClassListSubmissionBatch::create(['class_list_id' => $list->id, 'instructor_id' => $list->instructor_id]);
            foreach ($rosters as $roster) {
                if (empty($roster['entry_ids'])) {
                    continue;
                }
                if (empty($roster['confirmed']) || ! $offerings->has($roster['group_id'])) {
                    throw ValidationException::withMessages(['rosters' => 'Confirm every roster and select only your assigned active offerings.']);
                }
                $meetings = Schedule::active()->where('recurring_schedule_group_id', $roster['group_id'])->lockForUpdate()->get();
                if ($meetings->isEmpty() || $meetings->contains(fn ($meeting) => $meeting->instructor_id !== $list->instructor_id)) {
                    throw ValidationException::withMessages(['rosters' => 'The offering assignment has changed. Reload and review the roster.']);
                }
                $schedule = $offerings[$roster['group_id']]->first();
                foreach (array_unique($roster['entry_ids']) as $entryId) {
                    $entry = $list->entries()->with(['identity', 'student.user', 'registration'])->findOrFail($entryId);
                    $identity = StudentRegistrationIdentity::whereKey($entry->identity_id)->lockForUpdate()->firstOrFail();
                    $student = $identity->student ?? $entry->student;
                    if ($student) {
                        $this->assertStudentActive($student);
                        // Legacy section access is not an explicit reviewed
                        // enrollment. It must not prevent preparing requests
                        // before the controlled workflow cutover.
                        if ($student->scheduleEnrollments()->where('recurring_schedule_group_id', $schedule->recurring_schedule_group_id)->exists()) {
                            continue;
                        }
                    }
                    $key = hash('sha256', $identity->id.'|'.$roster['group_id']);
                    if (EnrollmentRequest::where('pending_key', $key)->lockForUpdate()->first()) {
                        throw ValidationException::withMessages(['rosters' => 'A pending request already exists for student '.$identity->student_no.' in this offering.']);
                    }
                    $snapshot = [
                        'student' => $student ? $student->only(['student_no', 'first_name', 'last_name', 'middle_name', 'name_suffix', 'course_id', 'section_id', 'enrollment_type']) + ['email' => $student->user->email] : $entry->registration->details,
                        'subject_id' => $schedule->subject_id, 'subject' => $schedule->subject->subject_code.' - '.$schedule->subject->subject_name,
                        'section_id' => $schedule->section_id, 'section' => $schedule->section->section_name,
                        'meetings' => $meetings->map->only(['day', 'start_time', 'end_time', 'room'])->all(),
                    ];
                    $request = EnrollmentRequest::create([
                        'batch_id' => $batch->id, 'identity_id' => $identity->id, 'student_id' => $student?->id,
                        'registration_request_id' => $student ? null : $entry->registration_request_id, 'instructor_id' => $list->instructor_id,
                        'recurring_schedule_group_id' => $roster['group_id'], 'snapshot' => $snapshot,
                        'pending_key' => $key, 'previous_request_id' => $previousRequestId,
                    ]);
                    if (! $student && $entry->registration && $entry->registration->status !== 'approved') {
                        $entry->registration->update(['status' => 'pending']);
                        app(AuditLogger::class)->record('registration.submitted', $entry->registration, [], $actor);
                    }
                    app(AuditLogger::class)->record('enrollment.submitted', $request, ['batch_id' => $batch->id], $actor);
                }
            }
            if (! $batch->requests()->exists()) {
                throw ValidationException::withMessages(['rosters' => 'No new requests were created. Select a subject and at least one student who is not already enrolled in that subject.']);
            }

            return $batch;
        }, 3);
    }
}
