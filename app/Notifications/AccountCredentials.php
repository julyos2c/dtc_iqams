<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountCredentials extends Notification
{
    use Queueable;

    public function __construct(public string $username, public string $temporaryPassword) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your IQAMS account')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your account has been created. Use these credentials to sign in:')
            ->line('User ID: '.$this->username)
            ->line('Temporary password: '.$this->temporaryPassword)
            ->action('Sign in', route('login'))
            ->line('You will be required to change your password when you sign in.');
    }
}
