<?php

namespace App\Notifications;

use App\Models\PaymentReceived;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PaymentReceived $payment
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = $this->payment->invoice?->tenant ?? auth()->user()->tenant;
        $invoiceNumber = $this->payment->invoice?->invoice_number ?? 'N/A';

        return (new MailMessage)
            ->subject("Payment Confirmation - {$this->payment->payment_number}")
            ->greeting("Hello {$notifiable->name},")
            ->line('We have received your payment. Thank you!')
            ->line("**Payment Number:** {$this->payment->payment_number}")
            ->line("**Payment Date:** {$this->payment->payment_date->format('M d, Y')}")
            ->line('**Amount Received:** '.number_format($this->payment->amount, 2)." {$tenant->currency}")
            ->line('**Payment Method:** '.ucfirst(str_replace('_', ' ', $this->payment->payment_method)))
            ->line("**Invoice:** #{$invoiceNumber}")
            ->line('---')
            ->line('Thank you for your payment!')
            ->salutation("Best regards,\n{$tenant->name}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_received',
            'payment_id' => $this->payment->id,
            'payment_number' => $this->payment->payment_number,
            'amount' => $this->payment->amount,
            'message' => "Payment #{$this->payment->payment_number} received",
        ];
    }
}
