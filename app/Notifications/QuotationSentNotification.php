<?php

namespace App\Notifications;

use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails a quotation to the customer with the quotation attached as a PDF.
 */
class QuotationSentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Quotation $quotation,
        public ?string $customMessage = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $quotation = $this->quotation->loadMissing(['customer', 'items.item', 'tenant']);
        $tenant = $quotation->tenant;

        $message = (new MailMessage)
            ->subject("Quotation {$quotation->quotation_number} from {$tenant->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Please find attached our quotation {$quotation->quotation_number}.")
            ->line('**Total:** '.number_format((float) $quotation->total, 2)." {$tenant->currency}");

        if ($quotation->expiry_date) {
            $message->line("**Valid until:** {$quotation->expiry_date->format('M d, Y')}");
        }
        if ($this->customMessage) {
            $message->line('---')->line($this->customMessage);
        }

        return $message
            ->line('To go ahead, simply reply to this email.')
            ->attachData($this->pdf(), "quotation-{$quotation->quotation_number}.pdf", ['mime' => 'application/pdf']);
    }

    public function pdf(): string
    {
        $quotation = $this->quotation->loadMissing(['customer', 'items.item', 'tenant']);

        return Pdf::loadView('quotations.print', [
            'quotation' => $quotation,
            'tenant' => $quotation->tenant,
            'forPdf' => true,
        ])->output();
    }
}
