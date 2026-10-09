<?php

namespace App\Notifications;

use App\Models\SubscriptionRenewalAttempt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The saved card could not be charged for a renewal (session 15). Sent on
 * the first failure (we will try again) and when we stop trying ($final).
 */
class AutoRenewalFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SubscriptionRenewalAttempt $attempt, public bool $final) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = $this->attempt->currency.' '.number_format((float) $this->attempt->amount, 2);
        $mail = (new MailMessage)
            ->greeting("Hello {$notifiable->name},")
            ->line("We couldn't renew your MyBooks subscription with your card •••• {$this->attempt->card_last4} ({$amount}).")
            ->line('Reason: '.$this->attempt->message);

        if ($this->final) {
            return $mail
                ->subject("We couldn't renew your MyBooks subscription")
                ->line("We won't try the card again. Please pay now to keep using MyBooks without a break. Your data is safe.")
                ->action('Pay now', route('settings.subscription'));
        }

        return $mail
            ->subject('Your MyBooks renewal payment failed')
            ->line('We will try again on '.$this->attempt->next_retry_at?->format('M d, Y').'. You can also pay now with any card.')
            ->action('Pay now', route('settings.subscription'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'auto_renewal_failed',
            'subscription_id' => $this->attempt->subscription_id,
            'final' => $this->final,
            'message' => $this->final
                ? "We couldn't renew your subscription automatically. Please pay now."
                : 'Your renewal payment failed. We will try again.',
        ];
    }
}
