<?php

namespace App\Support;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\Vendor;
use App\Models\WhtRate;

/**
 * Business-level WHT settings (tax pack 2), stored in
 * tenants.settings['wht'].
 *
 * - small_company: the business is a small company. Under the Deduction of
 *   Tax at Source (Withholding) Regulations 2024 a small company need not
 *   deduct WHT from a supplier with a TIN when the month's transactions
 *   with that supplier are ₦2,000,000 or less. The Regulations put "small"
 *   at turnover under ₦25m; the Nigeria Tax Act 2025 defines a small
 *   company as turnover ₦50m or less (and fixed assets ₦250m or less).
 *   Which one applies is not settled, so the business decides.
 * - double_without_tin: payees with no TIN suffer double the rate
 *   (Regulations 2024).
 * - Remittance: to the NRS by the 21st of the next month (companies);
 *   WHT on individuals goes to the State IRS (Regulations 2024).
 */
final class WithholdingTax
{
    public const DEFAULTS = [
        'small_company' => false,
        'small_company_threshold' => 2000000,
        'double_without_tin' => true,
    ];

    /** @return array<string, mixed> */
    public static function settings(?Tenant $tenant): array
    {
        $saved = (array) ($tenant?->settings['wht'] ?? []);

        return array_merge(self::DEFAULTS, array_intersect_key($saved, self::DEFAULTS));
    }

    /**
     * What the payment forms need to suggest WHT: the rates, whether each
     * vendor or customer is an individual and has a TIN, and the VAT in
     * each open bill or invoice (WHT is worked out on the amount before VAT).
     *
     * @param  'made'|'received'  $side
     * @return array<string, mixed>
     */
    public static function formConfig(int $tenantId, string $side): array
    {
        WhtRate::ensureDefaults($tenantId);
        $settings = self::settings(Tenant::find($tenantId));

        $parties = ($side === 'made' ? Vendor::query() : Customer::query())
            ->where('tenant_id', $tenantId)->get(['id', 'entity_type', 'tax_number'])
            ->mapWithKeys(fn ($p) => [$p->id => ['type' => $p->entity_type ?: 'company', 'has_tin' => filled($p->tax_number)]]);

        $docs = ($side === 'made'
            ? Bill::where('tenant_id', $tenantId)->whereIn('status', ['unpaid', 'partial', 'overdue'])
            : Invoice::where('tenant_id', $tenantId)->whereIn('status', ['draft', 'sent', 'unpaid', 'partial', 'overdue']))
            ->get(['id', 'total', 'tax_amount'])
            ->mapWithKeys(fn ($d) => [$d->id => ['total' => (float) $d->total, 'tax' => (float) $d->tax_amount]]);

        return [
            'side' => $side,
            'partyKey' => $side === 'made' ? 'selectedVendor' : 'selectedCustomer',
            'docKey' => $side === 'made' ? 'selectedBill' : 'selectedInvoice',
            'rates' => WhtRate::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('sort_order')->get()
                ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'company' => $r->rateFor('company'), 'individual' => $r->rateFor('individual')])->values(),
            'parties' => $parties,
            'docs' => $docs,
            'doubleWithoutTin' => (bool) $settings['double_without_tin'],
            'smallCompany' => (bool) $settings['small_company'],
            'threshold' => (float) $settings['small_company_threshold'],
        ];
    }
}
