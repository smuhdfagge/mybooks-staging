<?php

namespace App\Services\Accounting;

use Illuminate\Support\Facades\DB;

/**
 * The VAT rates every business starts with. They are ordinary tax rates the
 * business can edit (rate, name, active), so a change in the law is a data
 * change, not a code change.
 *
 * Source: Nigeria Tax Act 2025, in force 1 January 2026 (VAT chapter;
 * s.186 exempt supplies, s.187 zero-rated supplies), administered by the
 * Nigeria Revenue Service (formerly FIRS). Standard rate 7.5%, unchanged
 * from the VAT Act as amended by the Finance Act 2019.
 *
 * Written with the query builder so it is safe to run from a migration or
 * while someone else is signed in (the tenant scope would otherwise take
 * their business).
 */
class VatDefaults
{
    /** @return list<array<string, mixed>> */
    public static function rates(): array
    {
        return [
            [
                'name' => 'VAT 7.5%',
                'code' => 'VAT-STD',
                'rate' => 7.5,
                'vat_treatment' => VatTreatment::STANDARD,
                'description' => 'Standard rate. Nigeria Tax Act 2025, effective 1 January 2026. Change the rate here if the law changes.',
                'sort_order' => 1,
            ],
            [
                'name' => 'Zero-rated',
                'code' => 'VAT-ZERO',
                'rate' => 0,
                'vat_treatment' => VatTreatment::ZERO,
                'description' => 'VAT at 0% (NTA 2025 s.187): basic food, medical and pharmaceutical products and services, educational materials and tuition, exports, fertiliser and other farm inputs, electric vehicles. Input VAT is recoverable.',
                'sort_order' => 2,
            ],
            [
                'name' => 'Exempt',
                'code' => 'VAT-EXEMPT',
                'rate' => 0,
                'vat_treatment' => VatTreatment::EXEMPT,
                'description' => 'Exempt from VAT (NTA 2025 s.186): e.g. land and buildings, financial services and securities, passenger transport, baby and sanitary products. Input VAT on them is not recoverable.',
                'sort_order' => 3,
            ],
        ];
    }

    /**
     * Add any of the default rates the business doesn't have yet (matched
     * by code, deleted ones included so a removed rate isn't brought back).
     */
    public static function seedForTenant(int $tenantId): int
    {
        $created = 0;
        foreach (self::rates() as $rate) {
            $exists = DB::table('tax_rates')->where('tenant_id', $tenantId)->where('code', $rate['code'])->exists()
                // A business that already has a 7.5% rate, or a rate with this treatment, keeps its own.
                || DB::table('tax_rates')->where('tenant_id', $tenantId)->whereNull('deleted_at')
                    ->where(fn ($q) => $rate['rate'] > 0
                        ? $q->where('rate', $rate['rate'])
                        : $q->where('rate', 0)->where('vat_treatment', $rate['vat_treatment']))
                    ->exists();
            if ($exists) {
                continue;
            }

            DB::table('tax_rates')->insert($rate + [
                'tenant_id' => $tenantId,
                'type' => 'exclusive',
                'applies_to' => 'both',
                'is_compound' => false,
                'is_default' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $created++;
        }

        return $created;
    }
}
