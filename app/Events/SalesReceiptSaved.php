<?php

namespace App\Events;

use App\Models\SalesReceipt;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SalesReceiptSaved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SalesReceipt $salesReceipt
    ) {}
}
