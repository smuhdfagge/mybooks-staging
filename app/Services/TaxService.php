<?php

namespace App\Services;

use App\Models\Item;
use App\Models\TaxGroup;
use App\Models\TaxRate;
use Illuminate\Database\Eloquent\Collection;

class TaxService
{
    /**
     * Calculate tax for a line item
     *
     * @param  float  $amount  The taxable amount (quantity * unit price)
     * @param  Item|null  $item  Optional item to get tax configuration from
     * @param  TaxRate|TaxGroup|int|null  $tax  Tax rate, tax group, or ID
     * @param  string  $taxType  'rate' or 'group'
     * @return array ['tax_amount' => float, 'taxes' => array]
     */
    public function calculateTax(
        float $amount,
        ?Item $item = null,
        $tax = null,
        string $taxType = 'rate'
    ): array {
        // If item is provided and taxable, use its tax configuration
        if ($item) {
            if (! $item->is_taxable) {
                return ['tax_amount' => 0, 'taxes' => []];
            }

            if ($item->taxGroup) {
                return $this->calculateGroupTax($amount, $item->taxGroup);
            }

            if ($item->taxRate) {
                return $this->calculateSingleTax($amount, $item->taxRate);
            }

            // Fall back to old tax_rate column
            if ($item->tax_rate > 0) {
                return [
                    'tax_amount' => round($amount * ($item->tax_rate / 100), 2),
                    'taxes' => [[
                        'name' => 'Tax',
                        'rate' => $item->tax_rate,
                        'amount' => round($amount * ($item->tax_rate / 100), 2),
                    ]],
                ];
            }

            return ['tax_amount' => 0, 'taxes' => []];
        }

        // If explicit tax is provided
        if ($tax) {
            if ($tax instanceof TaxGroup) {
                return $this->calculateGroupTax($amount, $tax);
            }

            if ($tax instanceof TaxRate) {
                return $this->calculateSingleTax($amount, $tax);
            }

            // If ID is provided, look up the tax
            if (is_numeric($tax)) {
                if ($taxType === 'group') {
                    $taxGroup = TaxGroup::find($tax);
                    if ($taxGroup) {
                        return $this->calculateGroupTax($amount, $taxGroup);
                    }
                } else {
                    $taxRate = TaxRate::find($tax);
                    if ($taxRate) {
                        return $this->calculateSingleTax($amount, $taxRate);
                    }
                }
            }
        }

        return ['tax_amount' => 0, 'taxes' => []];
    }

    /**
     * Calculate tax using a single tax rate
     */
    protected function calculateSingleTax(float $amount, TaxRate $taxRate): array
    {
        $taxAmount = $taxRate->calculateTax($amount);

        return [
            'tax_amount' => round($taxAmount, 2),
            'taxes' => [[
                'tax_rate_id' => $taxRate->id,
                'name' => $taxRate->name,
                'code' => $taxRate->code,
                'rate' => $taxRate->rate,
                'amount' => round($taxAmount, 2),
                'is_compound' => false,
            ]],
        ];
    }

    /**
     * Calculate tax using a tax group (multiple rates)
     */
    protected function calculateGroupTax(float $amount, TaxGroup $taxGroup): array
    {
        $taxes = $taxGroup->calculateTaxes($amount);
        $totalTax = array_sum(array_column($taxes, 'amount'));

        return [
            'tax_amount' => round($totalTax, 2),
            'taxes' => $taxes,
        ];
    }

    /**
     * Calculate total tax for multiple line items
     *
     * @param  array  $lineItems  Array of ['amount' => float, 'item' => Item|null, 'tax' => mixed, 'tax_type' => string]
     * @return array ['total_tax' => float, 'tax_breakdown' => array]
     */
    public function calculateTotalTax(array $lineItems): array
    {
        $totalTax = 0;
        $taxBreakdown = [];

        foreach ($lineItems as $lineItem) {
            $result = $this->calculateTax(
                $lineItem['amount'] ?? 0,
                $lineItem['item'] ?? null,
                $lineItem['tax'] ?? null,
                $lineItem['tax_type'] ?? 'rate'
            );

            $totalTax += $result['tax_amount'];

            // Aggregate tax breakdown by tax rate
            foreach ($result['taxes'] as $tax) {
                $key = $tax['tax_rate_id'] ?? $tax['name'];
                if (! isset($taxBreakdown[$key])) {
                    $taxBreakdown[$key] = [
                        'name' => $tax['name'],
                        'rate' => $tax['rate'],
                        'amount' => 0,
                    ];
                }
                $taxBreakdown[$key]['amount'] += $tax['amount'];
            }
        }

        return [
            'total_tax' => round($totalTax, 2),
            'tax_breakdown' => array_values($taxBreakdown),
        ];
    }

    /**
     * Get available tax rates for selection (dropdowns)
     *
     * @param  string  $type  'sales', 'purchases', or 'both'
     * @return Collection
     */
    public function getAvailableTaxRates(string $type = 'both')
    {
        $query = TaxRate::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($type !== 'both') {
            $query->where(function ($q) use ($type) {
                $q->where('applies_to', $type)
                    ->orWhere('applies_to', 'both');
            });
        }

        return $query->get();
    }

    /**
     * Get available tax groups for selection (dropdowns)
     *
     * @return Collection
     */
    public function getAvailableTaxGroups()
    {
        return TaxGroup::with('taxRates')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get the default tax rate for a tenant
     *
     * @param  string  $type  'sales' or 'purchases'
     */
    public function getDefaultTaxRate(string $type = 'sales'): ?TaxRate
    {
        return TaxRate::where('is_default', true)
            ->where('is_active', true)
            ->where(function ($q) use ($type) {
                $q->where('applies_to', $type)
                    ->orWhere('applies_to', 'both');
            })
            ->first();
    }

    /**
     * Extract net amount from gross (tax-inclusive) amount
     *
     * @param  TaxRate|TaxGroup  $tax
     */
    public function extractNetAmount(float $grossAmount, $tax): float
    {
        if ($tax instanceof TaxRate && $tax->type === TaxRate::TYPE_INCLUSIVE) {
            return $tax->getNetAmount($grossAmount);
        }

        if ($tax instanceof TaxGroup) {
            $rate = $tax->combined_rate;

            return $grossAmount / (1 + ($rate / 100));
        }

        return $grossAmount;
    }

    /**
     * Format tax for display on invoices/receipts
     *
     * @param  array  $taxes  Array from calculateTax result
     */
    public function formatTaxSummary(array $taxes): string
    {
        if (empty($taxes)) {
            return 'No tax';
        }

        $parts = [];
        foreach ($taxes as $tax) {
            $rate = rtrim(rtrim(number_format($tax['rate'], 4), '0'), '.');
            $parts[] = "{$tax['name']} ({$rate}%)";
        }

        return implode(' + ', $parts);
    }
}
