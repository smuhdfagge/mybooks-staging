<?php

namespace App\Services\Accounting;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentMade;
use App\Models\Tenant;
use App\Models\Vendor;
use App\Models\WhtCategory;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Withholding tax (WHT) on payments.
 *
 * WHT is worked out on the amount before VAT. A payment's `amount` stays
 * the money that moved through the bank (the net); `wht_amount` is what
 * was withheld; together they settle the bill or invoice.
 *
 * When only the net is known, the WHT is grossed up so that
 *   wht = rate x (net + wht) x (amount before VAT / document total).
 * E.g. a 1,075,000 bill (1,000,000 + 7.5% VAT) at 2%: WHT 20,000, net
 * paid 1,055,000.
 *
 * Who it is paid to: WHT deducted from companies goes to the Nigeria
 * Revenue Service (federal); from individuals (including sole traders and
 * partnerships) to the state internal revenue service where they live.
 */
class WithholdingTax
{
    /**
     * Small company exemption (Withholding Regulations 2024): a small
     * company need not deduct WHT from a supplier with a valid TIN when the
     * supplier's transactions in the month are no more than N2,000,000.
     * Editable per business on the WHT settings page.
     */
    public const SMALL_COMPANY_THRESHOLD = 2000000.0;

    public const AUTHORITY_FEDERAL = 'nrs';

    public const AUTHORITY_STATE = 'state';

    /** @return array{business_type: string, small_company: bool, small_company_threshold: float} */
    public static function settings(int $tenantId): array
    {
        $saved = Tenant::find($tenantId)?->settings['wht'] ?? [];

        return [
            'business_type' => ($saved['business_type'] ?? 'company') === 'individual' ? 'individual' : 'company',
            'small_company' => (bool) ($saved['small_company'] ?? false),
            'small_company_threshold' => (float) ($saved['small_company_threshold'] ?? self::SMALL_COMPANY_THRESHOLD),
        ];
    }

    public static function authorityFor(string $payeeType): string
    {
        return $payeeType === 'individual' ? self::AUTHORITY_STATE : self::AUTHORITY_FEDERAL;
    }

    public static function authorityLabel(string $authority, ?string $state = null): string
    {
        if ($authority === self::AUTHORITY_STATE) {
            return trim(($state ?: 'State').' Internal Revenue Service');
        }

        return 'Nigeria Revenue Service (NRS)';
    }

    /** Share of a document's total that is before VAT (1 when there is no document). */
    public static function exVatShare(Bill|Invoice|null $document): float
    {
        $total = (float) ($document->total ?? 0);
        if (! $document || $total <= 0) {
            return 1.0;
        }

        return max(0.0, min(1.0, ($total - (float) $document->tax_amount) / $total));
    }

    /**
     * WHT on a net payment, grossed up. When the net plus WHT comes within
     * 5 kobo of what is owed, the WHT takes the rounding so the document is
     * settled exactly.
     *
     * @return array{wht: float, base: float}
     */
    public static function grossUp(float $ratePercent, float $exVatShare, float $net, ?float $owed = null): array
    {
        $r = $ratePercent / 100 * $exVatShare;
        if ($r <= 0 || $r >= 1) {
            return ['wht' => 0.0, 'base' => 0.0];
        }

        $wht = round($r * $net / (1 - $r), 2);
        if ($owed !== null && abs($net + $wht - $owed) <= 0.05) {
            $wht = round($owed - $net, 2);
        }

        return ['wht' => $wht, 'base' => round(($net + $wht) * $exVatShare, 2)];
    }

    /**
     * WHT columns for a payment to a vendor. $data may hold wht_category_id
     * and wht_amount (given = used as entered; left out = worked out).
     *
     * @param  array<string, mixed>  $data
     * @return array{wht_category_id: ?int, wht_rate: float, wht_base: float, wht_amount: float, wht_authority: ?string, wht_state: ?string}
     */
    public function forPurchase(int $tenantId, array $data, Vendor $vendor, ?Bill $bill): array
    {
        $entered = isset($data['wht_amount']) && $data['wht_amount'] !== '' ? round((float) $data['wht_amount'], 2) : null;
        $categoryId = $data['wht_category_id'] ?? null;

        if (empty($categoryId) && ! $entered) {
            return $this->none();
        }

        $category = $categoryId ? WhtCategory::where('tenant_id', $tenantId)->find($categoryId) : $vendor->whtCategory;
        if (! $category) {
            throw ValidationException::withMessages(['wht_category_id' => 'Choose the WHT transaction type.']);
        }
        if ($vendor->wht_exempt) {
            throw ValidationException::withMessages(['wht_amount' => "{$vendor->name} is marked as exempt from withholding tax."]);
        }

        $net = round((float) $data['amount'], 2);
        $rate = $category->rateFor($vendor->payee_type ?? 'company', $vendor->hasTin());
        $share = self::exVatShare($bill);

        if ($entered === null) {
            if ($this->smallCompanyExempt($tenantId, $vendor, $net, $data['payment_date'] ?? null)) {
                return $this->none();
            }
            ['wht' => $wht, 'base' => $base] = self::grossUp($rate, $share, $net, $bill ? (float) $bill->balance_due : null);
        } else {
            $wht = $entered;
            $base = round(($net + $wht) * $share, 2);
        }

        if ($wht <= 0) {
            return $this->none();
        }
        if ($wht - $base > 0.005) {
            throw ValidationException::withMessages(['wht_amount' => 'The WHT is more than the amount it is worked out on.']);
        }

        $payeeType = $vendor->payee_type === 'individual' ? 'individual' : 'company';

        return [
            'wht_category_id' => $category->id,
            'wht_rate' => $rate,
            'wht_base' => $base,
            'wht_amount' => $wht,
            'wht_authority' => self::authorityFor($payeeType),
            'wht_state' => $payeeType === 'individual' ? ($vendor->state ?: null) : null,
        ];
    }

    /**
     * WHT a customer deducted from a payment to us. An amount entered is
     * taken as given (the customer decided it); left out, it is worked out
     * from the transaction type at the rate for this business.
     *
     * @param  array<string, mixed>  $data
     * @return array{wht_category_id: ?int, wht_rate: float, wht_base: float, wht_amount: float, wht_authority: ?string, wht_state: ?string}
     */
    public function forSale(int $tenantId, array $data, Customer $customer, ?Invoice $invoice): array
    {
        $entered = isset($data['wht_amount']) && $data['wht_amount'] !== '' ? round((float) $data['wht_amount'], 2) : null;
        $categoryId = $data['wht_category_id'] ?? null;

        if (empty($categoryId) && ! $entered) {
            return $this->none();
        }

        $category = $categoryId ? WhtCategory::where('tenant_id', $tenantId)->find($categoryId) : $customer->whtCategory;
        if ($categoryId && ! $category) {
            throw ValidationException::withMessages(['wht_category_id' => 'Choose the WHT transaction type.']);
        }

        $net = round((float) $data['amount'], 2);
        $settings = self::settings($tenantId);
        $tenantHasTin = trim((string) Tenant::find($tenantId)?->tax_number) !== '';
        $rate = $category ? $category->rateFor($settings['business_type'], $tenantHasTin) : 0.0;
        $share = self::exVatShare($invoice);

        if ($entered === null) {
            if ($customer->wht_exempt) {
                return $this->none();
            }
            ['wht' => $wht, 'base' => $base] = self::grossUp($rate, $share, $net, $invoice ? (float) $invoice->balance_due : null);
        } else {
            $wht = $entered;
            $base = round(($net + $wht) * $share, 2);
        }

        if ($wht <= 0) {
            return $this->none();
        }
        if ($wht - $base > 0.005) {
            throw ValidationException::withMessages(['wht_amount' => 'The WHT is more than the amount it is worked out on.']);
        }
        if (! $category) {
            $rate = $base > 0 ? round($wht / $base * 100, 2) : 0.0;
        }

        return [
            'wht_category_id' => $category?->id,
            'wht_rate' => $rate,
            'wht_base' => $base,
            'wht_amount' => $wht,
            'wht_authority' => self::authorityFor($settings['business_type']),
            'wht_state' => null,
        ];
    }

    /**
     * Small company exemption: this business is small, the vendor has a
     * TIN, and its payments this month (with this one) stay within the
     * threshold.
     */
    protected function smallCompanyExempt(int $tenantId, Vendor $vendor, float $net, mixed $date): bool
    {
        $settings = self::settings($tenantId);
        if (! $settings['small_company'] || ! $vendor->hasTin()) {
            return false;
        }

        $day = $date ? Carbon::parse($date) : now();
        $paid = (float) PaymentMade::where('tenant_id', $tenantId)
            ->where('vendor_id', $vendor->id)
            ->whereBetween('payment_date', [$day->copy()->startOfMonth()->toDateString(), $day->copy()->endOfMonth()->toDateString()])
            ->selectRaw('COALESCE(SUM(amount + wht_amount), 0) as total')
            ->value('total');

        return $paid + $net <= $settings['small_company_threshold'] + 0.005;
    }

    /** @return array{wht_category_id: null, wht_rate: float, wht_base: float, wht_amount: float, wht_authority: null, wht_state: null} */
    protected function none(): array
    {
        return ['wht_category_id' => null, 'wht_rate' => 0.0, 'wht_base' => 0.0, 'wht_amount' => 0.0, 'wht_authority' => null, 'wht_state' => null];
    }
}
