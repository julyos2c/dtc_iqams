<?php

namespace App\Http\Controllers;

use App\Models\ClassList;
use App\Models\ClassListEntry;
use App\Models\Course;
use App\Models\EnrollmentRequest;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentRegistrationIdentity;
use App\Models\StudentRegistrationRequest;
use App\Services\InstructorEnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InstructorClassListController extends Controller
{
    public function index()
    {
        return redirect()->route('instructor.schedule');
    }

    public function store()
    {
        return redirect()->route('instructor.schedule')->with('success', 'Open a subject to prepare its roster. No separate class list is needed.');
    }

    public function show(ClassList $classList)
    {
        Gate::authorize('update', $classList);

        return $classList->recurring_schedule_group_id
            ? redirect()->route('instructor.subjects.show', $classList->recurring_schedule_group_id)
            : redirect()->route('instructor.schedule')->with('success', 'Your saved students are preserved. Open a subject and use Copy students to bring them into its roster.');
    }

    public function addEntry(ClassList $classList)
    {
        Gate::authorize('update', $classList);
        abort(410, 'Open My Teaching Schedule to add students directly to a subject roster.');
    }

    public function removeEntry(ClassList $classList, ClassListEntry $entry)
    {
        Gate::authorize('update', $classList);
        abort(410, 'Open My Teaching Schedule to edit a subject roster.');
    }

    public function submit(ClassList $classList)
    {
        Gate::authorize('update', $classList);
        abort(410, 'Open My Teaching Schedule and submit the roster for that subject.');
    }

    public function editRequest(EnrollmentRequest $enrollmentRequest)
    {
        Gate::authorize('resubmit', $enrollmentRequest);
        $courses = Course::active()->orderBy('course_code')->get();
        $sections = Section::active()->orderBy('section_name')->get();

        return view('instructor.class-lists.resubmit', compact('enrollmentRequest', 'courses', 'sections'));
    }

    public function resubmit(Request $request, EnrollmentRequest $enrollmentRequest, InstructorEnrollmentService $service)
    {
        Gate::authorize('resubmit', $enrollmentRequest);
        $request->validate(['confirmed' => 'accepted']);
        $batch = DB::transaction(function () use ($request, $enrollmentRequest, $service) {
            $list = ClassList::findOrFail($enrollmentRequest->batch->class_list_id);
            $entry = $list->entries()->where('identity_id', $enrollmentRequest->identity_id)->first();
            if ($enrollmentRequest->registration_request_id && ! $enrollmentRequest->identity->student_id) {
                $details = $service->validateDetails($request->all());
                $identity = StudentRegistrationIdentity::firstOrCreate(['student_no' => $details['student_no']]);
                if (Student::whereRaw('UPPER(TRIM(student_no)) = ?', [$identity->student_no])->exists()) {
                    throw ValidationException::withMessages(['student_no' => 'This student is now registered. Add the existing student to your list and submit a new roster.']);
                }
                $registration = StudentRegistrationRequest::create(['identity_id' => $identity->id, 'instructor_id' => $list->instructor_id, 'details' => $details]);
                if ($entry && $entry->identity_id !== $identity->id) {
                    $entry->delete();
                    $entry = null;
                }
                $entry = $list->entries()->updateOrCreate(['identity_id' => $identity->id], ['student_id' => null, 'registration_request_id' => $registration->id]);
            }
            if (! $entry) {
                $entry = $list->entries()->create(['identity_id' => $enrollmentRequest->identity_id, 'student_id' => $enrollmentRequest->identity->student_id, 'registration_request_id' => $enrollmentRequest->registration_request_id]);
            }

            return $service->submit($list, $request->user(), [['group_id' => $enrollmentRequest->recurring_schedule_group_id, 'entry_ids' => [$entry->id], 'confirmed' => true]], $enrollmentRequest->id);
        }, 3);

        return redirect()->route('instructor.subjects.show', $enrollmentRequest->recurring_schedule_group_id)->with('success', 'Corrected request submitted in batch #'.$batch->id.'.');
    }
}
