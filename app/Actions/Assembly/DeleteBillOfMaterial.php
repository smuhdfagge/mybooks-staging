<?php

namespace App\Actions\Assembly;

use App\Models\BillOfMaterial;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deleting a bill of materials (session 14): only one never used on an
 * assembly order; a used one is switched off instead. (The old check
 * looked at a column that doesn't exist and crashed.)
 */
class DeleteBillOfMaterial
{
    public function handle(BillOfMaterial $bom): void
    {
        if ($reason = $this->blockedBecause($bom)) {
            throw ValidationException::withMessages(['bill' => $reason]);
        }

        DB::transaction(function () use ($bom) {
            $bom->components()->delete();
            $bom->costs()->delete();
            $bom->delete();
        });
    }

    public function blockedBecause(BillOfMaterial $bom): ?string
    {
        return $bom->assemblyOrders()->withoutGlobalScopes()->exists()
            ? "{$bom->label()} has been used on assembly orders, so it can't be deleted. Untick \"In use\" to stop using it."
            : null;
    }
}
