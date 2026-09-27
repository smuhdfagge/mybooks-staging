<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;

class DiscountRule extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    const TYPE_PERCENTAGE = 'percentage';
    const TYPE_FIXED = 'fixed';

    const SCOPE_ORDER = 'order';
    const SCOPE_LINE_ITEM = 'line_item';
    const SCOPE_CUSTOMER = 'customer';
    const SCOPE_ITEM = 'item';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'type',
        'value',
        'scope',
        'customer_id',
        'item_id',
        'min_quantity',
        'min_amount',
        'start_date',
        'end_date',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_quantity' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Scope: only active and within date range.
     */
    public function scopeApplicable($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            });
    }

    /**
     * Calculate discount amount for a given base amount / quantity.
     */
    public function calculate(float $baseAmount, float $quantity = 1): float
    {
        // Check minimum thresholds
        if ($this->min_quantity && $quantity < $this->min_quantity) {
            return 0;
        }
        if ($this->min_amount && $baseAmount < $this->min_amount) {
            return 0;
        }

        if ($this->type === self::TYPE_PERCENTAGE) {
            return round($baseAmount * ($this->value / 100), 2);
        }

        return min($this->value, $baseAmount);
    }

    /**
     * Find the best applicable discount for a line item context.
     */
    public static function findBestDiscount(int $tenantId, ?int $customerId, ?int $itemId, float $amount, float $quantity): ?self
    {
        $query = static::where('tenant_id', $tenantId)
            ->applicable()
            ->orderBy('priority', 'desc')
            ->orderBy('value', 'desc');

        $rules = $query->get();

        $bestDiscount = null;
        $bestAmount = 0;

        foreach ($rules as $rule) {
            // Check scope eligibility
            if ($rule->scope === self::SCOPE_CUSTOMER && $rule->customer_id !== $customerId) {
                continue;
            }
            if ($rule->scope === self::SCOPE_ITEM && $rule->item_id !== $itemId) {
                continue;
            }

            $discountAmount = $rule->calculate($amount, $quantity);
            if ($discountAmount > $bestAmount) {
                $bestAmount = $discountAmount;
                $bestDiscount = $rule;
            }
        }

        return $bestDiscount;
    }
}
