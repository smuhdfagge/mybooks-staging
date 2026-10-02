<?php

namespace App\Actions\Payments;

use App\Models\Invoice;
use App\Models\NotificationSetting;
use App\Models\PaymentReceived;
use App\Models\WhtCredit;
use App\Services\BankService;
use App\Services\NotificationService;
use App\Services\PaymentValidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Recording a customer payment or deposit, the same from the web form and
 * the API (finding R3). The API never added the money to the bank's
 * balance, couldn't apply a deposit, and sent no payment confirmation.
 *
 * Checks (M5): right customer, payable invoice, not more than is owed, and
 * a deposit being applied has enough left. Then, in one transaction: the
 * payment (the Created event updates the invoice or deposit balance and
 * posts the journal), the bank balance, and the confirmation email.
 *
 * $data keys: customer_id, invoice_id, payment_date, amount,
 * payment_method, bank_id, reference, notes, is_deposit, apply_deposit_id,
 * deposit_amount, and optionally wht_rate_id, wht_rate, wht_amount (tax
 * pack 2): withholding tax the customer took off. The amount settles the
 * invoice in full; amount - WHT arrived in the bank.
 */
class RecordPaymentReceived
{
    public function __construct(
        protected BankService $bank,
        protected NotificationService $notifications,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(int $tenantId, array $data, ?int $userId = null): PaymentReceived
    {
        $isDeposit = (bool) ($data['is_deposit'] ?? false);
        $applyDepositId = $data['apply_deposit_id'] ?? null;
        $depositAmount = (float) ($data['deposit_amount'] ?? 0);
        $invoice = ! empty($data['invoice_id']) ? Invoice::find($data['invoice_id']) : null;

        if ($applyDepositId && $depositAmount > 0) {
            $errors = PaymentValidation::forDepositApplication(
                PaymentReceived::find($applyDepositId), $invoice, $data['customer_id'],
                $depositAmount, (float) $data['amount'] - $depositAmount
            );
        } elseif (! $isDeposit) {
            $errors = PaymentValidation::forInvoice($invoice, $data['customer_id'], (float) $data['amount']);
        }
        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
        $wht = WithholdingTaxOnPayment::from($data);
        if ($wht['wht_amount'] > 0 && ($isDeposit || $applyDepositId)) {
            throw ValidationException::withMessages(['wht_amount' => 'Withholding tax can only be recorded on a payment for an invoice, not a deposit.']);
        }

        return DB::transaction(function () use ($tenantId, $data, $userId, $isDeposit, $applyDepositId, $depositAmount, $invoice, $wht) {
            $amount = (float) $data['amount'];

            if ($applyDepositId && $depositAmount > 0 && $invoice) {
                $deposit = PaymentReceived::lockForUpdate()->findOrFail($applyDepositId);
                if (! $deposit->is_deposit || (float) $deposit->unused_amount + 0.005 < $depositAmount) {
                    throw ValidationException::withMessages(['deposit_amount' => 'The deposit does not have that much left.']);
                }
                $application = $deposit->applyToInvoice($invoice, $depositAmount, $data['notes'] ?? null);
                $amount = round($amount - $depositAmount, 2);

                if ($amount <= 0) {
                    return $application->appliedPayment;
                }
            }

            $payment = PaymentReceived::create([
                'tenant_id' => $tenantId,
                'customer_id' => $data['customer_id'],
                'invoice_id' => $isDeposit ? null : ($data['invoice_id'] ?? null),
                'payment_number' => PaymentReceived::generateNumber($tenantId),
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'payment_method' => $data['payment_method'],
                'bank_id' => $data['bank_id'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_deposit' => $isDeposit,
                'unused_amount' => $isDeposit ? $amount : 0,
                'created_by' => $userId,
            ] + $wht);

            // Only what arrived goes into the bank; the WHT is a tax credit
            // to follow up with the customer's certificate.
            $this->bank->credit($data['bank_id'] ?? null, $payment->cashAmount(), "Payment received #{$payment->payment_number}");
            if ($wht['wht_amount'] > 0) {
                WhtCredit::create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $payment->customer_id,
                    'payment_received_id' => $payment->id,
                    'amount' => $wht['wht_amount'],
                    'deducted_on' => $payment->payment_date,
                    'status' => WhtCredit::STATUS_AWAITING,
                ]);
            }

            if (! $isDeposit && NotificationSetting::getForTenant($tenantId)->send_payment_confirmation) {
                $this->notifications->sendPaymentConfirmation($payment);
            }

            return $payment;
        });
    }
}
