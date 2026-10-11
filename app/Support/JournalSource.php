<?php

namespace App\Support;

use App\Models\Journal;
use Illuminate\Support\Facades\Route;

/**
 * The document a journal was posted for, as a label and a link (tables
 * plan T7). Falls back to the journal itself when it has no document or
 * the document's page doesn't exist.
 */
final class JournalSource
{
    /** reference_type => [what it is called, show route, number field] */
    private const KINDS = [
        'App\Models\Invoice' => ['Invoice', 'invoices.show', 'invoice_number'],
        'App\Models\PaymentReceived' => ['Payment received', 'payments-received.show', 'payment_number'],
        'App\Models\SalesReceipt' => ['Cash sale', 'sales-receipts.show', 'receipt_number'],
        'App\Models\CreditNote' => ['Credit note', 'credit-notes.show', 'credit_note_number'],
        'App\Models\InvoiceRefund' => ['Refund', 'invoices.refunds.show', 'refund_number'],
        'App\Models\Bill' => ['Bill', 'bills.show', 'bill_number'],
        'App\Models\PaymentMade' => ['Payment made', 'payments-made.show', 'payment_number'],
        'App\Models\Expense' => ['Expense', 'expenses.show', 'expense_number'],
        'App\Models\VendorCredit' => ['Supplier credit', 'vendor-credits.show', 'vendor_credit_number'],
        'App\Models\PayrollBatch' => ['Payroll', 'payroll-batches.show', 'batch_number'],
        'App\Models\FixedAsset' => ['Fixed asset', 'fixed-assets.show', 'asset_number'],
        'App\Models\StockTransfer' => ['Stock transfer', 'stock-transfers.show', 'transfer_number'],
        'App\Models\AssemblyOrder' => ['Assembly', 'assembly-orders.show', 'order_number'],
    ];

    /** @return array{label: string, url: string} */
    public static function of(Journal $journal): array
    {
        [$name, $route, $field] = self::KINDS[$journal->reference_type] ?? [null, null, null];
        if ($name && $journal->reference_id && Route::has($route)) {
            // The document may since have been deleted, so the relation can be empty.
            $document = $journal->relationLoaded('reference') ? $journal->getRelation('reference') : null;
            $number = $document ? data_get($document, $field) : null;

            return ['label' => trim($name.' '.($number ?? '')), 'url' => route($route, $journal->reference_id)];
        }

        return ['label' => 'Journal '.$journal->journal_number, 'url' => route('journals.show', $journal)];
    }
}
