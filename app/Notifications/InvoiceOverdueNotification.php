<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Invoice $invoice,
        public int $daysOverdue = 0
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = $this->invoice->tenant;
        $url = route('invoices.show', $this->invoice);

        $subject = $this->daysOverdue > 0
            ? "Payment Reminder: Invoice #{$this->invoice->invoice_number} is {$this->daysOverdue} days overdue"
            : "Payment Reminder: Invoice #{$this->invoice->invoice_number}";

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name},")
            ->line("This is a friendly reminder that the following invoice is past due:")
            ->line("**Invoice Number:** {$this->invoice->invoice_number}")
            ->line("**Invoice Date:** {$this->invoice->invoice_date->format('M d, Y')}")
            ->line("**Due Date:** {$this->invoice->due_date->format('M d, Y')}")
            ->line("**Days Overdue:** {$this->daysOverdue}")
            ->line("**Outstanding Balance:** " . number_format($this->invoice->balance_due, 2) . " {$tenant->currency}")
            ->line('---')
            ->line('Please arrange payment at your earliest convenience. If you have already made payment, please disregard this notice.')
            ->action('View Invoice', $url)
            ->line('If you have any questions, please contact us.')
            ->salutation("Best regards,\n{$tenant->name}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'invoice_overdue',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'balance_due' => $this->invoice->balance_due,
            'days_overdue' => $this->daysOverdue,
            'message' => "Invoice #{$this->invoice->invoice_number} is overdue",
        ];
    }
}
