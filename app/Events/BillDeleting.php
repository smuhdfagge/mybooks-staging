<?php

namespace App\Events;

use App\Models\Bill;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BillDeleting
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Bill $bill
    ) {}
}
