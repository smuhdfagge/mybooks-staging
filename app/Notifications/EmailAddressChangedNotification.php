<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the old address when an account's email address changes, so the
 * owner hears about it even if someone else made the change (finding S6).
 */
class EmailAddressChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $userName,
        public string $oldEmail,
        public string $newEmail,
        public bool $changedByAdmin = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = config('app.name');

        return (new MailMessage)
            ->subject("Your {$app} email address was changed")
            ->greeting("Hello {$this->userName},")
            ->line("The email address on your {$app} account was changed from {$this->oldEmail} to {$this->newEmail}.")
            ->line($this->changedByAdmin
                ? 'The change was made by an administrator of your business.'
                : 'The change was made from your profile page.')
            ->line('If you did not expect this, contact your administrator or our support team straight away.');
    }
}
