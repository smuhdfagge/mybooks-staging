<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceSentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Invoice $invoice,
        public ?string $customMessage = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = $this->invoice->tenant;
        $url = route('invoices.show', $this->invoice);

        $message = (new MailMessage)
            ->subject("Invoice #{$this->invoice->invoice_number} from {$tenant->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("You have received a new invoice from {$tenant->name}.")
            ->line("**Invoice Number:** {$this->invoice->invoice_number}")
            ->line("**Invoice Date:** {$this->invoice->invoice_date->format('M d, Y')}")
            ->line("**Due Date:** {$this->invoice->due_date->format('M d, Y')}")
            ->line("**Amount Due:** " . number_format($this->invoice->balance_due, 2) . " {$tenant->currency}");

        if ($this->customMessage) {
            $message->line('---')
                ->line($this->customMessage);
        }

        return $message
            ->action('View Invoice', $url)
            ->line('Thank you for your business!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'invoice_sent',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'amount' => $this->invoice->total,
            'message' => "Invoice #{$this->invoice->invoice_number} sent",
        ];
    }
}
