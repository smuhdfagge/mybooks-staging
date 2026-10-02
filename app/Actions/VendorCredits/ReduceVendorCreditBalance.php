<?php

namespace App\Actions\VendorCredits;

use App\Enums\VendorCreditStatus;
use App\Models\VendorCredit;

/** Lower a credit's open balance; it closes when nothing is left. */
class ReduceVendorCreditBalance
{
    public static function by(VendorCredit $credit, float $amount): void
    {
        $credit->balance = round((float) $credit->balance - $amount, 2);
        if ($credit->balance <= 0.004) {
            $credit->balance = 0;
            $credit->status = VendorCreditStatus::Closed->value;
        }
        $credit->withoutPeriodValidation()->save();
    }
}
