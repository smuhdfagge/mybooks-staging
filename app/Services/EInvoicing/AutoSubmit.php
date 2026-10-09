<?php

namespace App\Services\EInvoicing;

use App\Jobs\SubmitEInvoice;
use App\Models\CreditNote;
use App\Models\EInvoiceSetting;
use App\Models\EInvoiceSubmission;
use App\Models\Invoice;
use Throwable;

/**
 * "Submit automatically" (session 18): when a business has chosen it, an
 * invoice that is sent or issued, or a credit note that is posted, is queued
 * for NRS. Always after posting and always safe: any problem here is
 * swallowed, so posting is never blocked by e-invoicing. A document that
 * already has an e-invoice row is left to the Retry button / einvoice:retry.
 * B2C documents under the NRS threshold are not required, so not sent.
 */
class AutoSubmit
{
    public function __construct(private PayloadBuilder $payloads, private EInvoiceDrivers $drivers) {}

    public function invoice(Invoice $invoice): void
    {
        $this->queue('invoice', $invoice);
    }

    public function creditNote(CreditNote $note): void
    {
        $this->queue('credit_note', $note);
    }

    private function queue(string $type, Invoice|CreditNote $document): void
    {
        if (! config('mybooks.features.e_invoicing')) {
            return;
        }
        try {
            $settings = EInvoiceSetting::query()->withoutGlobalScopes()->where('tenant_id', $document->tenant_id)->first();
            if (! $settings || ! $settings->enabled || ! $settings->isAuto() || ! $this->drivers->isLive($settings)) {
                return;
            }
            if (EInvoiceSubmission::forDocument($document) || (float) $document->total <= 0) {
                return;
            }
            $document->loadMissing('customer');
            if (! $this->payloads->mustReport($document)) {
                return;
            }

            SubmitEInvoice::dispatch($type, $document->getKey(), auth()->id())->afterCommit();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
