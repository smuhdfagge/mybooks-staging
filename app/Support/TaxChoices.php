<?php

namespace App\Support;

use App\Models\TaxRate;
use App\Models\Tenant;

/**
 * The VAT rates offered on document lines, and the one new lines start with
 * (audit MB-20). Lines used to take a typed percentage starting at 0%, so a
 * free-text line on an invoice carried no VAT unless someone remembered.
 *
 * The default is the business's "VAT on new sales / purchase lines" setting
 * (tenants.default_sales_tax_id / default_purchase_tax_id), else the rate
 * marked default on the tax rates page, else none (0%).
 */
class TaxChoices
{
    /**
     * Active rates for $type ('sales' or 'purchases'), one option per
     * percentage, "No VAT (0%)" first.
     *
     * @return list<array{rate: float, label: string}>
     */
    public static function options(string $type, ?int $tenantId = null): array
    {
        $tenantId ??= (int) auth()->user()?->tenant_id;
        // Not withoutGlobalScopes(): that would bring back deleted rates too.
        $rates = TaxRate::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->where('is_active', true)
            ->when($type === 'purchases', fn ($q) => $q->forPurchases(), fn ($q) => $q->forSales())
            ->orderBy('rate')->orderBy('sort_order')->orderBy('name')
            ->get(['name', 'rate']);

        $options = [['rate' => 0.0, 'label' => 'No VAT (0%)']];
        foreach ($rates->where('rate', '>', 0)->groupBy(fn ($r) => (string) (float) $r->rate) as $rate => $group) {
            $options[] = ['rate' => (float) $rate, 'label' => $group->pluck('name')->unique()->implode(' / ')];
        }

        return $options;
    }

    /** The percentage new lines of $type start with. */
    public static function defaultRate(string $type, ?int $tenantId = null): float
    {
        $tenantId ??= (int) auth()->user()?->tenant_id;
        $rate = self::defaultTaxRate($type, $tenantId);

        return $rate ? (float) $rate->rate : 0.0;
    }

    public static function defaultTaxRate(string $type, int $tenantId): ?TaxRate
    {
        $tenant = Tenant::find($tenantId);
        $id = $type === 'purchases' ? $tenant?->default_purchase_tax_id : $tenant?->default_sales_tax_id;
        // Only an active, not deleted rate that applies to this kind of line.
        $chosen = $id ? TaxRate::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->where('is_active', true)
            ->when($type === 'purchases', fn ($q) => $q->forPurchases(), fn ($q) => $q->forSales())
            ->find($id) : null;

        return $chosen ?? TaxRate::getDefault($tenantId, $type === 'purchases' ? 'purchases' : 'sales');
    }
}
