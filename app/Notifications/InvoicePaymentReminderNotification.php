<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoicePaymentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Invoice $invoice,
        public int $daysBefore = 3
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = $this->invoice->tenant;
        $url = route('invoices.show', $this->invoice);

        return (new MailMessage)
            ->subject("Upcoming Payment Due: Invoice #{$this->invoice->invoice_number}")
            ->greeting("Hello {$notifiable->name},")
            ->line("This is a friendly reminder that payment for the following invoice is due soon:")
            ->line("**Invoice Number:** {$this->invoice->invoice_number}")
            ->line("**Due Date:** {$this->invoice->due_date->format('M d, Y')} ({$this->daysBefore} days from now)")
            ->line("**Amount Due:** " . number_format($this->invoice->balance_due, 2) . " {$tenant->currency}")
            ->action('View Invoice', $url)
            ->line('Thank you for your business!')
            ->salutation("Best regards,\n{$tenant->name}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_reminder',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'balance_due' => $this->invoice->balance_due,
            'days_before' => $this->daysBefore,
            'message' => "Invoice #{$this->invoice->invoice_number} due in {$this->daysBefore} days",
        ];
    }
}
