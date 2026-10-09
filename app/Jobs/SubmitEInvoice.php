<?php

namespace App\Jobs;

use App\Actions\EInvoicing\SubmitInvoiceToNrs;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Services\EInvoicing\EInvoiceException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one document to NRS in the background (session 18), so a slow NRS
 * never holds up the screen that posted it. Like the SMS job, retries are
 * kept in the database (attempts, next_retry_at on the e-invoice row) and
 * picked up by einvoice:retry, so they behave the same on every queue
 * driver; the queue itself tries once.
 */
class SubmitEInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 90;

    /** @param 'invoice'|'credit_note' $type */
    public function __construct(public string $type, public int $documentId, public ?int $userId = null) {}

    public function handle(SubmitInvoiceToNrs $submit): void
    {
        $document = $this->type === 'invoice'
            ? Invoice::withoutGlobalScopes()->find($this->documentId)
            : CreditNote::withoutGlobalScopes()->find($this->documentId);
        if (! $document) {
            return;
        }

        try {
            $submit->handle($document, $this->userId);
        } catch (EInvoiceException $e) {
            // Not set up, a draft, or something to fix first: the reason is on the e-invoice row.
            Log::info('E-invoice not sent', ['type' => $this->type, 'id' => $this->documentId, 'reason' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
