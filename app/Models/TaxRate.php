<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;

class TaxRate extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'rate',
        'type',
        'applies_to',
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

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function taxGroups()
    {
        return $this->belongsToMany(TaxGroup::class, 'tax_group_rates')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    public function items()
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
        return rtrim(rtrim(number_format($this->rate, 4), '0'), '.') . '%';
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
            ->when($type === 'sales', fn($q) => $q->forSales())
            ->when($type === 'purchases', fn($q) => $q->forPurchases())
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
