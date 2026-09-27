<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentReceivedCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public \App\Models\PaymentReceived $payment
    ) {}
}
