<?php

namespace App\Models;

use App\Traits\RecordsVatTreatment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillItem extends Model
{
    /**
     * The accounts a bill line can be posted to: expense and asset accounts
     * (e.g. Prepaid Expenses, Equipment), never cash or bank (audit MB-14).
     *
     * @return Builder<ChartOfAccount>
     */
    public static function postableAccounts(int $tenantId): Builder
    {
        return ChartOfAccount::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('type', ['expense', 'asset'])
            ->where(fn ($q) => $q->whereNull('sub_type')->orWhereNotIn('sub_type', ['cash', 'bank']))
            ->orderBy('account_code');
    }

    use HasFactory, RecordsVatTreatment;

    protected $fillable = [
        'bill_id',
        'item_id',
        'account_id',
        'description',
        'quantity',
        'unit_price',
        'discount',
        'tax_rate',
        'tax_amount',
        'vat_treatment',
        'total',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /** @return BelongsTo<Bill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** @return BelongsTo<ChartOfAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
