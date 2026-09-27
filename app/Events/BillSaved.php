<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BillSaved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public \App\Models\Bill $bill
    ) {}
}
