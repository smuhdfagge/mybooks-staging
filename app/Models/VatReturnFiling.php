<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A month's VAT return marked as filed (Form 002 lines as filed, the hand
 * entered lines and the settlement journal). One per business per month.
 */
class VatReturnFiling extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'month', 'imports', 'import_vat', 'vat_withheld', 'auto_vat_paid',
        'credit_brought_forward', 'output_vat', 'input_vat', 'vat_payable', 'credit_carried_forward',
        'lines', 'settlement_journal_id', 'reference', 'filed_at', 'filed_by',
    ];

    protected $casts = [
        'imports' => 'decimal:2',
        'import_vat' => 'decimal:2',
        'vat_withheld' => 'decimal:2',
        'auto_vat_paid' => 'decimal:2',
        'credit_brought_forward' => 'decimal:2',
        'output_vat' => 'decimal:2',
        'input_vat' => 'decimal:2',
        'vat_payable' => 'decimal:2',
        'credit_carried_forward' => 'decimal:2',
        'lines' => 'array',
        'filed_at' => 'datetime',
    ];

    /** @return BelongsTo<Journal, $this> */
    public function settlementJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'settlement_journal_id');
    }

    /** @return BelongsTo<User, $this> */
    public function filedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filed_by');
    }

    /** @return array{imports: float, import_vat: float, vat_withheld: float, auto_vat_paid: float} */
    public function manual(): array
    {
        return [
            'imports' => (float) $this->imports,
            'import_vat' => (float) $this->import_vat,
            'vat_withheld' => (float) $this->vat_withheld,
            'auto_vat_paid' => (float) $this->auto_vat_paid,
        ];
    }

    /**
     * Line 100 for a month: the credit carried forward on the latest
     * return filed for an earlier month.
     */
    public static function creditBroughtForward(int $tenantId, string $month): float
    {
        return (float) (static::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('month', '<', $month)
            ->orderByDesc('month')->value('credit_carried_forward') ?? 0);
    }
}
