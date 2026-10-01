<?php

namespace App\Events;

use App\Models\InvoiceRefund;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceRefundDeleting
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public InvoiceRefund $refund
    ) {}
}
