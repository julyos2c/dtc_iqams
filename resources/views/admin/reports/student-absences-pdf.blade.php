<!doctype html><html><head><meta charset="utf-8"><style>
@page { margin: 28px 28px 40px; } body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; } h1,h2 { text-align:center; margin: 3px; } h1 { font-size: 14px; } h2 { font-size: 12px; } p { margin: 5px 0; } table { width:100%; border-collapse:collapse; margin-top:10px; } thead { display:table-header-group; } tr { page-break-inside:avoid; } th,td { border:1px solid #aaa; padding:5px; vertical-align:top; overflow-wrap:break-word; } th { background:#eee; text-align:left; } .meta { font-size:8px; }
</style></head><body>
<h1>DANAO TECHNOLOGICAL COLLEGE</h1><h2>STUDENT ABSENCE MONITORING REPORT</h2>
<p class="meta">Report: {{ ucfirst($type) }} | Generated: {{ $generatedAt->format('M d, Y g:i A') }} | Reporting period: {{ $filters['from'] ?? 'All dates' }} to {{ $filters['to'] ?? 'present' }}</p>
<p class="meta">Academic year(s): {{ $rows->pluck('school_year')->filter()->unique()->implode(', ') ?: 'Not assigned' }} | Filters: @foreach($filters as $key => $value) @if($value !== null && $value !== '' && $key !== 'type') {{ ucfirst($key) }}: {{ $value }}; @endif @endforeach</p>
<p>Affected students: {{ $summary->affected }} &nbsp; | &nbsp; Total absences: {{ $summary->absences }} &nbsp; | &nbsp; At-risk students: {{ $summary->at_risk }}</p>
<table><thead><tr><th>Student ID</th><th>Student name</th><th>Course / Section</th><th>Subject</th><th>Instructor</th><th>Absences</th><th>Risk</th>@if($type === 'detailed')<th>Dates and class time</th>@endif</tr></thead><tbody>
@foreach($rows->groupBy(fn ($row) => $row->student_id.'|'.$row->subject_id) as $group)
    @php($first = $group->first())
    @foreach($type === 'detailed' ? $group : collect([$first]) as $row)
    <tr><td>{{ $first->student_no }}</td><td>{{ implode(' ', array_filter([$first->first_name, $first->middle_name, $first->last_name, $first->name_suffix])) }}</td><td>{{ $first->course_code ?? '—' }} / {{ $first->section_name ?? '—' }}</td><td>{{ $first->subject_code }} — {{ $first->subject_name }}</td><td>{{ $type === 'detailed' ? (trim(($row->instructor_first ?? '').' '.($row->instructor_last ?? '')) ?: '—') : ($group->map(fn ($item) => trim(($item->instructor_first ?? '').' '.($item->instructor_last ?? '')))->filter()->unique()->implode(', ') ?: '—') }}</td><td>{{ $group->count() }}</td><td>{{ $group->count() >= \App\Services\StudentAbsenceWarningService::THRESHOLD ? 'At Risk' : 'Normal' }}</td>@if($type === 'detailed')<td>{{ $row->attendance_date }} ({{ $row->start_time }}–{{ $row->end_time }})</td>@endif</tr>
    @endforeach
@endforeach
</tbody></table></body></html>
