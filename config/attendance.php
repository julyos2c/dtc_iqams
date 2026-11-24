<?php

return [
    'operational_sync' => (bool) env('SCANNER_OPERATIONAL_SYNC', false),
    'scanner_queue_preview' => (bool) env('SCANNER_QUEUE_PREVIEW', false),
    'rehearsal_sync' => (bool) env('SCANNER_REHEARSAL_SYNC', false),
    'scanner_rehearsal_rate' => (int) env('SCANNER_REHEARSAL_REQUESTS_PER_MINUTE', 240),
    'heartbeat_seconds' => (int) env('SCANNER_HEARTBEAT_SECONDS', 30),
    'suspect_after_seconds' => (int) env('SCANNER_SUSPECT_AFTER_SECONDS', 90),
    'lease_hours' => (int) env('SCANNER_LEASE_HOURS', 8),
    'batch_size' => (int) env('SCANNER_BATCH_SIZE', 25),
    'clock_tolerance_seconds' => (int) env('SCANNER_CLOCK_TOLERANCE_SECONDS', 120),
    'escalation_operating_days' => (int) env('SCANNER_ESCALATION_OPERATING_DAYS', 1),
    'early_scan_minutes' => (int) env('ATTENDANCE_EARLY_SCAN_MINUTES', 15),
    'present_grace_minutes' => (int) env('ATTENDANCE_PRESENT_GRACE_MINUTES', 15),
    'duplicate_cooldown_seconds' => (int) env('ATTENDANCE_DUPLICATE_COOLDOWN_SECONDS', 5),
    'personnel_daily_scan_limit' => (int) env('ATTENDANCE_PERSONNEL_DAILY_SCAN_LIMIT', 4),
    'scanner_idle_submit_milliseconds' => (int) env('ATTENDANCE_SCANNER_IDLE_SUBMIT_MS', 350),
    'legacy_qr_cutoff' => env('ATTENDANCE_LEGACY_QR_CUTOFF'),
    'preview_ttl_seconds' => (int) env('ATTENDANCE_PREVIEW_TTL_SECONDS', 30),
    'scanner_scan_rate' => (int) env('ATTENDANCE_SCANNER_SCAN_RATE', 120),
    'invalid_scan_threshold' => (int) env('ATTENDANCE_INVALID_SCAN_THRESHOLD', 5),
    'max_report_days' => (int) env('ATTENDANCE_MAX_REPORT_DAYS', 366),
    'report_export_ttl_hours' => (int) env('ATTENDANCE_REPORT_EXPORT_TTL_HOURS', 24),

    'personnel_windows' => [
        'instructor' => [
            'morning_in' => [
                'label' => 'Morning Time In',
                'type' => 'time_in',
                'start' => env('ATTENDANCE_INSTRUCTOR_MORNING_IN_START', '06:30'),
                'end' => env('ATTENDANCE_INSTRUCTOR_MORNING_IN_END', '08:30'),
                'on_time_until' => env('ATTENDANCE_INSTRUCTOR_MORNING_IN_ON_TIME_UNTIL', '08:00'),
            ],
            'lunch_out' => [
                'label' => 'Lunch Time Out',
                'type' => 'time_out',
                'start' => env('ATTENDANCE_INSTRUCTOR_LUNCH_OUT_START', '11:30'),
                'end' => env('ATTENDANCE_INSTRUCTOR_LUNCH_OUT_END', '12:30'),
                'not_early_before' => env('ATTENDANCE_INSTRUCTOR_LUNCH_OUT_NOT_EARLY_BEFORE', '12:00'),
            ],
            'afternoon_in' => [
                'label' => 'Afternoon Time In',
                'type' => 'time_in',
                'start' => env('ATTENDANCE_INSTRUCTOR_AFTERNOON_IN_START', '12:30'),
                'end' => env('ATTENDANCE_INSTRUCTOR_AFTERNOON_IN_END', '13:30'),
                'on_time_until' => env('ATTENDANCE_INSTRUCTOR_AFTERNOON_IN_ON_TIME_UNTIL', '13:00'),
            ],
            'final_out' => [
                'label' => 'Final Time Out',
                'type' => 'time_out',
                'start' => env('ATTENDANCE_INSTRUCTOR_FINAL_OUT_START', '16:30'),
                'end' => env('ATTENDANCE_INSTRUCTOR_FINAL_OUT_END', '18:00'),
                'not_early_before' => env('ATTENDANCE_INSTRUCTOR_FINAL_OUT_NOT_EARLY_BEFORE', '17:00'),
            ],
        ],
        'staff' => [
            'morning_in' => [
                'label' => 'Morning Time In',
                'type' => 'time_in',
                'start' => env('ATTENDANCE_STAFF_MORNING_IN_START', '06:30'),
                'end' => env('ATTENDANCE_STAFF_MORNING_IN_END', '08:30'),
            ],
            'lunch_out' => [
                'label' => 'Lunch Time Out',
                'type' => 'time_out',
                'start' => env('ATTENDANCE_STAFF_LUNCH_OUT_START', '11:30'),
                'end' => env('ATTENDANCE_STAFF_LUNCH_OUT_END', '12:30'),
            ],
            'afternoon_in' => [
                'label' => 'Afternoon Time In',
                'type' => 'time_in',
                'start' => env('ATTENDANCE_STAFF_AFTERNOON_IN_START', '12:30'),
                'end' => env('ATTENDANCE_STAFF_AFTERNOON_IN_END', '13:30'),
            ],
            'final_out' => [
                'label' => 'Final Time Out',
                'type' => 'time_out',
                'start' => env('ATTENDANCE_STAFF_FINAL_OUT_START', '16:30'),
                'end' => env('ATTENDANCE_STAFF_FINAL_OUT_END', '18:00'),
            ],
        ],
    ],
];
