<?php

namespace App\Services\EInvoicing;

use RuntimeException;

/** A problem that can be shown to the person as it is: the message is plain English. */
class EInvoiceException extends RuntimeException {}
