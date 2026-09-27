<?php

namespace App\Services;

use App\Models\TaxBracket;

class PayrollTaxService
{
    /**
     * Calculate progressive tax based on tenant's tax brackets.
     *
     * Falls back to flat rate if no brackets are configured.
     *
     * @param  float  $taxableIncome  The taxable income amount
     * @param  int    $tenantId       The tenant ID for bracket lookup
     * @param  float  $flatRate       Fallback flat tax rate (percentage) when no brackets exist
     * @param  string $period         'monthly' or 'annual'
     * @return array{tax: float, breakdown: array, method: string}
     */
    public function calculateTax(float $taxableIncome, int $tenantId, float $flatRate = 0, string $period = 'monthly'): array
    {
        if ($taxableIncome <= 0) {
            return ['tax' => 0, 'breakdown' => [], 'method' => 'none'];
        }

        $brackets = TaxBracket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('period', $period)
            ->orderBy('sort_order')
            ->orderBy('min_amount')
            ->get();

        if ($brackets->isEmpty()) {
            // Fallback: use flat rate
            $tax = $flatRate > 0 ? round($taxableIncome * $flatRate / 100, 2) : 0;

            return [
                'tax' => $tax,
                'breakdown' => $tax > 0 ? [['bracket' => 'Flat Rate', 'rate' => $flatRate, 'taxable' => $taxableIncome, 'tax' => $tax]] : [],
                'method' => 'flat',
            ];
        }

        return $this->calculateProgressiveTax($taxableIncome, $brackets);
    }

    /**
     * Calculate tax using progressive/slab brackets.
     *
     * Each bracket applies its rate only to income within its min–max range.
     * The fixed_amount field allows modelling brackets that use a flat base
     * plus marginal rate on excess (common in many jurisdictions).
     */
    protected function calculateProgressiveTax(float $taxableIncome, $brackets): array
    {
        $totalTax = 0;
        $breakdown = [];
        $remainingIncome = $taxableIncome;

        foreach ($brackets as $bracket) {
            if ($remainingIncome <= 0) {
                break;
            }

            $min = (float) $bracket->min_amount;
            $max = $bracket->max_amount !== null ? (float) $bracket->max_amount : PHP_FLOAT_MAX;

            // Skip brackets whose range is below the already-taxed portions
            if ($taxableIncome <= $min) {
                break;
            }

            // Calculate the portion of income that falls in this bracket
            $bracketWidth = $max - $min;
            $taxableInBracket = min($remainingIncome, $bracketWidth);

            // Apply this bracket's rate
            $bracketTax = round($taxableInBracket * (float) $bracket->rate / 100, 2);

            // Add any fixed amount for this bracket (only if income reaches this bracket)
            if ($taxableIncome > $min && (float) $bracket->fixed_amount > 0) {
                $bracketTax += (float) $bracket->fixed_amount;
            }

            $totalTax += $bracketTax;
            $remainingIncome -= $taxableInBracket;

            $breakdown[] = [
                'bracket' => $bracket->name,
                'min' => $min,
                'max' => $bracket->max_amount,
                'rate' => (float) $bracket->rate,
                'fixed_amount' => (float) $bracket->fixed_amount,
                'taxable' => $taxableInBracket,
                'tax' => $bracketTax,
            ];
        }

        return [
            'tax' => round($totalTax, 2),
            'breakdown' => $breakdown,
            'method' => 'progressive',
        ];
    }

    /**
     * Calculate employer contributions based on tenant configuration.
     *
     * Employer contributions are costs borne by the employer (not deducted
     * from employee pay) such as employer-side social security, pension
     * matching, health insurance employer share, etc.
     *
     * @param  float  $grossSalary  The employee's gross salary
     * @param  array  $contributions  Array of contribution rules, each with:
     *   - name:   string  Label (e.g. "Employer Social Security")
     *   - type:   string  'fixed' or 'percentage'
     *   - rate:   float   Fixed amount or percentage
     *   - cap:    float|null  Maximum contribution amount (null = no cap)
     * @return array{total: float, details: array}
     */
    public function calculateEmployerContributions(float $grossSalary, array $contributions): array
    {
        $total = 0;
        $details = [];

        foreach ($contributions as $contribution) {
            $name = $contribution['name'] ?? 'Unnamed';
            $type = $contribution['type'] ?? 'percentage';
            $rate = (float) ($contribution['rate'] ?? 0);
            $cap = isset($contribution['cap']) ? (float) $contribution['cap'] : null;

            if ($type === 'fixed') {
                $amount = $rate;
            } else {
                $amount = round($grossSalary * $rate / 100, 2);
            }

            if ($cap !== null && $amount > $cap) {
                $amount = $cap;
            }

            $total += $amount;
            $details[] = [
                'name' => $name,
                'type' => $type,
                'rate' => $rate,
                'cap' => $cap,
                'amount' => $amount,
            ];
        }

        return [
            'total' => round($total, 2),
            'details' => $details,
        ];
    }
}
