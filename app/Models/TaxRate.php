<?php

namespace App\Models;

use App\Services\Accounting\VatTreatment;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxRate extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'rate',
        'type',
        'applies_to',
        'vat_treatment',
        'tax_number',
        'description',
        'is_compound',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'is_compound' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Type constants
    const TYPE_INCLUSIVE = 'inclusive';

    const TYPE_EXCLUSIVE = 'exclusive';

    // Applies to constants
    const APPLIES_TO_SALES = 'sales';

    const APPLIES_TO_PURCHASES = 'purchases';

    const APPLIES_TO_BOTH = 'both';

    /**
     * A rate that charges VAT is standard-rated. A 0% rate keeps the
     * treatment chosen for it (zero-rated, exempt or out of scope), or none
     * until the business picks one (VatTreatment).
     */
    protected static function booted(): void
    {
        static::saving(function (TaxRate $rate) {
            if ((float) $rate->rate > 0) {
                $rate->vat_treatment = VatTreatment::STANDARD;
            } elseif ($rate->vat_treatment === VatTreatment::STANDARD) {
                $rate->vat_treatment = null;
            }
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsToMany<TaxGroup, $this> */
    public function taxGroups(): BelongsToMany
    {
        return $this->belongsToMany(TaxGroup::class, 'tax_group_rates')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    /** @return HasMany<Item, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * Calculate tax amount for a given amount
     */
    public function calculateTax(float $amount): float
    {
        if ($this->type === self::TYPE_INCLUSIVE) {
            // Tax is included in the amount, extract it
            return $amount - ($amount / (1 + ($this->rate / 100)));
        }

        // Tax is exclusive, add it
        return $amount * ($this->rate / 100);
    }

    /**
     * Get the net amount (before tax) from a gross amount (tax inclusive)
     */
    public function getNetAmount(float $grossAmount): float
    {
        if ($this->type === self::TYPE_INCLUSIVE) {
            return $grossAmount / (1 + ($this->rate / 100));
        }

        return $grossAmount;
    }

    /**
     * Get the gross amount (with tax) from a net amount
     */
    public function getGrossAmount(float $netAmount): float
    {
        if ($this->type === self::TYPE_EXCLUSIVE) {
            return $netAmount * (1 + ($this->rate / 100));
        }

        return $netAmount;
    }

    /**
     * Format the rate for display
     */
    public function getFormattedRateAttribute(): string
    {
        return rtrim(rtrim(number_format($this->rate, 4), '0'), '.').'%';
    }

    /**
     * Get display name with rate
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} ({$this->formatted_rate})";
    }

    /**
     * Scope for active tax rates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for sales taxes
     */
    public function scopeForSales($query)
    {
        return $query->whereIn('applies_to', [self::APPLIES_TO_SALES, self::APPLIES_TO_BOTH]);
    }

    /**
     * Scope for purchase taxes
     */
    public function scopeForPurchases($query)
    {
        return $query->whereIn('applies_to', [self::APPLIES_TO_PURCHASES, self::APPLIES_TO_BOTH]);
    }

    /**
     * Get the default tax rate for tenant
     */
    public static function getDefault($tenantId, $type = 'sales')
    {
        return static::where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->when($type === 'sales', fn ($q) => $q->forSales())
            ->when($type === 'purchases', fn ($q) => $q->forPurchases())
            ->first();
    }

    /**
     * Set this tax as the default (and unset others)
     */
    public function setAsDefault(): void
    {
        // Unset other defaults for the same applies_to scope
        static::where('tenant_id', $this->tenant_id)
            ->where('id', '!=', $this->id)
            ->where('is_default', true)
            ->when($this->applies_to === self::APPLIES_TO_SALES, function ($q) {
                $q->whereIn('applies_to', [self::APPLIES_TO_SALES, self::APPLIES_TO_BOTH]);
            })
            ->when($this->applies_to === self::APPLIES_TO_PURCHASES, function ($q) {
                $q->whereIn('applies_to', [self::APPLIES_TO_PURCHASES, self::APPLIES_TO_BOTH]);
            })
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }
}
