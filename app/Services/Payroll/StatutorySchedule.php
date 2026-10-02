<?php

namespace App\Services\Payroll;

use App\Models\Payroll;
use App\Models\StatutoryRemittance;
use App\Models\Tenant;
use App\Services\PayrollTaxService;
use App\Support\Money;
use App\Support\PayrollStatutory;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Remittance schedules for payroll contributions (tax pack 1): PAYE by
 * state, pension by PFA (with RSA PINs), NHF, NSITF and ITF. Built from
 * approved and paid payslips whose pay period ends in the range, so they
 * match what the payroll journals put into each liability.
 */
class StatutorySchedule
{
    public const COLUMNS = [
        'paye' => ['employee_no' => 'Staff no.', 'name' => 'Employee', 'tin' => 'Tax ID (TIN)', 'gross' => 'Gross pay', 'amount' => 'PAYE deducted'],
        'pension' => ['employee_no' => 'Staff no.', 'name' => 'Employee', 'rsa_pin' => 'RSA PIN', 'pensionable' => 'Pensionable pay', 'employee' => 'Employee share', 'employer' => 'Employer share', 'amount' => 'Total'],
        'nhf' => ['employee_no' => 'Staff no.', 'name' => 'Employee', 'nhf_number' => 'NHF number', 'basic' => 'Basic salary', 'amount' => 'NHF deducted'],
        'nsitf' => ['employee_no' => 'Staff no.', 'name' => 'Employee', 'gross' => 'Gross pay', 'amount' => 'NSITF'],
        'itf' => ['employee_no' => 'Staff no.', 'name' => 'Employee', 'gross' => 'Gross pay', 'amount' => 'ITF'],
    ];

    public const MONEY = ['gross', 'pensionable', 'employee', 'employer', 'basic', 'amount'];

    public const GROUP_LABEL = ['paye' => 'State', 'pension' => 'PFA'];

    /** @return Collection<int, Payroll> */
    public function payrolls(int $tenantId, string $from, string $to): Collection
    {
        return Payroll::where('tenant_id', $tenantId)
            ->whereIn('status', [Payroll::STATUS_APPROVED, Payroll::STATUS_PAID])
            ->where('pay_period_end', '>=', $from)
            ->where('pay_period_end', '<', Carbon::parse($to)->addDay()->toDateString())
            ->with(['employee' => fn ($q) => $q->withTrashed()])
            ->orderBy('id')
            ->get();
    }

    /**
     * One body's schedule: rows per employee, grouped by state or PFA.
     *
     * @return array{body: string, title: string, from: string, to: string, group_label: ?string,
     *               columns: array<string, string>, groups: array<string, array{rows: array, totals: array}>, totals: array<string, float>, total: float}
     */
    public function build(int $tenantId, string $body, string $from, string $to): array
    {
        $tenant = Tenant::find($tenantId);
        $rows = [];

        foreach ($this->payrolls($tenantId, $from, $to) as $payroll) {
            $figures = $this->figures($payroll, $tenant);
            $amount = $figures[$body]['amount'];
            if ($amount == 0.0) {
                continue;
            }

            $group = match ($body) {
                'paye' => $figures['state'] ?: 'State not set',
                'pension' => $figures['pfa'] ?: 'PFA not set',
                default => '',
            };
            $key = $group.'|'.$payroll->employee_id;
            $row = $rows[$key] ?? [
                'group' => $group,
                'employee_no' => $payroll->employee?->employee_id,
                'name' => $payroll->employee->full_name ?? 'Employee #'.$payroll->employee_id,
                'tin' => $payroll->employee?->tax_id,
                'rsa_pin' => $figures['rsa_pin'],
                'nhf_number' => $figures['nhf_number'],
                'gross' => 0.0, 'pensionable' => 0.0, 'basic' => 0.0, 'employee' => 0.0, 'employer' => 0.0, 'amount' => 0.0,
            ];
            $row['gross'] = Money::add($row['gross'], $payroll->gross_salary);
            $row['basic'] = Money::add($row['basic'], $payroll->basic_salary);
            $row['pensionable'] = Money::add($row['pensionable'], $figures['pensionable']);
            $row['employee'] = Money::add($row['employee'], $figures[$body]['employee'] ?? 0);
            $row['employer'] = Money::add($row['employer'], $figures[$body]['employer'] ?? 0);
            $row['amount'] = Money::add($row['amount'], $amount);
            $rows[$key] = $row;
        }

        $groups = [];
        foreach (collect($rows)->sortBy(fn ($r) => $r['group'].'|'.$r['name']) as $row) {
            $groups[$row['group']]['rows'][] = $row;
        }
        foreach ($groups as $label => $group) {
            $groups[$label]['totals'] = $this->totals($group['rows']);
        }
        $totals = $this->totals(array_values($rows));

        return [
            'body' => $body,
            'title' => PayrollStatutory::LABELS[$body],
            'from' => $from,
            'to' => $to,
            'group_label' => self::GROUP_LABEL[$body] ?? null,
            'columns' => self::COLUMNS[$body],
            'groups' => $groups,
            'totals' => $totals,
            'total' => $totals['amount'] ?? 0.0,
        ];
    }

    /**
     * Each body's amounts from one payslip. Payslips made before the tax
     * pack have no snapshot; the employee's current details are used.
     *
     * @return array<string, mixed>
     */
    public function figures(Payroll $payroll, ?Tenant $tenant): array
    {
        $snap = (array) ($payroll->statutory ?? []);
        $employee = $payroll->employee;

        $sum = function (array $lines, string $body): float {
            return Money::sum(array_map(
                fn ($l) => empty($l['_loan_id']) && PayrollStatutory::classify((string) ($l['name'] ?? '')) === $body ? (float) ($l['amount'] ?? 0) : 0,
                $lines
            ));
        };
        $deductions = array_filter((array) ($payroll->deduction_details ?? []), fn ($d) => ! str_starts_with((string) ($d['name'] ?? ''), '_'));
        $contributions = (array) ($payroll->employer_contribution_details ?? []);

        $pensionEmployee = $sum($deductions, 'pension');
        $pensionEmployer = $sum($contributions, 'pension');

        return [
            'state' => $snap['tax_state'] ?? ($employee?->tax_state ?: ($employee?->state ?: $tenant?->state)),
            'pfa' => $snap['pfa_name'] ?? $employee?->pfa_name,
            'rsa_pin' => $snap['rsa_pin'] ?? $employee?->rsa_pin,
            'nhf_number' => $snap['nhf_number'] ?? $employee?->nhf_number,
            'pensionable' => (float) ($snap['pensionable_pay']
                ?? app(PayrollTaxService::class)->pensionablePay((float) $payroll->basic_salary, (array) ($payroll->allowance_details ?? []))),
            'paye' => ['amount' => Money::round($payroll->tax_deduction)],
            'pension' => ['employee' => $pensionEmployee, 'employer' => $pensionEmployer, 'amount' => Money::add($pensionEmployee, $pensionEmployer)],
            'nhf' => ['amount' => $sum($deductions, 'nhf')],
            'nsitf' => ['amount' => $sum($contributions, 'nsitf')],
            'itf' => ['amount' => $sum($contributions, 'itf')],
        ];
    }

    /**
     * The month's position per body: owed from payroll, paid, still to
     * pay, and when it is due. ITF is yearly, so it shows the year so far.
     *
     * @return Collection<int, StatutoryDue>
     */
    public function summary(int $tenantId, Carbon $month): Collection
    {
        return collect(PayrollStatutory::BODIES)->map(function ($body) use ($tenantId, $month) {
            [$from, $to] = $this->period($body, $month);
            $due = $this->build($tenantId, $body, $from->toDateString(), $to->toDateString())['total'];
            $paid = Money::round(StatutoryRemittance::where('tenant_id', $tenantId)
                ->where('body', $body)
                ->where('period_start', '>=', $from->toDateString())
                ->where('period_end', '<', ($body === 'itf' ? $to->copy()->endOfYear() : $to)->copy()->addDay()->toDateString())
                ->sum('amount'));

            return new StatutoryDue(
                body: $body,
                label: PayrollStatutory::LABELS[$body],
                from: $from,
                to: $to,
                due: $due,
                paid: $paid,
                outstanding: Money::subtract($due, $paid),
                due_date: PayrollStatutory::dueDate($body, $to->copy()->endOfMonth()),
                due_rule: PayrollStatutory::DUE_RULES[$body],
            );
        });
    }

    /**
     * The period a remittance or schedule covers: the month, or for ITF
     * the year up to the end of that month.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function period(string $body, Carbon $month): array
    {
        return $body === 'itf'
            ? [$month->copy()->startOfYear(), $month->copy()->endOfMonth()]
            : [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, float>
     */
    private function totals(array $rows): array
    {
        $totals = [];
        foreach (self::MONEY as $col) {
            $totals[$col] = Money::sum(array_column($rows, $col));
        }

        return $totals;
    }
}
