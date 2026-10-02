<?php

namespace App\Actions\CreditNotes;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
use App\Models\CreditNoteRefund;
use App\Services\BankService;
use App\Services\JournalService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Paying a customer back out of an open credit note:
 *   Dr Accounts receivable, Cr Cash/Bank.
 * The credit note's balance goes down; at nothing left it is closed.
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
        return DB::transaction(function () use ($note, $data, $userId) {
            $note = CreditNote::lockForUpdate()->findOrFail($note->id);
            $amount = Money::round($data['amount']);

            if ($note->status !== CreditNoteStatus::Open->value) {
                throw ValidationException::withMessages(['amount' => 'Only an open credit note can be refunded.']);
            }
            if ($amount <= 0 || $amount - (float) $note->balance > 0.005) {
                throw ValidationException::withMessages(['amount' => 'The refund can be at most the credit left ('.number_format((float) $note->balance, 2).').']);
            }

            $refund = CreditNoteRefund::create([
                'tenant_id' => $note->tenant_id,
                'credit_note_id' => $note->id,
                'refund_date' => $data['refund_date'],
                'amount' => $amount,
                'payment_method' => $data['payment_method'],
                'bank_id' => $data['bank_id'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $this->journals->createCreditNoteRefundJournal($refund);
            $this->banks->debit($refund->bank_id, $amount, "Refund on credit note {$note->credit_note_number}");

            $note->balance = Money::subtract($note->balance, $amount);
            if ($note->balance <= 0) {
                $note->status = CreditNoteStatus::Closed->value;
            }
            $note->save();

            return $refund;
        });
    }
}
