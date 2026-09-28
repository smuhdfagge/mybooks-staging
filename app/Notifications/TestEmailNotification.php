<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TestEmailNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $tenantName
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Test Email from {$this->tenantName}")
            ->greeting('Hello!')
            ->line('This is a test email from your accounting software.')
            ->line('If you received this email, your email configuration is working correctly.')
            ->line('---')
            ->line("**Sent from:** {$this->tenantName}")
            ->line('**Sent at:** '.now()->format('F j, Y g:i A'))
            ->line('---')
            ->line('You can now configure and send automated notifications to your customers and team members.')
            ->salutation("Best regards,\n{$this->tenantName}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'test_email',
            'message' => 'Test email sent successfully',
        ];
    }
}
