<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An extra cost on a build (session 14), from the bill's cost per batch
 * times the batches. amount is what was really spent, set on completion.
 */
class AssemblyOrderCost extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'assembly_order_id',
        'description',
        'account_id',
        'planned_amount',
        'amount',
    ];

    protected $casts = [
        'planned_amount' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    /** @return BelongsTo<AssemblyOrder, $this> */
    public function assemblyOrder(): BelongsTo
    {
        return $this->belongsTo(AssemblyOrder::class);
    }

    /** @return BelongsTo<ChartOfAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function actualAmount(): float
    {
        return (float) ($this->amount ?? $this->planned_amount);
    }
}
