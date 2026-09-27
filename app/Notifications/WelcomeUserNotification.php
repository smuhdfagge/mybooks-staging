<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeUserNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?string $temporaryPassword = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = $notifiable->tenant;
        $companyName = $tenant?->name ?? config('app.name');

        $message = (new MailMessage)
            ->subject("Welcome to {$companyName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Welcome to {$companyName}! Your account has been created successfully.")
            ->line("**Email:** {$notifiable->email}");

        if ($this->temporaryPassword) {
            $message->line("**Temporary Password:** {$this->temporaryPassword}")
                ->line('Please change your password after your first login.');
        }

        return $message
            ->action('Login Now', route('login'))
            ->line('If you have any questions, please contact your administrator.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'welcome',
            'message' => 'Welcome to the system',
        ];
    }
}
