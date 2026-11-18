<?php
namespace App\Notifications;

use Illuminate\Notifications\Notification;

class StudentExcuseNotification extends Notification
{
    public function __construct(public string $event, public string $url) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toDatabase(object $notifiable): array { return ['event' => $this->event, 'url' => $this->url]; }
}
