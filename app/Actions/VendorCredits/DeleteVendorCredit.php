<?php

namespace App\Actions\VendorCredits;

use App\Enums\VendorCreditStatus;
use App\Models\VendorCredit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Only a draft or void supplier credit can be deleted; an open one is voided first. */
class DeleteVendorCredit
{
    public function handle(VendorCredit $credit): void
    {
        if (! in_array($credit->status, [VendorCreditStatus::Draft->value, VendorCreditStatus::Void->value], true)) {
            throw ValidationException::withMessages(['vendor_credit' => 'Void this supplier credit before deleting it.']);
        }

        DB::transaction(function () use ($credit) {
            $credit->items()->delete();
            $credit->delete();
        });
    }
}
