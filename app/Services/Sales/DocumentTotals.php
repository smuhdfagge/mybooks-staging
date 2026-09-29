<?php

namespace App\Services\Sales;

use App\Support\Money;

/**
 * Line and document totals for sales documents (invoices, sales orders,
 * recurring invoices). One place, so the web, API, sales-order conversion
 * and recurring invoices can't disagree (findings A4 and Q1).
 *
 * VAT is charged on what the customer actually pays: each line's amount
 * after its own discount and its share of the document discount. The
 * document discount is shared across lines in proportion to their amounts
 * (the last line takes any rounding difference).
 *
 * Example: 100,000 of goods, 10% discount, 7.5% VAT gives subtotal 100,000,
 * discount 10,000, VAT 6,750 and total 96,750.
 */
class DocumentTotals
{
    /**
     * @param  array<int|string, array<string, mixed>>  $lines  each with quantity, unit_price, optional
     *                                                          discount + discount_type (fixed|percentage) and tax_rate
     * @param  string|null  $discountType  document discount: 'percentage', 'fixed' or null
     * @return array{lines: array<int|string, array<string, mixed>>, subtotal: float, discount_amount: float, tax_amount: float, total: float}
     */
    public static function calculate(array $lines, ?string $discountType, float|int|string|null $discountValue): array
    {
        // Round each line, then add the rounded lines (Q2).
        $net = [];
        $grossOf = [];
        foreach ($lines as $key => $line) {
            $gross = (float) ($line['quantity'] ?? 0) * (float) ($line['unit_price'] ?? 0);
            $grossOf[$key] = Money::round($gross);
            $lineDiscount = (float) ($line['discount'] ?? 0);
            if ($lineDiscount > 0 && ($line['discount_type'] ?? 'fixed') === 'percentage') {
                $lineDiscount = $gross * $lineDiscount / 100;
            }
            $net[$key] = Money::round(max(0, $gross - $lineDiscount));
        }

        $subtotal = Money::sum($net);

        $discount = (float) ($discountValue ?? 0);
        if ($discountType === 'percentage') {
            $discount = $subtotal * $discount / 100;
        }
        $discount = Money::round(min(max(0, $discount), $subtotal));

        // Share the document discount across lines, in proportion to their
        // amounts, in whole kobo so the shares add up to the discount.
        $shares = $net === [] ? [] : Money::allocate($discount, $net);

        $out = [];
        $taxes = [];
        foreach ($lines as $key => $line) {
            $rate = (float) ($line['tax_rate'] ?? 0);
            $lineTax = Money::percent(Money::subtract($net[$key], $shares[$key]), $rate);
            $taxes[] = $lineTax;

            $out[$key] = array_merge(array_diff_key($line, ['discount_type' => true]), [
                // The line's own discount as money, ready to store.
                'discount' => Money::subtract($grossOf[$key], $net[$key]),
                // This line's part of the document discount (bills need it).
                'discount_share' => $shares[$key],
                'tax_rate' => $rate,
                'tax_amount' => $lineTax,
                'total' => Money::add($net[$key], $lineTax),
            ]);
        }
        $tax = Money::sum($taxes);

        return [
            'lines' => $out,
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total' => Money::subtract(Money::add($subtotal, $tax), $discount),
        ];
    }
}
