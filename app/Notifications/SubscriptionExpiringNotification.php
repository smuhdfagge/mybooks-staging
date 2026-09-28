<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a tenant's admins that their subscription is about to end, or has
 * ended ($daysLeft = 0).
 */
class SubscriptionExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
        public int $daysLeft
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plan = $this->subscription->plan?->name ?? 'MyBooks';
        $date = $this->subscription->ends_at?->format('M d, Y');

        $mail = (new MailMessage)->greeting("Hello {$notifiable->name},");

        if ($this->daysLeft <= 0) {
            return $mail
                ->subject('Your MyBooks subscription has ended')
                ->line("Your {$plan} subscription ended on {$date}.")
                ->line('Your data is safe. Renew to get back into your account.')
                ->action('Renew now', route('settings.subscription'));
        }

        $when = $this->daysLeft === 1 ? 'tomorrow' : "in {$this->daysLeft} days";

        return $mail
            ->subject("Your MyBooks subscription ends {$when}")
            ->line("Your {$plan} subscription ends on {$date}.")
            ->line('Renew before then to keep using MyBooks without a break.')
            ->action('Renew now', route('settings.subscription'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription_expiring',
            'subscription_id' => $this->subscription->id,
            'days_left' => $this->daysLeft,
            'message' => $this->daysLeft <= 0
                ? 'Your subscription has ended'
                : "Your subscription ends in {$this->daysLeft} day(s)",
        ];
    }
}
