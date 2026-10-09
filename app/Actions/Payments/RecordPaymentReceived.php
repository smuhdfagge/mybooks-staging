<?php

namespace App\Actions\Payments;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NotificationSetting;
use App\Models\PaymentReceived;
use App\Services\Accounting\WithholdingTax;
use App\Services\BankService;
use App\Services\Messaging\CustomerMessenger;
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
 * Withholding tax: when the customer deducted WHT (wht_amount, or worked
 * out from wht_category_id), `amount` is the money received and amount plus
 * WHT settles the invoice; the WHT is held as a credit note receivable
 * (journal: Dr bank, Dr WHT receivable, Cr receivables). Not for deposits.
 *
 * $data keys: customer_id, invoice_id, payment_date, amount,
 * payment_method, bank_id, reference, notes, is_deposit, apply_deposit_id,
 * deposit_amount, wht_category_id, wht_amount.
 */
class RecordPaymentReceived
{
    public function __construct(
        protected BankService $bank,
        protected NotificationService $notifications,
        protected WithholdingTax $wht,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(int $tenantId, array $data, ?int $userId = null): PaymentReceived
    {
        $isDeposit = (bool) ($data['is_deposit'] ?? false);
        $applyDepositId = $data['apply_deposit_id'] ?? null;
        $depositAmount = (float) ($data['deposit_amount'] ?? 0);
        $invoice = ! empty($data['invoice_id']) ? Invoice::find($data['invoice_id']) : null;

        $wht = $this->wht->forSale($tenantId, $data, Customer::where('tenant_id', $tenantId)->findOrFail($data['customer_id']), $invoice);
        if ($wht['wht_amount'] > 0 && ($isDeposit || ($applyDepositId && $depositAmount > 0))) {
            throw ValidationException::withMessages(['wht_amount' => 'WHT can only be recorded on a payment against an invoice or a general payment, not with a deposit.']);
        }

        if ($applyDepositId && $depositAmount > 0) {
            $errors = PaymentValidation::forDepositApplication(
                PaymentReceived::find($applyDepositId), $invoice, $data['customer_id'],
                $depositAmount, (float) $data['amount'] - $depositAmount
            );
        } elseif (! $isDeposit) {
            // Money received plus WHT is what settles the invoice.
            $errors = PaymentValidation::forInvoice($invoice, $data['customer_id'], (float) $data['amount'] + $wht['wht_amount']);
        }
        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
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

            $this->bank->credit($data['bank_id'] ?? null, $amount, "Payment received #{$payment->payment_number}");

            if (! $isDeposit && NotificationSetting::getForTenant($tenantId)->send_payment_confirmation) {
                $this->notifications->sendPaymentConfirmation($payment);
            }

            // Thank-you SMS / WhatsApp, where switched on (session 16); sent
            // once the transaction commits.
            if (! $isDeposit) {
                app(CustomerMessenger::class)->paymentReceived($payment, $userId);
            }

            return $payment;
        });
    }
}
