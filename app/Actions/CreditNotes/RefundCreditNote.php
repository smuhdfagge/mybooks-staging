<?php

namespace App\Actions\CreditNotes;

use App\Models\CreditNote;
use App\Models\CreditNoteRefund;
use App\Services\BankService;
use App\Services\JournalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Paying a customer back (part of) an open credit note:
 *   Dr Accounts receivable, Cr Cash / Bank
 * and the money comes out of the bank's balance. The credit note's balance
 * goes down; at nothing left it is closed.
 *
 * $data keys: refund_date, amount, payment_method, bank_id, reference, notes.
 */
class RefundCreditNote
{
    public function __construct(
        private JournalService $journals,
        private BankService $banks,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(CreditNote $note, array $data, ?int $userId = null): CreditNoteRefund
    {
        $amount = round((float) $data['amount'], 2);

        return DB::transaction(function () use ($note, $data, $userId, $amount) {
            $note = CreditNote::lockForUpdate()->findOrFail($note->id);

            if (! $note->isOpen()) {
                throw ValidationException::withMessages(['amount' => 'Only an open credit note can be refunded.']);
            }
            if ($amount <= 0 || $amount - (float) $note->balance > 0.005) {
                throw ValidationException::withMessages(['amount' => 'Only '.number_format((float) $note->balance, 2).' of this credit is left to refund.']);
            }

            $refund = CreditNoteRefund::create([
                'tenant_id' => $note->tenant_id,
                'credit_note_id' => $note->id,
                'refund_date' => $data['refund_date'],
                'amount' => $amount,
                'payment_method' => $data['payment_method'] ?? 'bank_transfer',
                'bank_id' => $data['bank_id'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $this->journals->createCreditNoteRefundJournal($refund);
            $this->banks->debit($refund->bank_id, $amount, "Refund on credit note {$note->credit_note_number}");
            $note->reduceBalance($amount);

            return $refund;
        });
    }
}
