<?php

namespace App\Http\Controllers;

use App\Models\ClassList;
use App\Models\ClassListEntry;
use App\Models\Course;
use App\Models\EnrollmentRequest;
use App\Models\Section;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudentRegistrationIdentity;
use App\Services\InstructorEnrollmentService;
use App\Services\SubjectRosterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InstructorSubjectRosterController extends Controller
{
    public function index(Request $request)
    {
        return redirect()->route('instructor.schedule', $request->only(['status', 'requests_page']));
    }

    public function show(Request $request, string $groupId, SubjectRosterService $service, InstructorEnrollmentService $enrollments)
    {
        $meetings = $service->meetings($request->user(), $groupId);
        $roster = $service->roster($request->user(), $groupId);
        $entries = $roster?->entries()->with(['identity.student', 'student', 'registration'])->get() ?? collect();
        $history = EnrollmentRequest::where('instructor_id', $request->user()->instructor->id)
            ->where('recurring_schedule_group_id', $groupId)->with('identity')->orderByDesc('id')->get()->groupBy('identity_id');
        $enrolled = $service->enrolledStudents($groupId)->get();
        $enrolledNumbers = $enrolled->map(fn ($student) => StudentRegistrationIdentity::normalize($student->student_no))->all();
        $rows = collect();
        $entryByIdentity = $entries->keyBy('identity_id');
        foreach ($entries as $entry) {
            $student = $entry->identity->student ?? $entry->student;
            $details = $entry->registration?->details ?? [];
            $rows->put($entry->identity->student_no, [
                'number' => $entry->identity->student_no, 'entry_id' => $entry->id,
                'name' => $student?->fullName() ?? trim(($details['first_name'] ?? '').' '.($details['last_name'] ?? '')),
                'status' => 'draft', 'new_registration' => ! $student, 'history' => [], 'correction_url' => null,
            ]);
        }
        foreach ($history as $identityId => $items) {
            $latest = $items->first();
            $number = $latest->identity->student_no;
            $row = $rows->get($number, [
                'number' => $number, 'entry_id' => null,
                'name' => trim(($latest->snapshot['student']['first_name'] ?? '').' '.($latest->snapshot['student']['last_name'] ?? '')),
                'status' => $latest->status, 'new_registration' => ! $latest->student_id,
            ]);
            $row['status'] = $latest->status === 'pending' ? 'pending' : ($latest->status === 'rejected' ? 'rejected' : ($entryByIdentity->has($identityId) ? 'draft' : 'approved'));
            $row['correction_url'] = $row['status'] === 'rejected' ? route('instructor.enrollment-requests.edit', $latest) : null;
            $row['history'] = $items->map(fn ($item) => ['status' => $item->status, 'notes' => $item->review_notes, 'date' => $item->created_at?->format('M j, Y')])->values()->all();
            $rows->put($number, $row);
        }
        foreach ($enrolled as $student) {
            $number = StudentRegistrationIdentity::normalize($student->student_no);
            $row = $rows->get($number, ['number' => $number, 'entry_id' => null, 'new_registration' => false, 'history' => []]);
            $row['name'] = $student->fullName();
            $row['status'] = 'approved';
            $row['correction_url'] = null;
            $rows->put($number, $row);
        }
        $rows = $rows->sortBy('number');
        $draftIds = $rows->where('status', 'draft')->pluck('entry_id')->filter()->all();
        $readyEntries = $entries->whereIn('id', $draftIds);
        $offerings = $enrollments->offerings($request->user())->toBase()->except($groupId);
        $savedDrafts = ClassList::where('instructor_id', $request->user()->instructor->id)->whereNull('recurring_schedule_group_id')->with('section')->get();
        $copyFrom = (string) $request->query('copy_from', '');
        $candidates = $copyFrom !== '' ? $service->copyCandidates($request->user(), $groupId, $copyFrom) : collect();
        $search = trim((string) $request->query('search', ''));
        $students = mb_strlen($search) >= 2 ? Student::where('status', 'active')->whereHas('user', fn ($query) => $query->where('status', 'active')->whereHas('roles', fn ($query) => $query->where('name', 'student')))
            ->where(fn ($query) => $query->where('student_no', 'like', '%'.addcslashes($search, '%_\\').'%')->orWhere('last_name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->orderBy('student_no')->limit(25)->get(['id', 'student_no', 'first_name', 'last_name', 'middle_name', 'name_suffix']) : collect();
        $courses = Course::active()->orderBy('course_code')->get();
        $sections = Section::active()->orderBy('section_name')->get();

        return view('instructor.subjects.show', compact('groupId', 'meetings', 'rows', 'readyEntries', 'enrolled', 'offerings', 'savedDrafts', 'copyFrom', 'candidates', 'search', 'students', 'courses', 'sections'));
    }

    public function searchStudents(Request $request, string $groupId, SubjectRosterService $service)
    {
        $service->meetings($request->user(), $groupId);
        $data = $request->validate(['q' => 'required|string|min:2|max:100', 'page' => 'nullable|integer|min:1']);
        $term = trim($data['q']);
        if (mb_strlen($term) < 2) throw ValidationException::withMessages(['q' => 'Enter at least two characters.']);
        $pattern = '%'.addcslashes(mb_strtolower($term), '%_\\').'%';
        $students = Student::where('status', 'active')->whereHas('user', fn ($q) => $q->where('status', 'active')->whereHas('roles', fn ($roles) => $roles->where('name', 'student')))
            ->where(fn ($q) => $q->whereRaw('LOWER(student_no) LIKE ?', [$pattern])->orWhereRaw('LOWER(first_name) LIKE ?', [$pattern])->orWhereRaw('LOWER(last_name) LIKE ?', [$pattern]))
            ->with(['course:id,course_code', 'section:id,section_name'])->orderBy('student_no')->paginate(15, ['id', 'student_no', 'first_name', 'middle_name', 'last_name', 'name_suffix', 'course_id', 'section_id']);
        $statuses = $service->studentStatuses($request->user(), $groupId, $students->getCollection());
        $labels = ['available' => ['Available', null], 'enrolled' => ['Already Enrolled', 'Already enrolled in this subject.'], 'pending' => ['Pending Approval', 'Enrollment request pending approval.'], 'draft' => ['Already in Draft', 'Already added as draft.'], 'ineligible' => ['Not Eligible', 'Identity conflict requires administrator review.']];

        return response()->json(['students' => $students->getCollection()->map(function ($student) use ($statuses, $labels) {
            $status = $statuses[$student->id];
            return ['id' => $student->id, 'number' => $student->student_no, 'name' => $student->fullName(), 'course' => $student->course?->course_code, 'section' => $student->section?->section_name, 'status' => $status, 'status_label' => $labels[$status][0], 'reason' => $labels[$status][1]];
        })->values(), 'total' => $students->total(), 'has_more' => $students->hasMorePages(), 'page' => $students->currentPage()]);
    }

    public function add(Request $request, string $groupId, SubjectRosterService $service, InstructorEnrollmentService $enrollments)
    {
        $service->meetings($request->user(), $groupId);
        $data = $request->validate(['student_id' => 'nullable|integer|exists:students,id']);
        DB::transaction(function () use ($request, $groupId, $service, $enrollments, $data) {
            Schedule::where('recurring_schedule_group_id', $groupId)->lockForUpdate()->get();
            $student = ! empty($data['student_id']) ? Student::with('user.roles')->findOrFail($data['student_id']) : null;
            if ($student) {
                $enrollments->assertStudentActive($student);
                $status = $service->studentStatuses($request->user(), $groupId, collect([$student]))[$student->id];
                if ($status !== 'available') {
                    if ($request->expectsJson()) abort(409, 'This student is no longer available to add.');
                    throw ValidationException::withMessages(['student' => 'This student is already enrolled, pending, in the draft, or has an identity conflict.']);
                }
            }
            $roster = $service->roster($request->user(), $groupId, true);
            $enrollments->addEntry($roster, $request->user(), $student, $request->all());
        }, 3);

        if ($request->expectsJson()) return response()->json(['message' => 'Student added as draft.', 'status' => 'draft']);

        return redirect()->route('instructor.subjects.show', $groupId)->with('success', 'Student added to this subject draft. Review the roster and submit it to the admin.');
    }

    public function remove(Request $request, string $groupId, ClassListEntry $entry, SubjectRosterService $service)
    {
        $roster = $service->roster($request->user(), $groupId);
        abort_unless($roster && $entry->class_list_id === $roster->id, 404);
        $student = $entry->identity->student ?? $entry->student;
        if (EnrollmentRequest::where('identity_id', $entry->identity_id)->where('recurring_schedule_group_id', $groupId)->where('status', 'pending')->exists()
            || ($student && $student->scheduleEnrollments()->where('recurring_schedule_group_id', $groupId)->exists())) {
            throw ValidationException::withMessages(['student' => 'Only unsent draft students can be removed here. Pending requests and approved enrollments are maintained by the admin.']);
        }
        $entry->delete();

        return redirect()->route('instructor.subjects.show', $groupId)->with('success', 'Removed from the draft. Existing requests and approved enrollments are unchanged.');
    }

    public function copyPreview(Request $request, string $groupId, SubjectRosterService $service)
    {
        $data = $request->validate(['copy_from' => 'required|string|max:100']);
        $candidates = $service->eligibleCopyCandidates($request->user(), $groupId, $data['copy_from']);

        return response()->json(['students' => $candidates->map(fn ($candidate) => [
            'key' => $candidate['key'], 'number' => $candidate['number'], 'name' => $candidate['name'],
        ])->values()]);
    }

    public function copy(Request $request, string $groupId, SubjectRosterService $service)
    {
        $data = $request->validate(['copy_from' => 'required|string|max:100', 'students' => 'required|array|min:1|max:500', 'students.*' => 'required|string|distinct|max:80']);
        $count = $service->copy($request->user(), $groupId, $data['copy_from'], $data['students']);

        if ($request->expectsJson()) {
            return response()->json(['count' => $count, 'skipped' => count($data['students']) - $count]);
        }

        return redirect()->route('instructor.subjects.show', $groupId)->with('success', $count.' students copied to this subject draft. Check its official roster before submitting.');
    }

    public function submit(Request $request, string $groupId, SubjectRosterService $service, InstructorEnrollmentService $enrollments)
    {
        $data = $request->validate(['entry_ids' => 'required|array|min:1|max:500', 'entry_ids.*' => 'required|integer|distinct', 'confirmed' => 'accepted']);
        $roster = $service->roster($request->user(), $groupId);
        abort_unless($roster, 422, 'Add students to this subject before submitting.');
        $batch = $enrollments->submit($roster, $request->user(), [['group_id' => $groupId, 'entry_ids' => $data['entry_ids'], 'confirmed' => true]]);

        return redirect()->route('instructor.subjects.show', $groupId)->with('success', $batch->requests()->count().' student requests sent to the admin.');
    }
}
