<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * A withholding tax transaction type with its rates, one table per
 * business, seeded with the statutory rates and editable.
 *
 * Rates are percentages for resident payees. A payee without a TIN is
 * charged twice the rate on non-passive income (double_without_tin).
 * Changes are written to the activity log by WhtSetupController.
 *
 * @property float|string $rate_company
 * @property float|string $rate_individual
 */
class WhtCategory extends Model
{
    use BelongsToTenant;

    /** Where the seeded rates come from (shown on the rates page). */
    public const SOURCE = 'Deduction of Tax at Source (Withholding) Regulations 2024, Schedule (in force 1 Jan 2025); '
        .'deduction at source continues under s.51 Nigeria Tax Administration Act 2025 from 1 Jan 2026';

    public const EFFECTIVE_FROM = '2025-01-01';

    protected $fillable = [
        'tenant_id', 'code', 'name', 'description', 'rate_company', 'rate_individual',
        'double_without_tin', 'effective_from', 'source', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'rate_company' => 'decimal:2',
        'rate_individual' => 'decimal:2',
        'double_without_tin' => 'boolean',
        'is_active' => 'boolean',
        'effective_from' => 'date',
    ];

    /**
     * The seeded transaction types and resident rates (percent).
     *
     * From the Schedule to the Deduction of Tax at Source (Withholding)
     * Regulations 2024 (FIRS: in force 1 Jan 2025), as summarised by PwC
     * Nigeria and Andersen Nigeria; the Nigeria Tax Administration Act 2025
     * s.51 keeps deduction at source with rates set by regulations. Where
     * summaries differ (rent: 10% in most, 5% in one) the commonly applied
     * figure is used. Each business can edit these; check with your
     * accountant.
     *
     * Checked 2 Oct 2026 against: PwC Worldwide Tax Summaries, Nigeria,
     * Corporate withholding taxes (last reviewed 29 May 2026); Andersen
     * Nigeria summary of the 2024 Regulations; owoode.com "Nigeria Tax Rates
     * 2026" (3 Sep 2026) and nrsportal.ng WHT 2026 guide (23 Mar 2026), which
     * both say the 2024 Regulations' rates still apply under the Nigeria Tax
     * Act 2025. All agree with the figures below. Non-resident rates (10%
     * on fees, 5% on services, 20% on directors' fees) are not seeded.
     *
     * @return array<int, array{code: string, name: string, rate_company: float, rate_individual: float, double_without_tin: bool}>
     */
    public static function defaults(): array
    {
        return [
            ['code' => 'supply_goods', 'name' => 'Supply of goods (contract supplies, not made by the supplier)', 'rate_company' => 2, 'rate_individual' => 2, 'double_without_tin' => true],
            ['code' => 'services', 'name' => 'Other services and contracts', 'rate_company' => 2, 'rate_individual' => 2, 'double_without_tin' => true],
            ['code' => 'construction', 'name' => 'Construction of roads, bridges, buildings and power plants', 'rate_company' => 2, 'rate_individual' => 2, 'double_without_tin' => true],
            ['code' => 'construction_other', 'name' => 'Other construction work', 'rate_company' => 5, 'rate_individual' => 5, 'double_without_tin' => true],
            ['code' => 'professional', 'name' => 'Professional, consultancy, technical and management fees', 'rate_company' => 5, 'rate_individual' => 5, 'double_without_tin' => true],
            ['code' => 'commission', 'name' => 'Commission', 'rate_company' => 5, 'rate_individual' => 5, 'double_without_tin' => true],
            ['code' => 'brokerage', 'name' => 'Brokerage fees', 'rate_company' => 5, 'rate_individual' => 5, 'double_without_tin' => true],
            ['code' => 'rent', 'name' => 'Rent, hire and lease (property and equipment)', 'rate_company' => 10, 'rate_individual' => 10, 'double_without_tin' => false],
            ['code' => 'royalties', 'name' => 'Royalties', 'rate_company' => 10, 'rate_individual' => 5, 'double_without_tin' => false],
            ['code' => 'dividends', 'name' => 'Dividends', 'rate_company' => 10, 'rate_individual' => 10, 'double_without_tin' => false],
            ['code' => 'interest', 'name' => 'Interest', 'rate_company' => 10, 'rate_individual' => 10, 'double_without_tin' => false],
            ['code' => 'directors_fees', 'name' => "Directors' fees", 'rate_company' => 15, 'rate_individual' => 15, 'double_without_tin' => true],
        ];
    }

    /**
     * Give a business the seeded rates it doesn't have yet (by code), so
     * running it again never overwrites the business's own changes.
     */
    public static function seedDefaults(int $tenantId): void
    {
        $existing = DB::table('wht_categories')->where('tenant_id', $tenantId)->pluck('code')->all();

        foreach (static::defaults() as $i => $row) {
            if (in_array($row['code'], $existing, true)) {
                continue;
            }
            DB::table('wht_categories')->insert($row + [
                'tenant_id' => $tenantId,
                'effective_from' => static::EFFECTIVE_FROM,
                'source' => static::SOURCE,
                'is_active' => true,
                'sort_order' => ($i + 1) * 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * The rate (percent) for a payee: the company or individual rate,
     * doubled when the payee has no TIN and the type is non-passive income.
     */
    public function rateFor(string $payeeType, bool $hasTin): float
    {
        $rate = (float) ($payeeType === 'individual' ? $this->rate_individual : $this->rate_company);

        if (! $hasTin && $this->double_without_tin) {
            $rate *= 2;
        }

        return round($rate, 2);
    }
}
