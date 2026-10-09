<?php

namespace App\Actions\EInvoicing;

use App\Enums\EInvoiceStatus;
use App\Models\CreditNote;
use App\Models\EInvoiceSetting;
use App\Models\EInvoiceSubmission;
use App\Models\Invoice;
use App\Services\EInvoicing\EInvoiceDrivers;
use App\Services\EInvoicing\EInvoiceException;
use App\Services\EInvoicing\NotSetUp;
use App\Services\EInvoicing\NrsResult;
use App\Services\EInvoicing\PayloadBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sends an invoice or a credit note to NRS and records the answer (session
 * 18). It only ever writes the e_invoice_submissions row: the invoice, its
 * journal and every other book record are left alone, and a posted
 * document is never held back because NRS is slow or down.
 *
 * Safe to call twice: a document NRS has accepted is never sent again, and a
 * send that is still in flight is not started a second time. Where NRS's
 * answer is "try later" the row is marked failed with a next_retry_at, and
 * einvoice:retry (hourly) or the Retry button sends it again.
 *
 * Throws EInvoiceException (plain message) when it cannot even start: not
 * set up, draft document, missing TIN. NRS's own answers never throw.
 */
class SubmitInvoiceToNrs
{
    public function __construct(
        private PayloadBuilder $payloads,
        private EInvoiceDrivers $drivers,
    ) {}

    public function handle(Invoice|CreditNote $document, ?int $userId = null): EInvoiceSubmission
    {
        $settings = EInvoiceSetting::forTenant($document->tenant_id);
        if (! config('mybooks.features.e_invoicing') || ! $settings->enabled) {
            throw new NotSetUp('E-invoicing is switched off for this business.');
        }
        $driver = $this->drivers->for($settings);
        $this->assertPostedDocument($document);

        // Already accepted or being sent: nothing to do.
        $existing = EInvoiceSubmission::forDocument($document);
        if ($existing && ($existing->isAccepted() || $this->inFlight($existing))) {
            return $existing;
        }

        try {
            $payload = $this->payloads->build($document, $settings);
        } catch (EInvoiceException $e) {
            // Remember why, so the panel can say it; the status stays as it was.
            $this->claim($document, $settings, $userId, claim: false)?->forceFill(['last_error' => $e->getMessage()])->save();
            throw $e;
        }

        $submission = $this->claim($document, $settings, $userId);
        if (! $submission) {
            return EInvoiceSubmission::forDocument($document); // someone else got there first
        }
        $submission->forceFill(['irn' => $payload['irn'], 'kind' => $this->payloads->kind($document)])->save();

        try {
            $result = $driver->submit($payload, $settings);
        } catch (NotSetUp $e) {
            $this->release($submission, $e->getMessage());
            throw $e;
        } catch (Throwable $e) {
            report($e);
            $result = NrsResult::failed('Something went wrong while sending to NRS. It will be tried again.');
        }

        return $this->record($submission, $result, $driver->name() === 'log' ? 'simulated' : $settings->environment);
    }

    /** Asks NRS about a document it was left holding ("pending") and records the answer. */
    public function check(EInvoiceSubmission $submission): EInvoiceSubmission
    {
        $settings = EInvoiceSetting::forTenant($submission->tenant_id);
        if ($submission->status !== EInvoiceStatus::Pending->value || ! filled($submission->irn)) {
            return $submission;
        }
        $driver = $this->drivers->for($settings);
        $result = $driver->confirm((string) $submission->irn, $settings);

        return $this->record($submission, $result, $driver->name() === 'log' ? 'simulated' : $settings->environment);
    }

    private function assertPostedDocument(Invoice|CreditNote $document): void
    {
        if ($document instanceof Invoice) {
            if (in_array($document->status, ['draft', 'cancelled'], true) || (float) $document->total <= 0) {
                throw new EInvoiceException('Only an invoice that has been sent or issued can go to NRS. A draft or cancelled invoice cannot.');
            }

            return;
        }
        if (! in_array($document->status, ['open', 'closed'], true)) {
            throw new EInvoiceException('Post the credit note first. A draft or voided credit note cannot go to NRS.');
        }
    }

    private function inFlight(EInvoiceSubmission $row): bool
    {
        return $row->status === EInvoiceStatus::Pending->value
            && $row->last_attempt_at
            && $row->last_attempt_at->gt(now()->subMinutes((int) config('mybooks.einvoicing.pending_stale_minutes', 15)));
    }

    /**
     * Takes the document for sending: makes its row (one per document) and
     * moves it to pending, counting the attempt. Null when another request
     * got there first. With $claim false it only makes sure a row exists.
     */
    private function claim(Invoice|CreditNote $document, EInvoiceSetting $settings, ?int $userId, bool $claim = true): ?EInvoiceSubmission
    {
        $column = $document instanceof Invoice ? 'invoice_id' : 'credit_note_id';

        return DB::transaction(function () use ($document, $settings, $userId, $column, $claim) {
            $row = EInvoiceSubmission::query()->withoutGlobalScopes()->where($column, $document->getKey())->lockForUpdate()->first();
            if (! $row) {
                try {
                    $row = new EInvoiceSubmission([$column => $document->getKey(), 'status' => EInvoiceStatus::NotSubmitted->value, 'kind' => $this->payloads->kind($document)]);
                    $row->skipTenantGuard = true;
                    $row->tenant_id = $document->tenant_id;
                    $row->save();
                } catch (QueryException) {
                    $row = EInvoiceSubmission::query()->withoutGlobalScopes()->where($column, $document->getKey())->lockForUpdate()->first();
                }
            }
            if (! $claim) {
                return $row;
            }
            if ($row->isAccepted() || $this->inFlight($row)) {
                return null;
            }

            $row->forceFill([
                'status' => EInvoiceStatus::Pending->value,
                'attempts' => $row->attempts + 1,
                'last_attempt_at' => now(),
                'next_retry_at' => null,
                'environment' => $settings->environment,
                'submitted_by' => $userId ?? $row->submitted_by,
                'submitted_at' => $row->submitted_at ?? now(),
            ])->save();

            return $row;
        });
    }

    /** Could not start after all (not set up): put the row back as it was. */
    private function release(EInvoiceSubmission $row, string $message): void
    {
        $row->forceFill([
            'status' => EInvoiceStatus::Failed->value,
            'attempts' => max(0, $row->attempts - 1),
            'last_error' => $message,
        ])->save();
    }

    private function record(EInvoiceSubmission $row, NrsResult $result, ?string $environment): EInvoiceSubmission
    {
        $row = EInvoiceSubmission::query()->withoutGlobalScopes()->findOrFail($row->id);
        $fill = ['environment' => $environment];

        switch ($result->outcome) {
            case 'accepted':
                $fill += [
                    'status' => EInvoiceStatus::Accepted->value,
                    'irn' => $result->irn ?: $row->irn,
                    'csid' => $result->csid,
                    'qr_image' => $result->qrImage,
                    'qr_payload' => $result->qrPayload,
                    'accepted_at' => now(),
                    'last_error' => null,
                    'next_retry_at' => null,
                ];
                break;
            case 'pending':
                $fill += [
                    'status' => EInvoiceStatus::Pending->value,
                    'irn' => $result->irn ?: $row->irn,
                    'last_error' => null,
                    'last_attempt_at' => now(),
                    'next_retry_at' => now()->addMinutes(5), // asked about again by einvoice:retry
                ];
                break;
            case 'rejected':
                $fill += ['status' => EInvoiceStatus::Rejected->value, 'last_error' => $result->message, 'next_retry_at' => null];
                break;
            default:
                $fill += ['status' => EInvoiceStatus::Failed->value, 'last_error' => $result->message, 'next_retry_at' => $this->nextRetry($row->attempts)];
        }

        $row->forceFill($fill)->save();

        return $row;
    }

    /** When to try again, or null once the limit is used up. */
    private function nextRetry(int $attempts): ?Carbon
    {
        if ($attempts >= (int) config('mybooks.einvoicing.max_attempts', 5)) {
            return null;
        }
        $minutes = (array) config('mybooks.einvoicing.retry_minutes', [10, 30, 60]);

        return now()->addMinutes((int) ($minutes[$attempts - 1] ?? end($minutes) ?: 60));
    }
}
