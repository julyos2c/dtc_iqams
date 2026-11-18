<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentAbsenceReportRequest;
use App\Models\Course;
use App\Models\Department;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Services\AuditLogger;
use App\Services\StudentAbsenceReport;
use Dompdf\Dompdf;
use Dompdf\Options;

class StudentAbsenceReportController extends Controller
{
    public function index(StudentAbsenceReportRequest $request, StudentAbsenceReport $report)
    {
        $filters = $request->validated();
        $students = $report->students($filters)->paginate(15)->withQueryString();
        $summary = $report->summary($filters);
        $departments = Department::orderBy('department_name')->get(['id', 'department_name']);
        $courses = Course::orderBy('course_code')->get(['id', 'department_id', 'course_code']);
        $sections = Section::orderBy('section_name')->get(['id', 'course_id', 'section_name']);
        $subjects = Subject::orderBy('subject_code')->get(['id', 'subject_code', 'subject_name']);
        return view('admin.reports.student-absences', compact('students', 'summary', 'departments', 'courses', 'sections', 'subjects'));
    }

    public function details(StudentAbsenceReportRequest $request, Student $student, StudentAbsenceReport $report)
    {
        $filters = $request->validated();
        $row = $report->students($filters)->where('st.id', $student->id)->first();
        abort_unless($row, 404);
        return response()->json(['student' => $row, 'records' => $report->details($filters, $student->id)->get()]);
    }

    public function export(StudentAbsenceReportRequest $request, StudentAbsenceReport $report, AuditLogger $audit)
    {
        $filters = $request->validated();
        $summary = $report->summary($filters);
        if (! $summary->affected) return redirect()->route('admin.reports.student-absences.index', $filters)->with('error', 'No matching absences to export.');
        $type = $filters['type'] ?? 'summary';
        $rows = $report->details($filters)->get();
        $generatedAt = now(config('app.timezone'));
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('admin.reports.student-absences-pdf', compact('rows', 'summary', 'filters', 'type', 'generatedAt'))->render());
        $pdf->setPaper('a4', 'landscape');
        $pdf->render();
        $pdf->getCanvas()->page_text(740, 570, 'Page {PAGE_NUM} of {PAGE_COUNT}', null, 8);
        $audit->record('report.student_absences_exported', null, ['type' => $type, 'filters' => array_diff_key($filters, ['search' => true]), 'search_applied' => ! empty($filters['search']), 'result' => 'success'], $request->user(), $request);
        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="IQAMS_Student_Absences_'.$generatedAt->format('Y-m-d').'.pdf"']);
    }
}
