<?php

namespace App\Services\EInvoicing;

/** E-invoicing has no working keys or NRS address yet, so nothing is sent. */
class NotSetUp extends EInvoiceException
{
    public function __construct(string $message = 'E-invoicing is not set up yet, so nothing was sent to NRS.')
    {
        parent::__construct($message);
    }
}
