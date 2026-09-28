<?php

namespace App\Notifications;

use App\Models\Bill;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BillDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Bill $bill,
        public int $daysBefore = 3
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('bills.show', $this->bill);
        $vendorName = $this->bill->vendor?->name ?? 'Unknown Vendor';

        return (new MailMessage)
            ->subject("Bill Payment Due Soon: {$this->bill->bill_number}")
            ->greeting("Hello {$notifiable->name},")
            ->line('This is a reminder that the following bill is due for payment:')
            ->line("**Bill Number:** {$this->bill->bill_number}")
            ->line("**Vendor:** {$vendorName}")
            ->line("**Due Date:** {$this->bill->due_date->format('M d, Y')}")
            ->line('**Amount Due:** '.number_format($this->bill->balance_due, 2))
            ->action('View Bill', $url)
            ->line('Please ensure payment is made on time.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'bill_due',
            'bill_id' => $this->bill->id,
            'bill_number' => $this->bill->bill_number,
            'balance_due' => $this->bill->balance_due,
            'days_before' => $this->daysBefore,
            'message' => "Bill #{$this->bill->bill_number} due in {$this->daysBefore} days",
        ];
    }
}
