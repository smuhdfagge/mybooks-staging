<?php

namespace App\Services\Accounting;

use App\Models\PaymentMade;
use App\Models\StatutoryRemittance;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The monthly schedule of WHT we deducted from vendors, grouped by the
 * authority it is owed to (the NRS for companies; each state IRS for
 * individuals), with each vendor's TIN, what has been remitted for the
 * month and what is still to pay.
 */
class WhtSchedule
{
    /**
     * @return array{
     *     month: Carbon,
     *     groups: array<string, array{key: string, authority: string, state: ?string, label: string, due: Carbon,
     *         rows: Collection<int, PaymentMade>, vendors: Collection<int, array{name: string, tin: ?string, address: string, payments: int, base: float, wht: float}>,
     *         deducted: float, remitted: float, outstanding: float, remittances: Collection<int, StatutoryRemittance>}>,
     *     totals: array{deducted: float, remitted: float, outstanding: float}
     * }
     */
    public function build(int $tenantId, Carbon $month): array
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        // A deleted vendor's WHT still has to be paid over.
        $payments = PaymentMade::with(['vendor' => fn ($q) => $q->withTrashed(), 'bill', 'whtCategory'])
            ->where('tenant_id', $tenantId)
            ->where('wht_amount', '>', 0)
            ->whereDate('payment_date', '>=', $from->toDateString())
            ->whereDate('payment_date', '<=', $to->toDateString())
            ->orderBy('payment_date')->orderBy('id')
            ->get();

        $remittances = StatutoryRemittance::with('bank')
            ->where('tenant_id', $tenantId)
            ->where('body', StatutoryRemittance::BODY_WHT)
            ->whereDate('period_start', $from->toDateString())
            ->orderBy('paid_on')->orderBy('id')
            ->get();

        $groups = [];
        foreach ($payments as $payment) {
            $key = self::key((string) $payment->wht_authority, $payment->wht_state);
            $groups[$key] ??= $this->group((string) $payment->wht_authority, $payment->wht_state, $month);
            $groups[$key]['rows']->push($payment);
        }
        foreach ($remittances as $remittance) {
            $key = self::key((string) $remittance->wht_authority, $remittance->wht_state);
            $groups[$key] ??= $this->group((string) $remittance->wht_authority, $remittance->wht_state, $month);
            $groups[$key]['remittances']->push($remittance);
        }

        foreach ($groups as &$group) {
            $group['vendors'] = $group['rows']->groupBy('vendor_id')->map(function (Collection $rows) {
                $vendor = $rows->first()->vendor;

                return [
                    'name' => (string) $vendor?->name,
                    'tin' => $vendor?->tax_number,
                    'address' => collect([$vendor?->address, $vendor?->city, $vendor?->state])->filter()->implode(', '),
                    'payments' => $rows->count(),
                    'base' => round((float) $rows->sum('wht_base'), 2),
                    'wht' => round((float) $rows->sum('wht_amount'), 2),
                ];
            })->sortBy('name')->values();
            $group['deducted'] = round((float) $group['rows']->sum('wht_amount'), 2);
            $group['remitted'] = round((float) $group['remittances']->sum('amount'), 2);
            $group['outstanding'] = round($group['deducted'] - $group['remitted'], 2);
        }
        unset($group);

        // The NRS first, then states by name.
        uksort($groups, fn ($a, $b) => [$a !== WithholdingTax::AUTHORITY_FEDERAL, $a] <=> [$b !== WithholdingTax::AUTHORITY_FEDERAL, $b]);

        $sum = fn (string $field) => round(array_sum(array_column($groups, $field)), 2);

        return [
            'month' => $from,
            'groups' => $groups,
            'totals' => ['deducted' => $sum('deducted'), 'remitted' => $sum('remitted'), 'outstanding' => $sum('outstanding')],
        ];
    }

    /** One key per authority: "nrs", or "state:<state>" for a state IRS. */
    public static function key(string $authority, ?string $state): string
    {
        return $authority === WithholdingTax::AUTHORITY_STATE ? 'state:'.trim((string) $state) : WithholdingTax::AUTHORITY_FEDERAL;
    }

    /** @return array{key: string, authority: string, state: ?string, label: string, due: Carbon, rows: Collection<int, PaymentMade>, vendors: Collection<int, array{name: string, tin: ?string, address: string, payments: int, base: float, wht: float}>, deducted: float, remitted: float, outstanding: float, remittances: Collection<int, StatutoryRemittance>} */
    private function group(string $authority, ?string $state, Carbon $month): array
    {
        $authority = $authority === WithholdingTax::AUTHORITY_STATE ? WithholdingTax::AUTHORITY_STATE : WithholdingTax::AUTHORITY_FEDERAL;
        $state = $authority === WithholdingTax::AUTHORITY_STATE ? (trim((string) $state) ?: null) : null;

        return [
            'key' => self::key($authority, $state),
            'authority' => $authority,
            'state' => $state,
            'label' => WithholdingTax::authorityLabel($authority, $state),
            'due' => WithholdingTax::dueDate($authority, $month),
            'rows' => collect(),
            'vendors' => collect(),
            'deducted' => 0.0,
            'remitted' => 0.0,
            'outstanding' => 0.0,
            'remittances' => collect(),
        ];
    }
}
