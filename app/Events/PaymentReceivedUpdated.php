<?php

namespace App\Events;

use App\Models\PaymentReceived;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentReceivedUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PaymentReceived $payment
    ) {}
}
