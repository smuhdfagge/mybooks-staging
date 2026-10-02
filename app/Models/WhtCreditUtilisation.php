<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * WHT credit notes used against the business's income tax on one date:
 * Dr Income Tax Payable, Cr WHT Credit Notes Receivable.
 */
class WhtCreditUtilisation extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'utilisation_date', 'amount', 'reference', 'notes', 'created_by'];

    protected $casts = [
        'utilisation_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /** @return HasMany<PaymentReceived, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(PaymentReceived::class, 'wht_utilisation_id');
    }

    /** @return MorphOne<Journal, $this> */
    public function journal(): MorphOne
    {
        return $this->morphOne(Journal::class, 'reference');
    }
}
