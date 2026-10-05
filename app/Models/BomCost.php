<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An extra cost per batch on a bill of materials (session 14): labour,
 * power, packaging and the like that isn't kept as stock. When a build is
 * completed it is added to the finished goods' cost: Dr Inventory, Cr the
 * account chosen here.
 */
class BomCost extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'bill_of_materials_id',
        'description',
        'amount',
        'account_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /** @return BelongsTo<BillOfMaterial, $this> */
    public function billOfMaterial(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterial::class, 'bill_of_materials_id');
    }

    /** @return BelongsTo<ChartOfAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
