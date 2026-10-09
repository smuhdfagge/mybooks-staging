<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app notice: B2C invoices above the NRS threshold are close to (or past)
 * the 24-hour limit for reporting and have not been accepted yet (session 18).
 */
class EInvoiceNotReportedNotification extends Notification
{
    use Queueable;

    public function __construct(public int $count, public int $hours) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $what = $this->count === 1 ? '1 invoice to a customer without a TIN has' : "{$this->count} invoices to customers without a TIN have";

        return [
            'type' => 'e_invoice_not_reported',
            'message' => "{$what} not reached NRS yet, and NRS wants these reported within {$this->hours} hours. Open E-invoices and send them.",
            'url' => route('e-invoices.index', ['status' => 'not_submitted']),
        ];
    }
}
