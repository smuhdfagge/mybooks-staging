<?php

namespace App\Services\EInvoicing;

use App\Models\EInvoiceSetting;

/**
 * Something that can send a document to NRS (session 18). NrsDriver makes
 * the real calls; LogDriver is a local simulator. Neither ever logs a key.
 */
interface EInvoiceDriver
{
    /** Short name: nrs, log. */
    public function name(): string;

    /** Sends the document. Never throws for NRS's own answers: they come back as a result. */
    public function submit(array $payload, EInvoiceSetting $settings): NrsResult;

    /** Asks NRS where a document that was left pending now stands. */
    public function confirm(string $irn, EInvoiceSetting $settings): NrsResult;

    /** A harmless call that needs the keys. @return array{ok: bool, message: string} */
    public function test(EInvoiceSetting $settings): array;
}
