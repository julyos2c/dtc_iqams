<?php

namespace App\Notifications;

use App\Models\ClassCancellationRequest;
use Illuminate\Notifications\Notification;

class ClassCancellationNotification extends Notification
{
    public function __construct(private ClassCancellationRequest $item) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toDatabase(object $notifiable): array
    {
        return ['class_cancellation_request_id' => $this->item->id, 'status' => $this->item->status,
            'occurrence_date' => $this->item->occurrence_date->toDateString(),
            'url' => $notifiable->isAdmin() ? route('admin.class-cancellations.index') : route('instructor.schedule')];
    }
}
