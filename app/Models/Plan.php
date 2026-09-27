<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'monthly_price',
        'annual_price',
        'allow_monthly_billing',
        'allow_annual_billing',
        'max_users',
        'features',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'annual_price' => 'decimal:2',
        'allow_monthly_billing' => 'boolean',
        'allow_annual_billing' => 'boolean',
        'max_users' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get subscriptions for this plan
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Scope to get only active plans
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Get price based on billing cycle
     */
    public function getPriceForCycle(string $cycle): float
    {
        return $cycle === 'annual' ? $this->annual_price : $this->monthly_price;
    }

    /**
     * Check if billing cycle is allowed
     */
    public function allowsBillingCycle(string $cycle): bool
    {
        return $cycle === 'annual' ? $this->allow_annual_billing : $this->allow_monthly_billing;
    }

    /**
     * Get the annual savings amount
     */
    public function getAnnualSavingsAttribute(): float
    {
        return ($this->monthly_price * 12) - $this->annual_price;
    }

    /**
     * Get the annual savings percentage
     */
    public function getAnnualSavingsPercentAttribute(): float
    {
        if ($this->monthly_price <= 0) {
            return 0;
        }
        return round((($this->monthly_price * 12 - $this->annual_price) / ($this->monthly_price * 12)) * 100, 1);
    }
}
