<?php

namespace App\Services\EInvoicing;

use Carbon\CarbonInterface;

/**
 * The Invoice Reference Number: document number, the business's 8-character
 * NRS Service ID and the issue date, joined by hyphens, for example
 * INV001-7A0819F4-20251101 (format as described by NRS partners; confirm
 * against the NRS technical documents). The number keeps letters and digits
 * only, so the hyphens always separate the three parts. The same document
 * always gives the same IRN, so a retry can never create a second one.
 */
final class Irn
{
    public static function make(string $documentNumber, string $serviceId, CarbonInterface $issueDate): string
    {
        $number = preg_replace('/[^A-Za-z0-9]/', '', $documentNumber) ?? '';

        return $number.'-'.strtoupper(trim($serviceId)).'-'.$issueDate->format('Ymd');
    }
}
