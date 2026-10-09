<?php

namespace App\Services\EInvoicing;

use App\Models\EInvoiceSetting;
use Illuminate\Support\Facades\Log;

/**
 * A local simulator (EINVOICING_DRIVER=log, never on production): accepts
 * every document, builds the IRN itself, and writes a note to the log.
 * Nothing leaves the server. The "stamp" says SIMULATED so it can't pass
 * for NRS's.
 */
class LogDriver implements EInvoiceDriver
{
    public function name(): string
    {
        return 'log';
    }

    public function submit(array $payload, EInvoiceSetting $settings): NrsResult
    {
        $irn = (string) ($payload['irn'] ?? '');
        Log::info('E-invoice not sent to NRS (log driver)', ['irn' => $irn, 'type' => $payload['invoice_type_code'] ?? null]);

        return NrsResult::accepted($irn, 'SIMULATED-'.substr(hash('sha256', $irn), 0, 24), null, $irn, simulated: true);
    }

    public function confirm(string $irn, EInvoiceSetting $settings): NrsResult
    {
        return NrsResult::accepted($irn, 'SIMULATED-'.substr(hash('sha256', $irn), 0, 24), null, $irn, simulated: true);
    }

    public function test(EInvoiceSetting $settings): array
    {
        return ['ok' => true, 'message' => 'Simulator: nothing was sent to NRS.'];
    }
}
