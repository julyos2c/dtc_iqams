<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminLeaveRequestController;
use App\Http\Controllers\AdminLookupController;
use App\Http\Controllers\AttendanceLogController;
use App\Http\Controllers\AttendanceScannerController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DailyPersonnelAttendanceReportController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\IdCardController;
use App\Http\Controllers\InstructorAttendanceController;
use App\Http\Controllers\InstructorController;
use App\Http\Controllers\InstructorDashboardController;
use App\Http\Controllers\LeaveNotificationController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\MyProfileController;
use App\Http\Controllers\NonTeachingStaffController;
use App\Http\Controllers\OfficeUnitController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ScannerSecurityController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SchoolEventController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\StaffAttendanceController;
use App\Http\Controllers\StaffDashboardController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentAbsenceReportController;
use App\Http\Controllers\StudentDashboardController;
use App\Http\Controllers\StudentProfileController;
use App\Http\Controllers\StudentRecordsController;
use App\Http\Controllers\StudentScheduleController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\UserAccountPasswordController;
use App\Http\Controllers\UserAccountStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::view('/privacy-notice', 'privacy-notice')->name('privacy-notice');

Route::get('/dashboard', function () {
    return match (request()->user()->getRoleNames()->first()) {
        'admin' => redirect()->route('admin.dashboard'),
        'instructor' => redirect()->route('instructor.dashboard'),
        'student' => redirect()->route('student.dashboard'),
        'staff' => redirect()->route('staff.dashboard'),
        default => abort(403, 'No valid role assigned to this account.'),
    };
})->middleware(['auth', 'active', 'password.changed'])->name('dashboard');

Route::middleware(['auth', 'active', 'password.changed', 'redirect.non-admin.profile', 'role:admin'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->middleware('password.confirm')->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->middleware('password.confirm')->name('profile.destroy');
});

Route::middleware(['auth', 'active', 'password.changed', 'role:instructor|staff|student'])->group(function () {
    Route::get('/my-id-card', [IdCardController::class, 'show'])->name('id-card.show');
    Route::get('/my-profile', [MyProfileController::class, 'edit'])->name('my-profile.edit');
    Route::patch('/my-profile', [MyProfileController::class, 'update'])->name('my-profile.update');
});

Route::middleware(['auth', 'active', 'password.changed', 'role:instructor|staff'])->group(function () {
    Route::get('/leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
    Route::post('/leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
    Route::patch('/leave-requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel'])->name('leave-requests.cancel');
});

Route::post('/leave-notifications/read', [LeaveNotificationController::class, 'read'])
    ->middleware(['auth', 'active', 'password.changed'])->name('leave-notifications.read');
Route::post('/notifications/{notification}/open', [LeaveNotificationController::class, 'open'])
    ->middleware(['auth', 'active', 'password.changed'])->name('notifications.open');
Route::post('/notifications/clear', [LeaveNotificationController::class, 'clear'])
    ->middleware(['auth', 'active', 'password.changed'])->name('notifications.clear');

Route::middleware(['auth', 'active', 'password.changed', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('class-cancellations', [\App\Http\Controllers\ClassCancellationController::class, 'index'])->middleware('permission:manage-schedules')->name('class-cancellations.index');
    Route::patch('class-cancellations/{classCancellation}', [\App\Http\Controllers\ClassCancellationController::class, 'review'])->middleware(['permission:manage-schedules', 'password.confirm'])->name('class-cancellations.review');
    Route::get('dashboard', [AdminDashboardController::class, 'index'])->middleware('permission:view-reports')->name('dashboard');
    Route::get('dashboard/realtime', [AdminDashboardController::class, 'realtime'])->middleware('permission:view-reports')->name('dashboard.realtime');
    Route::get('dashboard/delta', [AdminDashboardController::class, 'delta'])->middleware('permission:view-reports')->name('dashboard.delta');
    Route::get('dashboard/analytics', [AdminDashboardController::class, 'analytics'])->middleware('permission:view-reports')->name('dashboard.analytics');
    Route::prefix('lookups')->name('lookups.')->group(function () {
        Route::get('people', [AdminLookupController::class, 'people'])->middleware('permission:manage-attendance')->name('people');
        Route::get('schedules', [AdminLookupController::class, 'schedules'])->middleware('permission:manage-attendance')->name('schedules');
        Route::get('subjects', [AdminLookupController::class, 'subjects'])->middleware('permission:manage-schedules')->name('subjects');
        Route::get('instructors', [AdminLookupController::class, 'instructors'])->middleware('permission:manage-schedules')->name('instructors');
        Route::get('sections', [AdminLookupController::class, 'sections'])->middleware('permission:manage-schedules')->name('sections');
    });
    Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:view-audit-logs')->name('audit-logs.index');
    Route::middleware('permission:view-reports')->prefix('reports/daily-personnel')->name('reports.daily-personnel.')->group(function () {
        Route::get('/', [DailyPersonnelAttendanceReportController::class, 'index'])->name('index');
        Route::post('/exports', [ReportExportController::class, 'store'])->name('exports.store');
    });
    Route::get('report-exports/{reportExport}', [ReportExportController::class, 'show'])->middleware('permission:view-reports')->name('report-exports.show');
    Route::get('reports/student-absences', [StudentAbsenceReportController::class, 'index'])->middleware('permission:view-reports')->name('reports.student-absences.index');
    Route::get('reports/student-absences/export', [StudentAbsenceReportController::class, 'export'])->middleware('permission:view-reports')->name('reports.student-absences.export');
    Route::get('reports/student-absences/{student}', [StudentAbsenceReportController::class, 'details'])->middleware('permission:view-reports')->name('reports.student-absences.details');
    Route::get('report-exports/{reportExport}/download', [ReportExportController::class, 'download'])->middleware('permission:view-reports')->name('report-exports.download');
    Route::get('leave-requests', [AdminLeaveRequestController::class, 'index'])->middleware('permission:review-leave-requests')->name('leave-requests.index');
    Route::get('enrollment-requests', [\App\Http\Controllers\AdminEnrollmentRequestController::class, 'index'])->middleware('permission:review-enrollment-requests')->name('enrollment-requests.index');
    Route::get('students/submissions', [\App\Http\Controllers\AdminEnrollmentRequestController::class, 'index'])->middleware(['permission:manage-users', 'permission:review-enrollment-requests'])->name('students.submissions');
    Route::post('enrollment-requests/review', [\App\Http\Controllers\AdminEnrollmentRequestController::class, 'review'])->middleware(['permission:review-enrollment-requests', 'password.confirm'])->name('enrollment-requests.review');
    Route::get('leave-requests/{leaveRequest}/attachment', [AdminLeaveRequestController::class, 'attachment'])->middleware('permission:review-leave-requests')->name('leave-requests.attachment');
    Route::patch('leave-requests/{leaveRequest}', [AdminLeaveRequestController::class, 'update'])->middleware(['permission:review-leave-requests', 'password.confirm'])->name('leave-requests.update');
});

Route::middleware(['auth', 'active', 'password.changed', 'role:admin'])->group(function () {
    Route::get('attendance-scanner', [AttendanceScannerController::class, 'index'])->middleware('permission:operate-scanner')->name('attendance-scanner.index');
    Route::post('attendance-scanner/terminal', [AttendanceScannerController::class, 'selectTerminal'])->middleware('permission:operate-scanner')->name('attendance-scanner.terminal');
    Route::middleware(['scanner.terminal', 'permission:operate-scanner'])->group(function () {
        Route::post('attendance-scanner/scan', [AttendanceScannerController::class, 'scan'])->middleware('scanner.throttle:scan')->name('attendance-scanner.scan');
    });
    Route::middleware('permission:manage-scanner-security')->group(function () {
        Route::get('scanner-security', [ScannerSecurityController::class, 'index'])->name('scanner-security.index');
        Route::get('scanner-security/users', [ScannerSecurityController::class, 'users'])->name('scanner-security.users');
        Route::post('scanner-security/terminals', [ScannerSecurityController::class, 'storeTerminal'])->name('scanner-security.terminals.store');
        Route::patch('scanner-security/terminals/{terminal}', [ScannerSecurityController::class, 'updateTerminal'])->middleware('password.confirm')->name('scanner-security.terminals.update');
        Route::post('scanner-security/users/{user}/qr/regenerate', [ScannerSecurityController::class, 'regenerate'])->middleware('password.confirm')->name('scanner-security.qr.regenerate');
        Route::post('scanner-security/qr/batch', [ScannerSecurityController::class, 'queueQrBatch'])->middleware('password.confirm')->name('scanner-security.qr.batch');
        Route::patch('scanner-security/flags/{flag}', [ScannerSecurityController::class, 'reviewFlag'])->middleware('password.confirm')->name('scanner-security.flags.update');
    });
    Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('permission:manage-academic-structure')->middlewareFor(['update', 'destroy'], 'password.confirm');
    Route::resource('courses', CourseController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('permission:manage-academic-structure')->middlewareFor(['update', 'destroy'], 'password.confirm');
    Route::resource('instructors', InstructorController::class)->except(['create', 'edit', 'show'])->middleware('permission:manage-users')->middlewareFor(['update', 'destroy'], 'password.confirm');
    Route::get('roles', [RoleController::class, 'index'])->middleware('permission:manage-role-assignments')->name('roles.index');
    Route::patch('roles/users/{user}', [RoleController::class, 'assign'])->middleware(['permission:manage-role-assignments', 'password.confirm'])->name('roles.assign');
    Route::resource('non-teaching-staff', NonTeachingStaffController::class)->except(['create', 'edit', 'show'])->middleware('permission:manage-users')->middlewareFor(['update', 'destroy'], 'password.confirm');
    Route::resource('office-units', OfficeUnitController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('permission:manage-office-units')->middlewareFor(['update', 'destroy'], 'password.confirm');
    Route::resource('subjects', SubjectController::class)->except('create', 'edit', 'show')->middleware('permission:manage-academic-structure')->middlewareFor(['update', 'destroy'], 'password.confirm');
    Route::resource('sections', SectionController::class)->except(['create', 'edit', 'show'])->middleware('permission:manage-academic-structure')->middlewareFor(['update', 'destroy'], 'password.confirm');
    Route::resource('students', StudentController::class)->except(['create', 'edit', 'show'])->middleware('permission:manage-users')->middlewareFor(['update', 'destroy'], 'password.confirm');
    Route::resource('schedules', ScheduleController::class)->except(['create', 'edit', 'show'])->middleware('permission:manage-schedules')->middlewareFor(['update', 'destroy'], 'password.confirm');
    Route::resource('attendance-logs', AttendanceLogController::class)->except(['create', 'edit', 'show'])->middleware('permission:manage-attendance')->middlewareFor(['update', 'destroy'], 'password.confirm');
    Route::resource('school-events', SchoolEventController::class)
        ->parameters(['school-events' => 'schoolEvent'])->except(['show', 'create', 'edit'])->middleware('permission:manage-school-events')->middlewareFor(['update', 'destroy'], 'password.confirm');
    Route::patch('school-events/{schoolEvent}/publish', [SchoolEventController::class, 'publish'])->middleware(['permission:manage-school-events', 'password.confirm'])->name('school-events.publish');
    Route::patch('school-events/{schoolEvent}/cancel', [SchoolEventController::class, 'cancel'])->middleware(['permission:manage-school-events', 'password.confirm'])->name('school-events.cancel');
    Route::patch('users/{user}/status', [UserAccountStatusController::class, 'update'])->middleware(['permission:manage-users', 'password.confirm'])->name('users.status.update');
    Route::post('users/{user}/password/reset', [UserAccountPasswordController::class, 'reset'])->middleware(['permission:manage-users', 'password.confirm'])->name('users.password.reset');
    Route::get('admin/users/{user}/qr', [\App\Http\Controllers\QrManagementController::class, 'show'])->middleware('permission:manage-users')->name('admin.qr.show');
    Route::post('admin/users/{user}/qr/revoke', [\App\Http\Controllers\QrManagementController::class, 'revoke'])->middleware('permission:manage-users')->name('admin.qr.revoke');
    Route::post('admin/users/{user}/qr/reissue', [\App\Http\Controllers\QrManagementController::class, 'reissue'])->middleware('permission:manage-users')->name('admin.qr.reissue');
    Route::get('admin/id-cards/{user}', [IdCardController::class, 'adminShow'])->middleware('permission:manage-users')->name('admin.id-card.show');
});

Route::middleware(['auth', 'active', 'password.changed', 'role:student'])->prefix('student')->name('student.')->group(function () {
    Route::get('excuses', [\App\Http\Controllers\StudentExcuseController::class, 'studentIndex'])->name('excuses.index');
    Route::post('excuses', [\App\Http\Controllers\StudentExcuseController::class, 'store'])->name('excuses.store');
    Route::get('excuses/{excuse}/attachment', [\App\Http\Controllers\StudentExcuseController::class, 'attachment'])->name('excuses.attachment');
    Route::get('dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/realtime', [StudentDashboardController::class, 'realtime'])->name('dashboard.realtime');
    Route::get('schedule', [StudentScheduleController::class, 'index'])->name('schedule');
    Route::get('profile', [StudentProfileController::class, 'show'])->name('profile');
    Route::get('profile/qr', [StudentProfileController::class, 'qr'])->name('profile.qr');
    Route::patch('profile/contact', [StudentProfileController::class, 'updateContact'])->name('profile.contact');
    Route::put('profile/photo', [StudentProfileController::class, 'updatePhoto'])->name('profile.photo');
    Route::delete('profile/photo', [StudentProfileController::class, 'removePhoto'])->name('profile.photo.destroy');
    Route::get('attendance', [StudentRecordsController::class, 'attendance'])->name('attendance');
    Route::get('settings', [StudentProfileController::class, 'settings'])->name('settings');
    Route::put('settings/password', [StudentProfileController::class, 'updatePassword'])->name('password');
});

Route::middleware(['auth', 'active', 'password.changed', 'role:staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('dashboard', [StaffDashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/realtime', [StaffDashboardController::class, 'realtime'])->name('dashboard.realtime');
    Route::get('attendance/history', [StaffAttendanceController::class, 'history'])->name('attendance.history');
    Route::get('attendance/summary', [StaffAttendanceController::class, 'summary'])->name('attendance.summary');
    Route::get('attendance/issues', [StaffAttendanceController::class, 'issues'])->name('attendance.issues');
    Route::get('profile', [MyProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [MyProfileController::class, 'update'])->name('profile.update');
    Route::get('settings', [MyProfileController::class, 'staffSettings'])->name('settings');
    Route::patch('settings', [MyProfileController::class, 'update'])->name('settings.update');
    Route::get('leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
    Route::post('leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
    Route::patch('leave-requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel'])->name('leave-requests.cancel');
});

Route::middleware(['auth', 'active', 'password.changed', 'role:instructor'])->prefix('instructor')->name('instructor.')->group(function () {
    Route::get('excuses', [\App\Http\Controllers\StudentExcuseController::class, 'instructorIndex'])->name('excuses.index');
    Route::patch('excuses/{decision}', [\App\Http\Controllers\StudentExcuseController::class, 'decide'])->name('excuses.decide');
    Route::get('excuses/attachment/{excuse}', [\App\Http\Controllers\StudentExcuseController::class, 'attachment'])->name('excuses.attachment');
    Route::get('settings', [MyProfileController::class, 'settings'])->name('settings');
    Route::patch('settings/email', [MyProfileController::class, 'updateEmail'])->name('settings.email');
    Route::get('subjects', [\App\Http\Controllers\InstructorSubjectRosterController::class, 'index'])->name('subjects.index');
    Route::get('subjects/{groupId}', [\App\Http\Controllers\InstructorSubjectRosterController::class, 'show'])->whereUuid('groupId')->middleware('throttle:60,1')->name('subjects.show');
    Route::get('subjects/{groupId}/students/search', [\App\Http\Controllers\InstructorSubjectRosterController::class, 'searchStudents'])->whereUuid('groupId')->middleware('throttle:60,1')->name('subjects.students.search');
    Route::post('subjects/{groupId}/students', [\App\Http\Controllers\InstructorSubjectRosterController::class, 'add'])->whereUuid('groupId')->middleware('throttle:60,1')->name('subjects.students.store');
    Route::delete('subjects/{groupId}/students/{entry}', [\App\Http\Controllers\InstructorSubjectRosterController::class, 'remove'])->whereUuid('groupId')->name('subjects.students.destroy');
    Route::get('subjects/{groupId}/copy-preview', [\App\Http\Controllers\InstructorSubjectRosterController::class, 'copyPreview'])->whereUuid('groupId')->middleware('throttle:60,1')->name('subjects.copy-preview');
    Route::post('subjects/{groupId}/copy', [\App\Http\Controllers\InstructorSubjectRosterController::class, 'copy'])->whereUuid('groupId')->middleware('throttle:20,1')->name('subjects.copy');
    Route::post('subjects/{groupId}/submit', [\App\Http\Controllers\InstructorSubjectRosterController::class, 'submit'])->whereUuid('groupId')->middleware('throttle:10,1')->name('subjects.submit');
    Route::get('class-lists', [\App\Http\Controllers\InstructorClassListController::class, 'index'])->name('class-lists.index');
    Route::post('class-lists', [\App\Http\Controllers\InstructorClassListController::class, 'store'])->middleware('throttle:30,1')->name('class-lists.store');
    Route::get('class-lists/{classList}', [\App\Http\Controllers\InstructorClassListController::class, 'show'])->middleware('throttle:60,1')->name('class-lists.show');
    Route::post('class-lists/{classList}/entries', [\App\Http\Controllers\InstructorClassListController::class, 'addEntry'])->middleware('throttle:60,1')->name('class-lists.entries.store');
    Route::delete('class-lists/{classList}/entries/{entry}', [\App\Http\Controllers\InstructorClassListController::class, 'removeEntry'])->name('class-lists.entries.destroy');
    Route::post('class-lists/{classList}/submit', [\App\Http\Controllers\InstructorClassListController::class, 'submit'])->middleware('throttle:10,1')->name('class-lists.submit');
    Route::get('enrollment-requests/{enrollmentRequest}/resubmit', [\App\Http\Controllers\InstructorClassListController::class, 'editRequest'])->name('enrollment-requests.edit');
    Route::post('enrollment-requests/{enrollmentRequest}/resubmit', [\App\Http\Controllers\InstructorClassListController::class, 'resubmit'])->middleware('throttle:10,1')->name('enrollment-requests.resubmit');
    Route::get('dashboard', [InstructorDashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/realtime', [InstructorDashboardController::class, 'realtime'])->name('dashboard.realtime');
    Route::get('attendance', [InstructorAttendanceController::class, 'history'])->name('attendance');
    Route::get('summary', [InstructorAttendanceController::class, 'summary'])->name('summary');
    Route::get('issues', [InstructorAttendanceController::class, 'issues'])->name('issues');
    Route::get('schedule', [InstructorAttendanceController::class, 'schedule'])->name('schedule');
    Route::post('schedule/{schedule}/cancellation', [\App\Http\Controllers\ClassCancellationController::class, 'store'])->name('schedule.cancellation');
    Route::get('schedule/{schedule}/attendance', [InstructorAttendanceController::class, 'classAttendance'])->name('schedule.attendance');
    Route::get('schedule/{schedule}/attendance/download', [InstructorAttendanceController::class, 'downloadClassAttendance'])->name('schedule.attendance.download');
});

require __DIR__.'/auth.php';
