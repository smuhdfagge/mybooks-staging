<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment to a tax office or fund (tax pack 1 and 2): PAYE to a state
 * IRS, pension to a PFA, NHF, NSITF, ITF, or withholding tax to the NRS.
 * The journal (Dr liability, Cr bank) is posted when it is recorded.
 *
 * WHT remittances (body "wht") also record the authority (wht_authority:
 * nrs or state, with wht_state) and the bank paid from; deleting one
 * reverses its journal (WhtPayableController).
 */
class StatutoryRemittance extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'body', 'account_code', 'period_start', 'period_end', 'paid_to',
        'amount', 'paid_on', 'payment_method', 'reference', 'journal_id', 'created_by',
        'bank_id', 'wht_authority', 'wht_state',
    ];

    public const BODY_WHT = 'wht';

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'paid_on' => 'date',
        'amount' => 'decimal:2',
    ];

    /** @return BelongsTo<Bank, $this> */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /** @return BelongsTo<Journal, $this> */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
