<?php

namespace App\Services\Accounting;

use App\Models\PaymentReceived;
use App\Models\WhtCreditUtilisation;
use App\Services\JournalService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * WHT our customers deducted (WHT credit notes receivable).
 *
 * Each payment with WHT moves through: outstanding (deducted, no credit
 * note yet) -> received (the credit note's number and date are recorded)
 * -> utilised (used against the business's income tax). Using credits
 * only posts Dr Income Tax Payable / Cr WHT receivable; whole credit notes
 * are used, and the tax computation itself is left to the accountant.
 */
class WhtCredits
{
    public function __construct(protected JournalService $journals) {}

    public function recordCreditNote(PaymentReceived $payment, string $number, string $date): PaymentReceived
    {
        if ((float) $payment->wht_amount <= 0) {
            throw ValidationException::withMessages(['wht_credit_note_number' => 'This payment has no WHT deducted.']);
        }
        if ($payment->wht_utilisation_id) {
            throw ValidationException::withMessages(['wht_credit_note_number' => 'This WHT credit has already been used against income tax.']);
        }

        // Only the credit note fields change; the payment's period check does not apply.
        PaymentReceived::withoutEvents(fn () => $payment->withoutPeriodValidation()->forceFill([
            'wht_credit_note_number' => $number,
            'wht_credit_note_date' => $date,
        ])->save());

        return $payment;
    }

    /**
     * Use received credit notes against income tax.
     *
     * @param  array<int, int|string>  $paymentIds
     */
    public function utilise(int $tenantId, array $paymentIds, string $date, ?string $reference, ?string $notes, ?int $userId): WhtCreditUtilisation
    {
        return DB::transaction(function () use ($tenantId, $paymentIds, $date, $reference, $notes, $userId) {
            /** @var Collection<int, PaymentReceived> $payments */
            $payments = PaymentReceived::where('tenant_id', $tenantId)
                ->whereIn('id', $paymentIds)
                ->lockForUpdate()
                ->get();

            if ($payments->isEmpty() || $payments->count() !== count(array_unique($paymentIds))) {
                throw ValidationException::withMessages(['payment_ids' => 'Choose WHT credit notes of this business.']);
            }
            foreach ($payments as $payment) {
                if ($payment->whtStatus() !== PaymentReceived::WHT_RECEIVED) {
                    throw ValidationException::withMessages(['payment_ids' => "Payment {$payment->payment_number} has no WHT credit note in hand to use."]);
                }
            }

            $utilisation = WhtCreditUtilisation::create([
                'tenant_id' => $tenantId,
                'utilisation_date' => $date,
                'amount' => round((float) $payments->sum('wht_amount'), 2),
                'reference' => $reference,
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            $this->journals->createWhtUtilisationJournal($utilisation);

            PaymentReceived::whereIn('id', $payments->pluck('id'))->update(['wht_utilisation_id' => $utilisation->id]);

            return $utilisation;
        });
    }
}
