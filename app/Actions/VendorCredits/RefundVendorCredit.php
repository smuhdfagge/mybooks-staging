<?php

namespace App\Actions\VendorCredits;

use App\Enums\VendorCreditStatus;
use App\Models\VendorCredit;
use App\Models\VendorCreditRefund;
use App\Services\BankService;
use App\Services\JournalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The supplier pays back (part of) an open credit: Dr bank or cash,
 * Cr accounts payable, and the money goes into the bank's balance.
 *
 * $data keys: refund_date, amount, payment_method, bank_id, reference, notes.
 */
class RefundVendorCredit
{
    public function __construct(
        protected JournalService $journals,
        protected BankService $bank,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(VendorCredit $credit, array $data, ?int $userId = null): VendorCreditRefund
    {
        $amount = round((float) $data['amount'], 2);

        return DB::transaction(function () use ($credit, $data, $userId, $amount) {
            $credit = VendorCredit::lockForUpdate()->findOrFail($credit->id);
            if ($credit->status !== VendorCreditStatus::Open->value) {
                throw ValidationException::withMessages(['amount' => 'Only an open supplier credit can be refunded.']);
            }
            if ($amount <= 0 || $amount - (float) $credit->balance > 0.005) {
                throw ValidationException::withMessages(['amount' => 'Only '.number_format((float) $credit->balance, 2).' of this credit is left.']);
            }

            $refund = VendorCreditRefund::create([
                'tenant_id' => $credit->tenant_id,
                'vendor_credit_id' => $credit->id,
                'refund_date' => $data['refund_date'],
                'amount' => $amount,
                'payment_method' => $data['payment_method'] ?? 'bank_transfer',
                'bank_id' => $data['bank_id'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $this->journals->createVendorCreditRefundJournal($refund);
            $this->bank->credit($refund->bank_id, $amount, "Refund of supplier credit {$credit->vendor_credit_number}");
            ReduceVendorCreditBalance::by($credit, $amount);

            return $refund;
        });
    }
}
