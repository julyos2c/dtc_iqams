<?php

namespace App\Http\Controllers;

use App\Notifications\LeaveRequestNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveNotificationController extends Controller
{
    public function clear(Request $request)
    {
        $request->user()->notifications()->whereIn('type', [
            LeaveRequestNotification::class,
            \App\Notifications\ClassCancellationNotification::class,
            \App\Notifications\StudentExcuseNotification::class,
            \App\Notifications\EnrollmentApprovedNotification::class,
        ])->delete();

        return back();
    }

    public function open(Request $request, string $notification)
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $route = match ($item->type) {
            \App\Notifications\LeaveRequestNotification::class => match (true) {
                $request->user()->isAdmin() => 'admin.leave-requests.index',
                $request->user()->isStaff() => 'staff.leave-requests.index',
                default => 'leave-requests.index',
            },
            \App\Notifications\ClassCancellationNotification::class => $request->user()->isAdmin() ? 'admin.class-cancellations.index' : 'instructor.schedule',
            \App\Notifications\StudentExcuseNotification::class => $request->user()->isStudent() ? 'student.excuses.index' : 'instructor.excuses.index',
            \App\Notifications\EnrollmentApprovedNotification::class => 'instructor.schedule',
            default => abort(404),
        };
        $item->markAsRead();

        return redirect()->route($route);
    }

    public function read(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()
            ->where('type', LeaveRequestNotification::class)
            ->update(['read_at' => now()]);

        return response()->json(['unread_count' => 0]);
    }
}
