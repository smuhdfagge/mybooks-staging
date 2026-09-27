<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentReceivedDeleting
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public \App\Models\PaymentReceived $payment
    ) {}
}
