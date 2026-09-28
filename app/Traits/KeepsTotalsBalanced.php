<?php

namespace App\Traits;

/**
 * Keeps a financial document's stored amounts consistent with its ledger
 * entry (finding M2).
 *
 * Amounts are calculated in PHP without rounding and the database rounds
 * each column separately, so subtotal + tax - discount could differ from
 * the stored total by a kobo (e.g. 3 x 1.235 at 7.5% VAT: 3.71 + 0.28 vs
 * 3.98). The journal then could not balance.
 *
 * On save, each part is rounded to 2 decimals and, when the stored total
 * differs from the rounded parts by rounding only, the total (and balance
 * due) is set from the parts. A larger difference is left alone so the
 * journal balance check reports it instead of hiding a real bug.
 *
 * Models define documentTotalParts(): [positive fields, negative fields].
 */
trait KeepsTotalsBalanced
{
    /** The largest difference treated as rounding rather than an error. */
    protected static float $roundingTolerance = 0.02;

    protected static function bootKeepsTotalsBalanced(): void
    {
        static::saving(function ($document) {
            $document->reconcileTotals();
        });
    }

    public function reconcileTotals(): void
    {
        [$plus, $minus] = $this->documentTotalParts();

        foreach (array_merge($plus, $minus, ['total', 'amount_paid']) as $field) {
            if ($this->getAttribute($field) !== null) {
                $this->setAttribute($field, round((float) $this->getAttribute($field), 2));
            }
        }

        $computed = 0.0;
        foreach ($plus as $field) {
            $computed += (float) ($this->getAttribute($field) ?? 0);
        }
        foreach ($minus as $field) {
            $computed -= (float) ($this->getAttribute($field) ?? 0);
        }
        $computed = round($computed, 2);

        $total = (float) ($this->getAttribute('total') ?? 0);
        $difference = abs($computed - $total);

        if ($difference > 0.0001 && $difference <= static::$roundingTolerance) {
            $this->setAttribute('total', $computed);

            // amount_paid defaults to 0 in the database, so a new document may not have it set yet.
            if (array_key_exists('balance_due', $this->getAttributes())) {
                $this->setAttribute('balance_due', round($computed - (float) ($this->getAttribute('amount_paid') ?? 0), 2));
            }
        }
    }

    /**
     * @return array{0: string[], 1: string[]} fields added, fields subtracted
     */
    abstract protected function documentTotalParts(): array;
}
