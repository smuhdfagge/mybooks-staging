<?php

namespace App\Notifications;

use App\Models\BillingCard;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The saved card runs out before the next automatic renewal (session 15).
 */
class BillingCardExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public BillingCard $card, public CarbonInterface $renewalDate) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your saved card for MyBooks is about to expire')
            ->greeting("Hello {$notifiable->name},")
            ->line("The card we renew your MyBooks subscription with ({$this->card->label()}) expires at the end of {$this->card->expiryLabel()}.")
            ->line('Your next renewal is due on '.$this->renewalDate->format('M d, Y').', after the card expires.')
            ->line('To keep renewing automatically, pay once with your new card from the billing page; it will replace the old one.')
            ->action('Open billing', route('settings.subscription'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'billing_card_expiring',
            'message' => "Your saved card {$this->card->label()} expires {$this->card->expiryLabel()}",
        ];
    }
}
