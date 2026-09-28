<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;

class TaxGroup extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsToMany<TaxRate, $this> */
    public function taxRates(): BelongsToMany
    {
        return $this->belongsToMany(TaxRate::class, 'tax_group_rates')
            ->withPivot('sort_order')
            ->orderBy('pivot_sort_order')
            ->withTimestamps();
    }

    /** @return HasMany<Item, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * Get the combined tax rate
     */
    public function getCombinedRateAttribute(): float
    {
        $rates = $this->taxRates()->where('is_active', true)->get();
        $totalRate = 0;
        $baseRate = 0;

        foreach ($rates as $rate) {
            if ($rate->is_compound) {
                // Compound tax is calculated on base + previous taxes
                $totalRate += ($baseRate + 100) * ($rate->rate / 100);
            } else {
                $baseRate += $rate->rate;
                $totalRate += $rate->rate;
            }
        }

        return $totalRate;
    }

    /**
     * Get formatted combined rate
     */
    public function getFormattedRateAttribute(): string
    {
        return rtrim(rtrim(number_format($this->combined_rate, 4), '0'), '.') . '%';
    }

    /**
     * Get display name with rate
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} ({$this->formatted_rate})";
    }

    /**
     * Calculate taxes for a given amount
     * Returns array of individual tax amounts
     */
    public function calculateTaxes(float $amount): array
    {
        $rates = $this->taxRates()->where('is_active', true)->orderBy('pivot_sort_order')->get();
        $taxes = [];
        $runningTotal = $amount;

        foreach ($rates as $rate) {
            if ($rate->is_compound) {
                // Compound tax is calculated on amount + previous taxes
                $taxAmount = $runningTotal * ($rate->rate / 100);
            } else {
                // Non-compound tax is calculated on original amount
                $taxAmount = $amount * ($rate->rate / 100);
            }

            $taxes[] = [
                'tax_rate_id' => $rate->id,
                'name' => $rate->name,
                'code' => $rate->code,
                'rate' => $rate->rate,
                'amount' => round($taxAmount, 2),
                'is_compound' => $rate->is_compound,
            ];

            $runningTotal += $taxAmount;
        }

        return $taxes;
    }

    /**
     * Get total tax amount for a given amount
     */
    public function calculateTotalTax(float $amount): float
    {
        $taxes = $this->calculateTaxes($amount);
        return array_sum(array_column($taxes, 'amount'));
    }

    /**
     * Scope for active groups
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
