<?php

namespace App\Services\Sales;

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
        $net = [];
        $grossOf = [];
        foreach ($lines as $key => $line) {
            $gross = (float) ($line['quantity'] ?? 0) * (float) ($line['unit_price'] ?? 0);
            $grossOf[$key] = round($gross, 2);
            $lineDiscount = (float) ($line['discount'] ?? 0);
            if ($lineDiscount > 0 && ($line['discount_type'] ?? 'fixed') === 'percentage') {
                $lineDiscount = $gross * $lineDiscount / 100;
            }
            $net[$key] = round(max(0, $gross - $lineDiscount), 2);
        }

        $subtotal = round(array_sum($net), 2);

        $discount = (float) ($discountValue ?? 0);
        if ($discountType === 'percentage') {
            $discount = $subtotal * $discount / 100;
        }
        $discount = round(min(max(0, $discount), $subtotal), 2);

        // Share the document discount across lines, in proportion to their amounts.
        $shares = [];
        $left = $discount;
        $keys = array_keys($net);
        $last = end($keys);
        foreach ($net as $key => $amount) {
            if ($key === $last) {
                $shares[$key] = round($left, 2);
            } else {
                $shares[$key] = $subtotal > 0 ? round($discount * $amount / $subtotal, 2) : 0.0;
                $left -= $shares[$key];
            }
        }

        $out = [];
        $tax = 0.0;
        foreach ($lines as $key => $line) {
            $rate = (float) ($line['tax_rate'] ?? 0);
            $lineTax = round(($net[$key] - $shares[$key]) * $rate / 100, 2);
            $tax += $lineTax;

            $out[$key] = array_merge(array_diff_key($line, ['discount_type' => true]), [
                // The line's own discount as money, ready to store.
                'discount' => round($grossOf[$key] - $net[$key], 2),
                // This line's part of the document discount (bills need it).
                'discount_share' => $shares[$key],
                'tax_rate' => $rate,
                'tax_amount' => $lineTax,
                'total' => round($net[$key] + $lineTax, 2),
            ]);
        }
        $tax = round($tax, 2);

        return [
            'lines' => $out,
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total' => round($subtotal - $discount + $tax, 2),
        ];
    }
}
