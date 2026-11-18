<?php

namespace App\Http\Controllers;

use App\Models\ClassCancellationRequest;
use App\Models\Schedule;
use App\Services\ClassCancellationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ClassCancellationController extends Controller
{
    public function store(Request $request, Schedule $schedule, ClassCancellationService $service)
    {
        $data = $request->validate(['occurrence_date' => ['required', 'date_format:Y-m-d'], 'reason' => ['required', 'string', 'max:2000']]);
        $service->submit($schedule, Carbon::createFromFormat('!Y-m-d', $data['occurrence_date'], config('app.timezone')), $data['reason'], $request->user());
        return back()->with('success', 'Class cancellation request submitted.');
    }

    public function index(Request $request)
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])]]);
        $items = ClassCancellationRequest::with(['schedule.subject', 'schedule.section', 'requester', 'reviewer'])
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))->latest()->paginate(20)->withQueryString();
        return view('admin.class-cancellations.index', compact('items'));
    }

    public function review(Request $request, ClassCancellationRequest $classCancellation, ClassCancellationService $service)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected'])], 'review_notes' => ['nullable', 'string', 'max:2000']]);
        $service->review($classCancellation, $data['status'], $data['review_notes'] ?? null, $request->user());
        return back()->with('success', 'Cancellation request reviewed.');
    }
}
