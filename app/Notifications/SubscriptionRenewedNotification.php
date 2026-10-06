<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Models\SubscriptionRenewalAttempt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Receipt for an automatic renewal charged to the saved card (session 15).
 */
class SubscriptionRenewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SubscriptionRenewalAttempt $attempt) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subscription = Subscription::withoutGlobalScopes()->with('plan')->find($this->attempt->subscription_id);
        $amount = $this->attempt->currency.' '.number_format((float) $this->attempt->amount, 2);

        return (new MailMessage)
            ->subject('Receipt: your MyBooks subscription has been renewed')
            ->greeting("Hello {$notifiable->name},")
            ->line('We renewed your '.($subscription?->plan->name ?? 'MyBooks').' subscription with your saved card.')
            ->line("Amount paid: {$amount}")
            ->line('Card: •••• '.$this->attempt->card_last4)
            ->line('Date: '.$this->attempt->updated_at?->format('M d, Y'))
            ->line('Reference: '.$this->attempt->reference)
            ->line('Your subscription now runs until '.($subscription?->ends_at?->format('M d, Y') ?? '—').'.')
            ->action('View billing', route('settings.subscription'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription_renewed',
            'subscription_id' => $this->attempt->subscription_id,
            'reference' => $this->attempt->reference,
            'message' => 'Your subscription was renewed automatically',
        ];
    }
}
