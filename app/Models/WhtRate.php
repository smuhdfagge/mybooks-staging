<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A withholding tax rate for one type of transaction (tax pack 2), with
 * separate rates for companies and individuals. Each business can edit
 * its own; the defaults below are added the first time it uses WHT.
 */
class WhtRate extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'code', 'name', 'rate_company', 'rate_individual', 'is_active', 'sort_order'];

    protected $casts = [
        'rate_company' => 'decimal:2',
        'rate_individual' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Resident payees, from the First Schedule of the Deduction of Tax at
     * Source (Withholding) Regulations 2024 (in force 1 July 2024; kept by
     * the Nigeria Tax Act 2025). Checked 2 October 2026 against PwC, Andersen
     * and UUBO summaries of the Regulations. Payees without a TIN suffer
     * double these rates. null = does not apply to that kind of payee.
     * The rent/hire/lease rate is the least certain (summaries differ
     * between 5% and 10% for hire of equipment); the business can change it.
     *
     * @var array<int, array{0: string, 1: string, 2: int|null, 3: int|null}>
     */
    public const DEFAULTS = [
        ['dividends_interest', 'Dividends and interest', 10, 10],
        ['royalties', 'Royalties', 10, 5],
        ['rent', 'Rent, hire or lease of property and equipment', 10, 10],
        ['professional', 'Commission, consultancy, professional, technical and management fees', 5, 5],
        ['brokerage', 'Brokerage fees', 5, 5],
        ['goods', 'Supply of goods (not by the manufacturer)', 2, 2],
        ['services', 'Other services', 2, 2],
        ['telecom', 'Telecom tower and colocation services', 2, 2],
        ['construction_major', 'Building, road, bridge and power plant construction', 2, 2],
        ['construction_other', 'Other construction work', 5, 5],
        ['directors_fees', "Directors' fees", null, 15],
    ];

    /** Add the default rates for a business that has none yet. */
    public static function ensureDefaults(int $tenantId): void
    {
        if (static::withoutGlobalScopes()->where('tenant_id', $tenantId)->exists()) {
            return;
        }
        foreach (self::DEFAULTS as $i => [$code, $name, $company, $individual]) {
            $rate = new self(['tenant_id' => $tenantId, 'code' => $code, 'name' => $name, 'rate_company' => $company, 'rate_individual' => $individual, 'is_active' => true, 'sort_order' => $i]);
            $rate->skipTenantGuard = true;
            $rate->save();
        }
    }

    /** The rate for a payee, or null where it does not apply. */
    public function rateFor(?string $entityType): ?float
    {
        $rate = $entityType === 'individual' ? $this->rate_individual : $this->rate_company;

        return $rate === null ? null : (float) $rate;
    }
}
