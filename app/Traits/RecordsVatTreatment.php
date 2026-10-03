<?php

namespace App\Traits;

use App\Services\Accounting\VatTreatment;

/**
 * Document lines (invoice, bill, cash sale, credit note, supplier credit) record their VAT
 * treatment when they are created, so later changes to tax rates or items
 * don't change past returns. See VatTreatment::forLine() for the rule.
 */
trait RecordsVatTreatment
{
    protected static function bootRecordsVatTreatment(): void
    {
        static::creating(function ($line) {
            $line->vat_treatment = VatTreatment::forLine(
                $line->item_id ? (int) $line->item_id : null,
                (float) $line->tax_rate,
                $line->vat_treatment,
                $line->inheritedVatTreatment(),
            );
        });
    }

    /** A treatment to fall back on, e.g. from the line this one corrects. */
    public function inheritedVatTreatment(): ?string
    {
        return null;
    }
}
