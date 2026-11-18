<?php

namespace App\Http\Controllers;

use App\Models\EnrollmentRequest;
use App\Models\Instructor;
use App\Models\Section;
use App\Models\Subject;
use App\Services\EnrollmentReviewService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminEnrollmentRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = EnrollmentRequest::with(['identity', 'instructor', 'reviewer'])->latest();
        foreach (['instructor_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        $status = $request->query('status', 'pending');
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }
        foreach (['subject_id', 'section_id'] as $field) {
            if ($request->filled($field)) {
                $query->where('snapshot->'.$field, (int) $request->input($field));
            }
        }
        if ($request->input('type') === 'registration') {
            $query->whereNotNull('registration_request_id');
        }
        if ($request->input('type') === 'enrollment') {
            $query->whereNull('registration_request_id');
        }
        $requests = $query->paginate(25)->withQueryString();
        $instructors = Instructor::orderBy('last_name')->get();
        $subjects = Subject::orderBy('subject_code')->get();
        $sections = Section::orderBy('section_name')->get();
        $pendingCount = EnrollmentRequest::where('status', 'pending')->count();

        return response()->view('admin.enrollment-requests.index', compact('requests', 'instructors', 'subjects', 'sections', 'pendingCount', 'status'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function review(Request $request, EnrollmentReviewService $service)
    {
        $data = $request->validate([
            'request_ids' => 'required|array|min:1|max:100', 'request_ids.*' => 'required|integer|distinct|exists:enrollment_requests,id',
            'decision' => 'required|in:approved,rejected', 'review_notes' => 'nullable|string|max:2000',
        ]);
        if ($data['decision'] === 'rejected') {
            $request->validate(['review_notes' => 'required|string|max:2000']);
        }
        $results = [];
        foreach ($data['request_ids'] as $id) {
            try {
                $result = $service->review(EnrollmentRequest::findOrFail($id), $request->user(), $data['decision'], $data['review_notes'] ?? null);
                unset($result['temporary_password']);
                $results[] = $result;
            } catch (ValidationException $exception) {
                $results[] = ['id' => $id, 'status' => 'unchanged', 'message' => collect($exception->errors())->flatten()->implode(' ')];
            } catch (QueryException $exception) {
                report($exception);
                $results[] = ['id' => $id, 'status' => 'unchanged', 'message' => 'A concurrent identity or enrollment change prevented review. Reload and resolve the conflict before retrying.'];
            }
        }

        return back()->with('enrollment_review_results', $results)->with('success', 'Review completed. Check each result below.');
    }
}
