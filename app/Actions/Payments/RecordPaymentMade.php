<?php

namespace App\Actions\Payments;

use App\Models\Bill;
use App\Models\PaymentMade;
use App\Services\BankService;
use App\Services\PaymentValidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Recording a payment to a vendor, the same from the web form and the API
 * (finding R3). The API never took the money off the bank's balance.
 * The Created event updates the bill and posts the journal.
 *
 * $data keys: vendor_id, bill_id, payment_date, amount, payment_method,
 * bank_id, reference, notes.
 */
class RecordPaymentMade
{
    public function __construct(protected BankService $bank) {}

    /** @param array<string, mixed> $data */
    public function handle(int $tenantId, array $data, ?int $userId = null): PaymentMade
    {
        // Right vendor, payable bill, not more than is owed (M5).
        $bill = ! empty($data['bill_id']) ? Bill::find($data['bill_id']) : null;
        if ($errors = PaymentValidation::forBill($bill, $data['vendor_id'], (float) $data['amount'])) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($tenantId, $data, $userId) {
            $payment = PaymentMade::create([
                'tenant_id' => $tenantId,
                'vendor_id' => $data['vendor_id'],
                'bill_id' => $data['bill_id'] ?? null,
                'payment_number' => PaymentMade::generateNumber($tenantId),
                'payment_date' => $data['payment_date'],
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'bank_id' => $data['bank_id'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $this->bank->debit($data['bank_id'] ?? null, (float) $data['amount'], "Payment made #{$payment->payment_number}");

            return $payment;
        });
    }
}
