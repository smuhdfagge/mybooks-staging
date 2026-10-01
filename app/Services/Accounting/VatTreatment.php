<?php

namespace App\Services\Accounting;

use App\Models\Item;
use App\Models\TaxRate;
use Illuminate\Support\Facades\DB;

/**
 * How a supply is treated for VAT, for the monthly return.
 *
 *   standard      VAT charged at the standard rate (7.5% today)
 *   zero          VATable at 0%: basic food, medical, exports and the rest
 *                 of the Nigeria Tax Act 2025 zero-rated list. Input VAT
 *                 on them can be recovered.
 *   exempt        outside VAT (NTA 2025 exempt list). No output VAT and the
 *                 input VAT on them is not recoverable.
 *   out_of_scope  not a supply for VAT at all (e.g. a deposit or a
 *                 disbursement); left off the return.
 *
 * Each invoice, bill, cash sale and credit note line records its treatment
 * when it is saved, so changing a tax rate later doesn't rewrite filed
 * months.
 *
 * Rule for a line (forLine):
 *  - VAT charged (rate above 0): standard. The ledger holds that VAT, so the
 *    line can't be zero-rated or exempt whatever else says so.
 *  - No VAT: the treatment chosen on the line, else the one inherited (a
 *    credit note line from its invoice line), else the item's tax rate (or
 *    tax group) when that is zero-rated, exempt or out of scope.
 *  - Otherwise null ("not classified"). An item simply not ticked as
 *    taxable is not enough to call it exempt (the item form leaves that box
 *    unticked by default). The return lists these lines so they can be
 *    classified before filing, rather than showing them as standard-rated
 *    with no VAT.
 */
class VatTreatment
{
    public const STANDARD = 'standard';

    public const ZERO = 'zero';

    public const EXEMPT = 'exempt';

    public const OUT_OF_SCOPE = 'out_of_scope';

    public const ALL = [self::STANDARD, self::ZERO, self::EXEMPT, self::OUT_OF_SCOPE];

    /** Treatments a line without VAT can have. */
    public const NO_VAT = [self::ZERO, self::EXEMPT, self::OUT_OF_SCOPE];

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::STANDARD => 'Standard-rated',
            self::ZERO => 'Zero-rated',
            self::EXEMPT => 'Exempt',
            self::OUT_OF_SCOPE => 'Outside the scope of VAT',
        ];
    }

    public static function label(?string $treatment): string
    {
        return self::labels()[$treatment] ?? 'Not classified';
    }

    /**
     * VAT status code used by the TaxPro-Max sales schedule upload:
     * 0 = VATable, 1 = zero-rated, 2 = exempt.
     */
    public static function taxProMaxStatus(?string $treatment): string
    {
        return match ($treatment) {
            self::ZERO => '1',
            self::EXEMPT => '2',
            default => '0',
        };
    }

    public static function forLine(?int $itemId, float $rate, ?string $chosen = null, ?string $inherited = null): ?string
    {
        if ($rate > 0) {
            return self::STANDARD;
        }
        if (in_array($chosen, self::NO_VAT, true)) {
            return $chosen;
        }
        if (in_array($inherited, self::NO_VAT, true)) {
            return $inherited;
        }

        return $itemId ? self::forItem($itemId) : null;
    }

    /**
     * The no-VAT treatment set on the item's tax rate or tax group, if any.
     */
    public static function forItem(int $itemId): ?string
    {
        $item = Item::withoutGlobalScopes()->find($itemId);
        if (! $item) {
            return null;
        }

        if ($item->tax_group_id) {
            $treatments = DB::table('tax_group_rates')
                ->join('tax_rates', 'tax_rates.id', '=', 'tax_group_rates.tax_rate_id')
                ->where('tax_group_rates.tax_group_id', $item->tax_group_id)
                ->whereNull('tax_rates.deleted_at')
                ->pluck('tax_rates.vat_treatment')
                ->unique();

            $only = $treatments->count() === 1 ? $treatments->first() : null;

            return in_array($only, self::NO_VAT, true) ? $only : null;
        }

        if ($item->tax_rate_id) {
            $treatment = TaxRate::withoutGlobalScopes()->withTrashed()->whereKey($item->tax_rate_id)->value('vat_treatment');

            return in_array($treatment, self::NO_VAT, true) ? $treatment : null;
        }

        return null;
    }
}
