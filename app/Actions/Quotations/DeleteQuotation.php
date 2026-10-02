<?php

namespace App\Actions\Quotations;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deleting a quotation. One that was accepted or converted is kept, so the
 * order or invoice made from it can still be traced back.
 */
class DeleteQuotation
{
    public function handle(Quotation $quotation): void
    {
        if ($reason = $this->blockedBecause($quotation)) {
            throw ValidationException::withMessages(['quotation' => $reason]);
        }

        DB::transaction(function () use ($quotation) {
            $quotation->items()->delete();
            $quotation->delete();
        });
    }

    public function blockedBecause(Quotation $quotation): ?string
    {
        if (in_array($quotation->status, [QuotationStatus::Accepted->value, QuotationStatus::Converted->value], true)) {
            return "Quotation {$quotation->quotation_number} has been {$quotation->status}, so it is kept.";
        }

        return null;
    }
}
