<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatutoryTaxTemplate extends Model
{
    protected $fillable = [
        'country_code',
        'name',
        'tax_year',
        'brackets',
        'period',
        'employer_contributions',
        'description',
        'is_current',
    ];

    protected $casts = [
        'brackets' => 'array',
        'employer_contributions' => 'array',
        'is_current' => 'boolean',
        'tax_year' => 'integer',
    ];

    public function scopeForCountry($query, string $countryCode)
    {
        return $query->where('country_code', $countryCode);
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Apply this template to a tenant's tax brackets.
     * Deactivates old brackets and creates new ones from template.
     */
    public function applyToTenant(int $tenantId): int
    {
        // Deactivate existing brackets for this period type
        \App\Models\TaxBracket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('period', $this->period)
            ->update(['is_active' => false]);

        $created = 0;
        foreach ($this->brackets as $index => $bracket) {
            \App\Models\TaxBracket::create([
                'tenant_id' => $tenantId,
                'name' => $bracket['name'] ?? "{$this->name} Bracket ".($index + 1),
                'min_amount' => $bracket['min'] ?? $bracket['min_amount'] ?? 0,
                'max_amount' => $bracket['max'] ?? $bracket['max_amount'] ?? null,
                'rate' => $bracket['rate'],
                'fixed_amount' => $bracket['fixed_amount'] ?? 0,
                'period' => $this->period,
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * Get available countries with templates.
     */
    public static function availableCountries(): array
    {
        return static::select('country_code')
            ->distinct()
            ->pluck('country_code')
            ->toArray();
    }
}
