<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refunds used to take the refund off the invoice's amount paid, so a paid
 * invoice refunded in full showed as owed again in ageing, while the ledger
 * showed nothing owed (finding A6). Recalculate every refunded invoice from
 * its payments and applied credits, as Invoice::updateBalances() now does.
 */
return new class extends Migration
{
    public function up(): void
    {
        $hasCredits = Schema::hasTable('credit_note_applications');

        DB::table('invoices')
            ->where('total_refunded', '>', 0)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->each(function ($invoice) use ($hasCredits) {
                $paid = (float) DB::table('payments_received')
                    ->where('invoice_id', $invoice->id)
                    ->whereNull('deleted_at')
                    ->sum('amount');
                if ($hasCredits) {
                    $paid += (float) DB::table('credit_note_applications')->where('invoice_id', $invoice->id)->sum('amount');
                }
                $paid = round($paid, 2);
                $due = round((float) $invoice->total - $paid, 2);

                $status = $invoice->status;
                if (! in_array($status, ['draft', 'cancelled'], true)) {
                    $status = $due <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
                }

                DB::table('invoices')->where('id', $invoice->id)->update([
                    'amount_paid' => $paid,
                    'balance_due' => $due,
                    'status' => $status,
                ]);
            });
    }

    public function down(): void
    {
        // Nothing to undo: the old figures were wrong.
    }
};
