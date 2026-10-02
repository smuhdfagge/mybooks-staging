<?php

namespace App\Services\Payroll;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Payroll;
use App\Models\PensionFundAdministrator;
use App\Models\State;
use App\Models\StatutoryContribution;
use App\Models\StatutoryRemittance;
use App\Services\AccountCodeService;
use App\Services\JournalService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Remittance schedules for one pay month, built from approved and paid
 * payroll (pay period ending in the month):
 * - PAYE, grouped by the state whose IRS receives it;
 * - pension, grouped by PFA (employee and employer shares);
 * - NHF, NSITF and ITF.
 *
 * Each schedule is checked against the ledger: what payroll and any other
 * journals posted to its liability account in the month (remittances left
 * out) should equal the schedule total.
 */
class StatutoryScheduleService
{
    public const SCHEDULES = [
        'paye' => 'PAYE',
        'pension' => 'Pension',
        'nhf' => 'NHF',
        'nsitf' => 'NSITF',
        'itf' => 'ITF',
    ];

    /** The scheme setting (due date) behind each schedule. */
    public const SETTING = [
        'paye' => StatutoryLines::PAYE,
        'pension' => StatutoryLines::PENSION_EMPLOYEE,
        'nhf' => StatutoryLines::NHF,
        'nsitf' => StatutoryLines::NSITF,
        'itf' => StatutoryLines::ITF,
    ];

    /** @var array<int, Collection<int, Payroll>> */
    private array $payrolls = [];

    /**
     * @return array{
     *     schedule: string, title: string, month: Carbon, columns: array<string, string>,
     *     groups: array<string, array{key: string, label: string, rows: list<array<string, mixed>>, amount: float, remitted: float, outstanding: float, remittances: Collection<int, StatutoryRemittance>}>,
     *     total: float, remitted: float, outstanding: float, employees: int, gross: float,
     *     ledger: array{account_code: string, account_name: string, posted: float, difference: float, balance: float},
     *     due_date: Carbon, due_rule: string, extra: array<string, mixed>
     * }
     */
    public function build(int $tenantId, string $schedule, Carbon $month, bool $showFullNumbers = false): array
    {
        $month = $month->copy()->startOfMonth();
        $payrolls = $this->payrolls($tenantId, $month);
        $mask = fn (?string $v) => $showFullNumbers || $v === null || $v === '' ? $v : '****'.substr($v, -4);

        [$columns, $groups, $extra] = match ($schedule) {
            'paye' => $this->paye($payrolls, $mask),
            'pension' => $this->pension($payrolls, $mask),
            'nhf' => $this->nhf($payrolls, $mask),
            'nsitf' => $this->employerLevy($payrolls, StatutoryLines::NSITF),
            'itf' => array_replace($this->employerLevy($payrolls, StatutoryLines::ITF), [2 => $this->itfYearToDate($tenantId, $month)]),
            default => throw new \InvalidArgumentException("Unknown schedule {$schedule}"),
        };

        $total = round(array_sum(array_column($groups, 'amount')), 2);

        // What has been paid against each group (part payments allowed).
        $paid = StatutoryRemittance::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('schedule', $schedule)->whereDate('period', $month->toDateString())
            ->with(['journal' => fn ($q) => $q->withoutGlobalScopes()])
            ->orderBy('paid_on')->orderBy('id')->get();
        foreach ($groups as $key => $group) {
            $mine = $paid->where('group_key', (string) $key)->values();
            $groups[$key]['remittances'] = $mine;
            $groups[$key]['remitted'] = round((float) $mine->sum('amount'), 2);
            $groups[$key]['outstanding'] = round($group['amount'] - $groups[$key]['remitted'], 2);
        }
        $remitted = round((float) $paid->sum('amount'), 2);
        $setting = StatutoryContribution::forTenant($tenantId)[self::SETTING[$schedule]];
        $lastPay = $payrolls->max(fn (Payroll $p) => $p->pay_date ?? $p->pay_period_end);

        return [
            'schedule' => $schedule,
            'title' => self::SCHEDULES[$schedule].' schedule',
            'month' => $month,
            'columns' => $columns,
            'groups' => $groups,
            'total' => $total,
            'remitted' => $remitted,
            'outstanding' => round(max(0, $total - $remitted), 2),
            'employees' => (int) collect($groups)->flatMap(fn ($g) => array_column($g['rows'], 'employee_id'))->unique()->count(),
            'gross' => round((float) $payrolls->sum('gross_salary'), 2),
            'ledger' => $this->ledger($tenantId, $schedule, $month, $total),
            'due_date' => $setting->dueDate($month, $lastPay ? Carbon::parse($lastPay) : null),
            'due_rule' => $setting->dueRuleLabel(),
            'extra' => $extra,
        ];
    }

    /** @return Collection<int, Payroll> */
    public function payrolls(int $tenantId, Carbon $month): Collection
    {
        $key = $tenantId * 1000000 + (int) $month->format('Ym');

        return $this->payrolls[$key] ??= Payroll::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereIn('status', [Payroll::STATUS_APPROVED, Payroll::STATUS_PAID])
            // Half-open range: date columns can hold a time part (SQLite).
            ->where('pay_period_end', '>=', $month->copy()->startOfMonth()->toDateString())
            ->where('pay_period_end', '<', $month->copy()->startOfMonth()->addMonthNoOverflow()->toDateString())
            ->with(['employee' => fn ($q) => $q->withoutGlobalScopes()->withTrashed()])
            ->orderBy('id')
            ->get();
    }

    /**
     * Amount of a scheme on one payslip: employee lines from the deductions,
     * employer lines from the employer contributions.
     */
    public static function amount(Payroll $payroll, string $code): float
    {
        if ($code === StatutoryLines::PAYE) {
            return round((float) $payroll->tax_deduction, 2);
        }
        $employeeSide = in_array($code, [StatutoryLines::PENSION_EMPLOYEE, StatutoryLines::NHF], true);
        $lines = $employeeSide ? ($payroll->deduction_details ?? []) : ($payroll->employer_contribution_details ?? []);

        $sum = 0.0;
        foreach ($lines as $line) {
            if (str_starts_with((string) ($line['name'] ?? ''), '_')) {
                continue;
            }
            $lineCode = $employeeSide ? StatutoryLines::deduction($line) : StatutoryLines::contribution($line);
            if ($lineCode === $code) {
                $sum += (float) ($line['amount'] ?? 0);
            }
        }

        return round($sum, 2);
    }

    /** @return array{0: array<string, string>, 1: array<string, mixed>, 2: array<string, mixed>} */
    private function paye(Collection $payrolls, \Closure $mask): array
    {
        $states = State::whereIn('id', $payrolls->map(fn (Payroll $p) => $p->tax_state_id ?? $p->employee?->tax_state_id)->filter()->unique())
            ->pluck('name', 'id');

        $groups = [];
        foreach ($payrolls as $p) {
            $stateId = $p->tax_state_id ?? $p->employee?->tax_state_id;
            $key = $stateId ? (string) $stateId : 'none';
            $groups[$key] ??= ['key' => $key, 'label' => $stateId ? ($states[$stateId] ?? 'Unknown state') : 'State not set', 'rows' => [], 'amount' => 0.0];
            $paye = self::amount($p, StatutoryLines::PAYE);
            $groups[$key]['rows'][] = [
                'employee_id' => $p->employee_id,
                'employee' => $p->employee?->full_name,
                'staff_no' => $p->employee?->employee_id,
                'tin' => $mask($p->employee?->tax_id),
                'gross' => round((float) $p->gross_salary, 2),
                'taxable' => round((float) ($p->taxable_income ?? $this->taxableFallback($p)), 2),
                'amount' => $paye,
            ];
            $groups[$key]['amount'] = round($groups[$key]['amount'] + $paye, 2);
        }

        return [['employee' => 'Employee', 'staff_no' => 'Staff no.', 'tin' => 'TIN', 'gross' => 'Gross pay', 'taxable' => 'Taxable pay', 'amount' => 'PAYE'], $this->labelSort($groups), []];
    }

    /** @return array{0: array<string, string>, 1: array<string, mixed>, 2: array<string, mixed>} */
    private function pension(Collection $payrolls, \Closure $mask): array
    {
        $pfas = PensionFundAdministrator::whereIn('id', $payrolls->map(fn (Payroll $p) => $p->pension_fund_administrator_id ?? $p->employee?->pension_fund_administrator_id)->filter()->unique())
            ->pluck('name', 'id');

        $groups = [];
        foreach ($payrolls as $p) {
            $employee = self::amount($p, StatutoryLines::PENSION_EMPLOYEE);
            $employer = self::amount($p, StatutoryLines::PENSION_EMPLOYER);
            if ($employee + $employer <= 0) {
                continue;
            }
            $pfaId = $p->pension_fund_administrator_id ?? $p->employee?->pension_fund_administrator_id;
            $key = $pfaId ? (string) $pfaId : 'none';
            $groups[$key] ??= ['key' => $key, 'label' => $pfaId ? ($pfas[$pfaId] ?? 'Unknown PFA') : 'PFA not set', 'rows' => [], 'amount' => 0.0];
            $groups[$key]['rows'][] = [
                'employee_id' => $p->employee_id,
                'employee' => $p->employee?->full_name,
                'staff_no' => $p->employee?->employee_id,
                'rsa_pin' => $mask($p->employee?->rsa_pin),
                'employee_share' => $employee,
                'employer_share' => $employer,
                'amount' => round($employee + $employer, 2),
            ];
            $groups[$key]['amount'] = round($groups[$key]['amount'] + $employee + $employer, 2);
        }

        return [['employee' => 'Employee', 'staff_no' => 'Staff no.', 'rsa_pin' => 'RSA PIN', 'employee_share' => 'Employee', 'employer_share' => 'Employer', 'amount' => 'Total'], $this->labelSort($groups), []];
    }

    /** @return array{0: array<string, string>, 1: array<string, mixed>, 2: array<string, mixed>} */
    private function nhf(Collection $payrolls, \Closure $mask): array
    {
        $group = ['key' => 'all', 'label' => 'Federal Mortgage Bank of Nigeria (NHF)', 'rows' => [], 'amount' => 0.0];
        foreach ($payrolls as $p) {
            $nhf = self::amount($p, StatutoryLines::NHF);
            if ($nhf <= 0) {
                continue;
            }
            $group['rows'][] = [
                'employee_id' => $p->employee_id,
                'employee' => $p->employee?->full_name,
                'staff_no' => $p->employee?->employee_id,
                'nhf_number' => $mask($p->employee?->nhf_number),
                'basic' => round((float) $p->basic_salary, 2),
                'amount' => $nhf,
            ];
            $group['amount'] = round($group['amount'] + $nhf, 2);
        }

        return [['employee' => 'Employee', 'staff_no' => 'Staff no.', 'nhf_number' => 'NHF number', 'basic' => 'Basic salary', 'amount' => 'NHF'], ['all' => $group], []];
    }

    /** NSITF or ITF: employer levies on payroll. @return array{0: array<string, string>, 1: array<string, mixed>, 2: array<string, mixed>} */
    private function employerLevy(Collection $payrolls, string $code): array
    {
        $label = $code === StatutoryLines::NSITF ? 'Nigeria Social Insurance Trust Fund' : 'Industrial Training Fund';
        $group = ['key' => 'all', 'label' => $label, 'rows' => [], 'amount' => 0.0];
        foreach ($payrolls as $p) {
            $amount = self::amount($p, $code);
            if ($amount <= 0) {
                continue;
            }
            $group['rows'][] = [
                'employee_id' => $p->employee_id,
                'employee' => $p->employee?->full_name,
                'staff_no' => $p->employee?->employee_id,
                'gross' => round((float) $p->gross_salary, 2),
                'amount' => $amount,
            ];
            $group['amount'] = round($group['amount'] + $amount, 2);
        }

        return [['employee' => 'Employee', 'staff_no' => 'Staff no.', 'gross' => 'Gross pay', 'amount' => 'Contribution'], ['all' => $group], []];
    }

    /**
     * ITF is paid once a year: the year so far (January to this month).
     *
     * @return array{year_to_date: float, year_payroll: float}
     */
    private function itfYearToDate(int $tenantId, Carbon $month): array
    {
        $ytd = 0.0;
        $gross = 0.0;
        for ($m = $month->copy()->startOfYear(); $m->lte($month); $m->addMonth()) {
            foreach ($this->payrolls($tenantId, $m) as $p) {
                $ytd += self::amount($p, StatutoryLines::ITF);
                $gross += (float) $p->gross_salary;
            }
        }

        return ['year_to_date' => round($ytd, 2), 'year_payroll' => round($gross, 2)];
    }

    /**
     * Ledger check: net credit to the scheme's liability account from
     * journals dated in the month (payroll and manual), leaving out
     * remittances and reversal entries; the account balance today.
     *
     * @return array{account_code: string, account_name: string, posted: float, difference: float, balance: float}
     */
    public function ledger(int $tenantId, string $schedule, Carbon $month, float $scheduleTotal): array
    {
        $code = AccountCodeService::resolve($tenantId, StatutoryLines::liabilityKey(self::SETTING[$schedule]));
        $account = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('account_code', $code)->first();

        $posted = 0.0;
        if ($account) {
            $entries = JournalEntry::query()
                ->join('journals', 'journals.id', '=', 'journal_entries.journal_id')
                ->where('journal_entries.account_id', $account->id)
                ->where('journals.tenant_id', $tenantId)
                ->where('journals.status', 'posted')
                ->whereNull('journals.deleted_at')
                ->where('journals.journal_date', '>=', $month->copy()->startOfMonth()->toDateString())
                ->where('journals.journal_date', '<', $month->copy()->startOfMonth()->addMonthNoOverflow()->toDateString())
                ->where(fn ($q) => $q->whereNull('journals.journal_type')->orWhere('journals.journal_type', '!=', JournalService::PAYROLL_REMITTANCE))
                ->where(fn ($q) => $q->whereNull('journals.reference')->orWhere('journals.reference', 'not like', 'REV-%'));
            $posted = round((float) (clone $entries)->sum('journal_entries.credit') - (float) (clone $entries)->sum('journal_entries.debit'), 2);
        }

        return [
            'account_code' => $code,
            'account_name' => $account->name ?? $code,
            'posted' => $posted,
            'difference' => round($scheduleTotal - $posted, 2),
            'balance' => round((float) ($account->current_balance ?? 0), 2),
        ];
    }

    /** Taxable pay for payroll made before it was stored: gross less pre-tax deductions. */
    private function taxableFallback(Payroll $p): float
    {
        $taxable = (float) $p->gross_salary;
        foreach ($p->deduction_details ?? [] as $line) {
            if (! empty($line['pre_tax'])) {
                $taxable -= (float) ($line['amount'] ?? 0);
            }
        }

        return max(0, $taxable);
    }

    /**
     * Groups in name order, "not set" last.
     *
     * @param  array<string, array<string, mixed>>  $groups
     * @return array<string, array<string, mixed>>
     */
    private function labelSort(array $groups): array
    {
        uasort($groups, fn ($a, $b) => [$a['key'] === 'none', $a['label']] <=> [$b['key'] === 'none', $b['label']]);

        return $groups;
    }
}
