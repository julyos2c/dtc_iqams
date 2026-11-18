<?php
namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\StudentExcuseDecision;
use App\Models\StudentExcuseRequest;
use App\Models\User;
use App\Services\StudentScheduleEligibility;
use App\Services\ScheduleOccurrenceResolver;
use Illuminate\Support\Carbon;
use App\Notifications\StudentExcuseNotification;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StudentExcuseController extends Controller
{
    public function studentIndex(Request $request)
    {
        $eligible = app(StudentScheduleEligibility::class)->schedulesFor($request->user()->student)
            ->whereHas('instructor.user')->with('subject')->orderBy('day')->orderBy('start_time')->get();
        $requests = StudentExcuseRequest::where('user_id', $request->user()->id)->with(['decisions.schedule.subject', 'decisions.attendanceLog.schedule.subject'])->latest()->paginate(10);
        $request->user()->unreadNotifications()->where('type', StudentExcuseNotification::class)->update(['read_at' => now()]);
        return view('student.excuses', compact('eligible', 'requests'));
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'occurrence_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'schedule_ids' => 'required|array|min:1|max:100', 'schedule_ids.*' => 'required|integer|distinct',
            'reason_type' => 'required|in:illness,emergency,other', 'explanation' => 'required|string|max:2000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);
        $path = $request->file('attachment')?->store('student-excuse-attachments', 'local');
        if ($request->hasFile('attachment') && ! $path) {
            throw ValidationException::withMessages(['attachment' => 'The attachment could not be stored. Please try again.']);
        }
        try {
            $excuse = DB::transaction(function () use ($request, $data, $path, $audit) {
                User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $date = Carbon::parse($data['occurrence_date'], config('app.timezone'))->startOfDay();
                $schedules = app(StudentScheduleEligibility::class)->schedulesFor($request->user()->student, $date)
                    ->whereIn('id', $data['schedule_ids'])->with('instructor.user')->lockForUpdate()->get();
                if ($schedules->count() !== count($data['schedule_ids'])) throw ValidationException::withMessages(['schedule_ids' => 'Select only your eligible classes.']);
                foreach ($schedules as $schedule) {
                    $occurrence = app(ScheduleOccurrenceResolver::class)->forDate($schedule, $date);
                    if (! $schedule->instructor?->user || ! $occurrence || $occurrence->startsAt->lessThanOrEqualTo(now())) throw ValidationException::withMessages(['schedule_ids' => 'Select classes on the chosen date that have not started.']);
                    if (StudentExcuseDecision::where('schedule_id', $schedule->id)->whereDate('occurrence_date', $date)->whereIn('status', ['pending', 'approved'])->whereHas('excuseRequest', fn ($q) => $q->where('user_id', $request->user()->id))->exists()) throw ValidationException::withMessages(['schedule_ids' => 'A selected class already has a pending or approved request.']);
                }
                $excuse = StudentExcuseRequest::create(['user_id' => $request->user()->id, 'reason_type' => $data['reason_type'], 'explanation' => $data['explanation'], 'attachment_path' => $path]);
                foreach ($schedules as $schedule) {
                    $excuse->decisions()->create(['schedule_id' => $schedule->id, 'occurrence_date' => $date->toDateString(), 'instructor_id' => $schedule->instructor_id]);
                }
                $audit->record('student_excuse.submitted', $excuse, ['class_count' => $schedules->count()], $request->user(), $request);
                return $excuse;
            });
        } catch (\Throwable $e) {
            if ($path) Storage::disk('local')->delete($path);
            throw $e;
        }
        foreach ($excuse->decisions()->with('instructor.user')->get()->pluck('instructor.user')->filter()->unique('id') as $instructor) {
            $instructor->notify(new StudentExcuseNotification('submitted', route('instructor.excuses.index')));
        }
        return redirect()->route('student.excuses.index')->with('success', 'Excuse request submitted.');
    }

    public function instructorIndex(Request $request)
    {
        $instructor = $request->user()->instructor ?? abort(403);
        $decisions = StudentExcuseDecision::where('instructor_id', $instructor->id)->with(['excuseRequest.studentUser', 'attendanceLog.schedule.subject', 'schedule.subject'])->latest()->paginate(20);
        $request->user()->unreadNotifications()->where('type', StudentExcuseNotification::class)->update(['read_at' => now()]);
        return view('instructor.excuses', compact('decisions'));
    }

    public function decide(Request $request, StudentExcuseDecision $decision, AuditLogger $audit)
    {
        abort_unless($request->user()->instructor?->id === $decision->instructor_id, 403);
        $data = $request->validate(['status' => 'required|in:approved,rejected', 'remarks' => 'nullable|string|max:2000|required_if:status,rejected']);
        DB::transaction(function () use ($decision, $data, $audit, $request) {
            $decision = StudentExcuseDecision::whereKey($decision->id)->lockForUpdate()->firstOrFail();
            if ($decision->status !== 'pending') throw ValidationException::withMessages(['status' => 'This request has already been reviewed.']);
            if ($decision->schedule_id) \App\Models\Schedule::whereKey($decision->schedule_id)->lockForUpdate()->firstOrFail();
            $log = $decision->attendance_log_id ? AttendanceLog::whereKey($decision->attendance_log_id)->lockForUpdate()->first() : null;
            if (! $log && $decision->schedule_id && $decision->occurrence_date) {
                $occurrence = app(ScheduleOccurrenceResolver::class)->forDate($decision->schedule, Carbon::parse($decision->occurrence_date));
                if ($occurrence) $log = AttendanceLog::canonical()->where('user_id', $decision->excuseRequest->user_id)->where('schedule_id', $decision->schedule_id)->where('attendance_type', 'time_in')->whereBetween('scan_time', [$occurrence->opensAt, $occurrence->endsAt])->lockForUpdate()->first();
            }
            if ($data['status'] === 'approved' && $log && $log->status === 'absent' && in_array($log->record_state, [null, 'canonical'], true)) {
                $log->update(['status' => 'excused']);
                $decision->attendance_log_id = $log->id;
            }
            $decision->update(['status' => $data['status'], 'remarks' => $data['remarks'] ?? null, 'decided_at' => now()]);
            $audit->record('student_excuse.'.$data['status'], $decision, ['attendance_log_id' => $log?->id, 'schedule_id' => $decision->schedule_id], $request->user(), $request);
        });
        $decision->excuseRequest->studentUser->notify(new StudentExcuseNotification($data['status'], route('student.excuses.index')));
        return back()->with('success', 'Excuse request '.$data['status'].'.');
    }

    public function attachment(Request $request, StudentExcuseRequest $excuse)
    {
        $owns = $request->user()->id === $excuse->user_id;
        $reviews = $request->user()->instructor && $excuse->decisions()->where('instructor_id', $request->user()->instructor->id)->exists();
        abort_unless($owns || $reviews, 403);
        abort_unless($excuse->attachment_path && Storage::disk('local')->exists($excuse->attachment_path), 404);
        return Storage::disk('local')->download($excuse->attachment_path);
    }
}
