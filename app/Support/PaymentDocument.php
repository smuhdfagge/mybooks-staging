<?php

namespace App\Support;

use App\Models\PaymentMade;
use App\Models\PaymentReceived;
use App\Models\Tenant;

/**
 * Money moving, as a document. Two sides of the same sheet:
 *
 *   received  a receipt for the customer — "can I have a receipt?" is the
 *             question this answers.
 *   made      a payment advice for the supplier, which in Nigeria is what
 *             lets them claim back the WHT you withheld.
 *
 * Both carry the same facts in the same places, so they share one view
 * and differ only in their wording.
 */
class PaymentDocument
{
    /**
     * @param  array<string, mixed>|null  $settings
     * @return array<string, mixed>
     */
    public static function received(PaymentReceived $payment, ?Tenant $tenant = null, bool $forPdf = false, ?array $settings = null): array
    {
        $tenant ??= $payment->tenant ?? Tenant::findOrFail($payment->tenant_id);

        return self::shape($payment, $tenant, $forPdf, $settings, [
            'kind' => 'received',
            'title' => 'Receipt',
            'partyLabel' => 'Received from',
            'party' => $payment->customer,
            'againstLabel' => 'For invoice',
            'against' => $payment->invoice?->invoice_number,
            'onAccount' => (bool) $payment->is_deposit,
            'onAccountLabel' => 'Deposit on account',
            // The customer kept this back and owes it to the tax authority.
            'whtLabel' => 'Less WHT deducted by you',
            'whtNote' => 'Please send us the withholding tax credit note for this deduction.',
            'amountLabel' => 'Amount received',
            'settledLabel' => 'Settled on the invoice',
            'signs' => [['role' => 'For '.$tenant->name, 'who' => 'Name, signature and date']],
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array<string, mixed>
     */
    public static function made(PaymentMade $payment, ?Tenant $tenant = null, bool $forPdf = false, ?array $settings = null): array
    {
        $tenant ??= $payment->tenant ?? Tenant::findOrFail($payment->tenant_id);

        return self::shape($payment, $tenant, $forPdf, $settings, [
            'kind' => 'made',
            'title' => 'Payment Advice',
            'partyLabel' => 'Paid to',
            'party' => $payment->vendor,
            'againstLabel' => 'For bill',
            'against' => $payment->bill?->bill_number,
            'onAccount' => (bool) $payment->is_advance,
            'onAccountLabel' => 'Advance on account',
            // We kept this back and will remit it; the supplier claims it back.
            'whtLabel' => 'Less WHT withheld',
            'whtNote' => 'The withholding tax credit note for this deduction will follow.',
            'amountLabel' => 'Amount paid',
            'settledLabel' => 'Settled on the bill',
            'signs' => [
                ['role' => 'Prepared by', 'who' => $payment->createdBy?->name ?: 'Name, signature and date'],
                ['role' => 'Approved by', 'who' => 'Name, signature and date'],
            ],
        ]);
    }

    /**
     * The facts both sides share, in the same places.
     *
     * @param  array<string, mixed>|null  $settings
     * @param  array<string, mixed>  $side
     * @return array<string, mixed>
     */
    private static function shape(PaymentReceived|PaymentMade $payment, Tenant $tenant, bool $forPdf, ?array $settings, array $side): array
    {
        $brand = DocumentBrand::of($tenant, $forPdf, $settings);
        $wht = round((float) $payment->wht_amount, 2);

        return $brand + $side + [
            'payment' => $payment,
            'amount' => round((float) $payment->amount, 2),
            'wht' => $wht,
            'settled' => $payment->settledAmount(),
            'method' => $payment->payment_method
                ? ucfirst(str_replace('_', ' ', (string) $payment->payment_method))
                : null,
            // The words follow the money that actually changed hands, not
            // the figure settled on the document.
            'inWords' => AmountInWords::format($payment->amount, $brand['currency']),
        ];
    }

    public static function pdf(PaymentReceived|PaymentMade $payment): string
    {
        if ($payment instanceof PaymentReceived) {
            $payment->loadMissing(['customer', 'invoice', 'bank', 'whtCategory']);

            return DocumentBrand::pdf('payments.pdf', self::received($payment, forPdf: true));
        }

        $payment->loadMissing(['vendor', 'bill', 'bank', 'whtCategory', 'createdBy']);

        return DocumentBrand::pdf('payments.pdf', self::made($payment, forPdf: true));
    }

    public static function fileName(PaymentReceived|PaymentMade $payment): string
    {
        return $payment instanceof PaymentReceived
            ? DocumentBrand::fileName('Receipt', $payment->payment_number, $payment->customer?->name)
            : DocumentBrand::fileName('Payment advice', $payment->payment_number, $payment->vendor?->name);
    }
}
