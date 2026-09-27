<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceDeleting
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public \App\Models\Invoice $invoice
    ) {}
}
