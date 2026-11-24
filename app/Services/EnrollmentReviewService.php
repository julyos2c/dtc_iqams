<?php

namespace App\Services;

use App\Models\EnrollmentRequest;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudentRegistrationIdentity;
use App\Models\User;
use App\Notifications\EnrollmentApprovedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EnrollmentReviewService
{
    /** The temporary password exists only in the return value of the first activation. */
    public function review(EnrollmentRequest $target, User $actor, string $decision, ?string $notes): array
    {
        Gate::forUser($actor)->authorize('review', $target);
        validator(['decision' => $decision, 'notes' => $notes], [
            'decision' => 'required|in:approved,rejected', 'notes' => ($decision === 'rejected' ? 'required' : 'nullable').'|string|max:2000',
        ])->validate();

        $result = DB::transaction(function () use ($target, $actor, $decision, $notes) {
            $identity = StudentRegistrationIdentity::whereKey($target->identity_id)->lockForUpdate()->firstOrFail();
            $request = EnrollmentRequest::whereKey($target->id)->lockForUpdate()->firstOrFail();
            if ($request->status === $decision) {
                return ['id' => $request->id, 'status' => $decision];
            }
            if ($request->status !== 'pending') {
                throw ValidationException::withMessages(['request' => 'Only pending requests can be reviewed.']);
            }
            $password = null;
            if ($decision === 'approved') {
                app(EnrollmentWorkflow::class)->requireEnabled();
                $meetings = Schedule::active()->where('recurring_schedule_group_id', $request->recurring_schedule_group_id)->lockForUpdate()->get();
                $instructorUser = $request->instructor
                    ? User::whereKey($request->instructor->user_id)->lockForUpdate()->first()
                    : null;
                if ($meetings->isEmpty() || $meetings->contains(fn ($meeting) => $meeting->instructor_id !== $request->instructor_id
                    || $meeting->subject_id !== (int) $request->snapshot['subject_id']
                    || $meeting->section_id !== (int) $request->snapshot['section_id']
                ) || $instructorUser?->status !== 'active' || ! $instructorUser->isInstructor()) {
                    throw ValidationException::withMessages(['offering' => 'The offering or instructor assignment is no longer available. Reject and request a corrected submission.']);
                }
                $student = Student::whereRaw('UPPER(TRIM(student_no)) = ?', [$identity->student_no])->lockForUpdate()->first();
                $details = $request->registration ? app(InstructorEnrollmentService::class)->validateDetails($request->snapshot['student']) : null;
                if ($student) {
                    $student->setRelation('user', User::whereKey($student->user_id)->lockForUpdate()->firstOrFail());
                    app(InstructorEnrollmentService::class)->assertStudentActive($student);
                    if ($details) {
                        foreach (['first_name', 'last_name', 'middle_name', 'name_suffix', 'course_id', 'section_id', 'email'] as $field) {
                            $actual = $field === 'email' ? $student->user->email : $student->{$field};
                            if (mb_strtolower(trim((string) $actual)) !== mb_strtolower(trim((string) ($details[$field] ?? '')))) {
                                throw ValidationException::withMessages(['identity' => 'Registration details conflict with the canonical student. Reject this request with correction instructions.']);
                            }
                        }
                    }
                } else {
                    if (! $details) {
                        throw ValidationException::withMessages(['identity' => 'The existing student record is unavailable.']);
                    }
                    if (User::whereRaw('LOWER(email) = ?', [$details['email']])->orWhereRaw('UPPER(TRIM(username)) = ?', [$identity->student_no])->exists()) {
                        throw ValidationException::withMessages(['identity' => 'The email or username belongs to another account. Resolve the conflict before approval.']);
                    }
                    $password = 'Student@'.$identity->student_no;
                    $user = User::create([
                        'username' => $identity->student_no, 'email' => $details['email'],
                        'name' => trim($details['first_name'].' '.$details['last_name'].' '.($details['name_suffix'] ?? '')),
                        'password' => Hash::make($password), 'status' => 'active',
                    ]);
                    $user->forceFill(['must_change_password' => true, 'password_changed_at' => null, 'email_verified_at' => null])->save();
                    app(RoleAssignmentService::class)->assign($user, 'student', $actor);
                    $student = Student::create(collect($details)->except(['email'])->all() + ['user_id' => $user->id, 'status' => 'active', 'qr_code' => null]);
                    app(QrCredentialService::class)->issue($user, $actor);
                    app(AuditLogger::class)->record('account.activated', $user, ['registration_request_id' => $request->registration_request_id], $actor);
                }
                $identity->update(['student_id' => $student->id]);
                if ($request->registration && $request->registration->status !== 'approved') {
                    $request->registration->update(['status' => 'approved', 'student_id' => $student->id, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
                    app(AuditLogger::class)->record('registration.approved', $request->registration, ['student_id' => $student->id], $actor);
                }
                $student->scheduleEnrollments()->firstOrCreate(['recurring_schedule_group_id' => $request->recurring_schedule_group_id], ['effective_at' => now(), 'origin' => 'approved']);
                $request->student_id = $student->id;
            }
            $request->fill(['status' => $decision, 'pending_key' => null, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_notes' => $notes])->save();
            if ($decision === 'approved') {
                $notification = $instructorUser->notifications()
                    ->where('type', EnrollmentApprovedNotification::class)
                    ->where('data->group_id', $request->recurring_schedule_group_id)
                    ->lockForUpdate()->first();
                if ($notification) {
                    $data = $notification->data;
                    $data['approved_count']++;
                    $notification->update(['data' => $data, 'read_at' => null]);
                } else {
                    $instructorUser->notify(new EnrollmentApprovedNotification($request));
                }
            }
            app(AuditLogger::class)->record('enrollment.'.$decision, $request, ['batch_id' => $request->batch_id], $actor);
            if ($decision === 'rejected' && $request->registration) {
                app(AuditLogger::class)->record('registration.enrollment_rejected', $request->registration, ['enrollment_request_id' => $request->id], $actor);
                if ($request->registration->status !== 'approved' && ! EnrollmentRequest::where('registration_request_id', $request->registration_request_id)->where('status', 'pending')->exists()) {
                    $request->registration->update(['status' => 'rejected', 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
                    app(AuditLogger::class)->record('registration.rejected', $request->registration, [], $actor);
                }
            }
            DB::afterCommit(fn () => app(AttendanceSummaryCache::class)->invalidateStudentContext());

            return array_filter(['id' => $request->id, 'status' => $decision, 'username' => $password ? $identity->student_no : null, 'temporary_password' => $password], fn ($value) => $value !== null);
        }, 3);

        if (isset($result['temporary_password'])) {
            $user = User::where('username', $result['username'])->firstOrFail();
            $result['credentials_email_sent'] = app(AccountCredentialsSender::class)->send($user, $result['temporary_password']);
        }

        return $result;
    }
}
