<?php

namespace App\Traits;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stock document's warehouse (session 12): where its goods come from or
 * go to. Documents saved before warehouses existed were given the default
 * warehouse by the migration.
 */
trait HasWarehouse
{
    public function initializeHasWarehouse(): void
    {
        $this->mergeFillable(['warehouse_id']);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** The warehouse id to use, the business's default when none is set. */
    public function warehouseIdOrDefault(): int
    {
        return $this->warehouse_id ? (int) $this->warehouse_id : Warehouse::defaultIdFor((int) $this->tenant_id);
    }
}
