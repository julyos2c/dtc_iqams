<?php

namespace App\Notifications;

use App\Models\EnrollmentRequest;
use Illuminate\Notifications\Notification;

class EnrollmentApprovedNotification extends Notification
{
    public function __construct(private EnrollmentRequest $request) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toDatabase(object $notifiable): array
    {
        return [
            'group_id' => $this->request->recurring_schedule_group_id,
            'status' => 'approved',
            'approved_count' => 1,
            'subject' => $this->request->snapshot['subject'] ?? '',
        ];
    }
}
