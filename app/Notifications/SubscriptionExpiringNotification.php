<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a tenant's admins that their subscription (or free trial, $trial)
 * is about to end, or has ended ($daysLeft = 0).
 */
class SubscriptionExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
        public int $daysLeft,
        public bool $trial = false,
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

        if ($this->trial) {
            $price = '₦'.number_format((float) $this->subscription->amount).' a '.($this->subscription->billing_cycle === Subscription::CYCLE_ANNUAL ? 'year' : 'month');

            if ($this->daysLeft <= 0) {
                return $mail
                    ->subject('Your MyBooks free trial has ended')
                    ->line("Your free trial of the {$plan} plan ended on {$date}.")
                    ->line("Everything you entered is safe. Pay for the plan ({$price}) to carry on where you left off.")
                    ->action('Choose how to pay', route('settings.subscription'));
            }

            $when = $this->daysLeft === 1 ? 'tomorrow' : "in {$this->daysLeft} days";

            return $mail
                ->subject("Your MyBooks free trial ends {$when}")
                ->line("Your free trial of the {$plan} plan ends on {$date}.")
                ->line("To keep using MyBooks without a break, pay for the plan ({$price}) before then. The days left of your trial are kept.")
                ->action('Pay now', route('settings.subscription'));
        }

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
            'trial' => $this->trial,
            'message' => $this->trial
                ? ($this->daysLeft <= 0 ? 'Your free trial has ended' : "Your free trial ends in {$this->daysLeft} day(s)")
                : ($this->daysLeft <= 0
                ? 'Your subscription has ended'
                : "Your subscription ends in {$this->daysLeft} day(s)"),
        ];
    }
}
