<?php

namespace App\Events;

use App\Models\PaymentMade;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentMadeUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PaymentMade $payment
    ) {}
}
