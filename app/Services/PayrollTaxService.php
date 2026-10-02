<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\TaxBracket;
use App\Models\Tenant;
use App\Support\Money;
use App\Support\PayrollStatutory;
use Illuminate\Support\Collection;

class PayrollTaxService
{
    /**
     * Calculate progressive tax based on tenant's tax brackets.
     *
     * Falls back to flat rate if no brackets are configured.
     *
     * @param  float  $taxableIncome  The taxable income amount
     * @param  int  $tenantId  The tenant ID for bracket lookup
     * @param  float  $flatRate  Fallback flat tax rate (percentage) when no brackets exist
     * @param  string  $period  'monthly' or 'annual'
     * @return array{tax: float, breakdown: array, method: string}
     */
    public function calculateTax(float $taxableIncome, int $tenantId, float $flatRate = 0, string $period = 'monthly'): array
    {
        if ($taxableIncome <= 0) {
            return ['tax' => 0, 'breakdown' => [], 'method' => 'none'];
        }

        $brackets = $this->activeBrackets($tenantId, $period);

        // Most statutory tables are annual (Nigeria, South Africa, UK) while
        // payroll runs monthly. Use the other period's brackets and convert:
        // annualise the pay, apply the bands, then take the monthly share.
        if ($brackets->isEmpty()) {
            $otherPeriod = $period === 'monthly' ? 'annual' : 'monthly';
            $brackets = $this->activeBrackets($tenantId, $otherPeriod);

            if ($brackets->isNotEmpty()) {
                $factor = $period === 'monthly' ? 12 : 1 / 12;
                $result = $this->calculateProgressiveTax($taxableIncome * $factor, $brackets);
                $result['tax'] = round($result['tax'] / $factor, 2);
                $result['period_converted_from'] = $otherPeriod;

                return $result;
            }
        }

        if ($brackets->isEmpty()) {
            // Fallback: use flat rate
            $tax = $flatRate > 0 ? round($taxableIncome * $flatRate / 100, 2) : 0;

            return [
                'tax' => $tax,
                'breakdown' => $tax > 0 ? [['bracket' => 'Flat Rate', 'rate' => $flatRate, 'taxable' => $taxableIncome, 'tax' => $tax]] : [],
                'method' => 'flat',
            ];
        }

        return $this->calculateProgressiveTax($taxableIncome, $brackets);
    }

    /** @return Collection<int, TaxBracket> */
    protected function activeBrackets(int $tenantId, string $period)
    {
        return TaxBracket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('period', $period)
            ->orderBy('sort_order')
            ->orderBy('min_amount')
            ->get();
    }

    /**
     * Calculate tax using progressive/slab brackets.
     *
     * Each bracket applies its rate only to income within its min–max range.
     * The fixed_amount field allows modelling brackets that use a flat base
     * plus marginal rate on excess (common in many jurisdictions).
     */
    protected function calculateProgressiveTax(float $taxableIncome, $brackets): array
    {
        $totalTax = 0;
        $breakdown = [];
        $remainingIncome = $taxableIncome;

        foreach ($brackets as $bracket) {
            if ($remainingIncome <= 0) {
                break;
            }

            $min = (float) $bracket->min_amount;
            $max = $bracket->max_amount !== null ? (float) $bracket->max_amount : PHP_FLOAT_MAX;

            // Skip brackets whose range is below the already-taxed portions
            if ($taxableIncome <= $min) {
                break;
            }

            // Calculate the portion of income that falls in this bracket
            $bracketWidth = $max - $min;
            $taxableInBracket = min($remainingIncome, $bracketWidth);

            // Apply this bracket's rate
            $bracketTax = round($taxableInBracket * (float) $bracket->rate / 100, 2);

            // Add any fixed amount for this bracket (only if income reaches this bracket)
            if ($taxableIncome > $min && (float) $bracket->fixed_amount > 0) {
                $bracketTax += (float) $bracket->fixed_amount;
            }

            $totalTax += $bracketTax;
            $remainingIncome -= $taxableInBracket;

            $breakdown[] = [
                'bracket' => $bracket->name,
                'min' => $min,
                'max' => $bracket->max_amount,
                'rate' => (float) $bracket->rate,
                'fixed_amount' => (float) $bracket->fixed_amount,
                'taxable' => $taxableInBracket,
                'tax' => $bracketTax,
            ];
        }

        return [
            'tax' => round($totalTax, 2),
            'breakdown' => $breakdown,
            'method' => 'progressive',
        ];
    }

    /** Nigeria Tax Act 2025: 20% of annual rent, at most 500,000 a year. */
    public const RENT_RELIEF_RATE = 0.20;

    public const RENT_RELIEF_CAP = 500000;

    /**
     * Monthly rent relief for an employee of a Nigerian business: taken off
     * pay before PAYE, like pension and NHF. Zero elsewhere or with no rent.
     */
    public function monthlyRentRelief(Employee $employee): float
    {
        $rent = (float) ($employee->annual_rent ?? 0);
        $country = strtoupper(trim((string) Tenant::whereKey($employee->tenant_id)->value('country')));
        if ($rent <= 0 || ! in_array($country, ['NG', 'NGA', 'NIGERIA'], true)) {
            return 0.0;
        }

        return round(min($rent * self::RENT_RELIEF_RATE, self::RENT_RELIEF_CAP) / 12, 2);
    }

    /**
     * One employee's payslip figures for a pay period ending $periodEnd:
     * allowances, pre-tax reliefs (pension, NHF, rent), PAYE, other
     * deductions and loan instalments. The payroll run and bulk create both
     * use this, so a payslip is worked out the same way whichever screen
     * made it (P-payroll). With no salary structure, the employee's basic
     * salary is used with no allowances or structure deductions.
     *
     * @param  array<int, array<string, mixed>>  $employerContributionRules
     * @return array<string, mixed> Payroll attributes
     */
    public function payslipFigures(Employee $employee, int $tenantId, float $flatTaxRate, array $employerContributionRules, string $periodEnd): array
    {
        $structure = $employee->salaryStructure;

        $basicSalary = $structure ? $structure->basic_salary : (float) ($employee->salary ?? 0);
        $allowanceDetails = [];
        $totalAllowances = 0;

        foreach ($structure ? $structure->allowances : [] as $item) {
            $calculated = $item->amount_type === 'percentage'
                ? round($basicSalary * $item->amount / 100, 2)
                : $item->amount;
            $allowanceDetails[] = [
                'name' => $item->name,
                'amount_type' => $item->amount_type,
                'rate' => $item->amount,
                'amount' => $calculated,
                'is_taxable' => $item->is_taxable,
            ];
            $totalAllowances += $calculated;
        }

        $grossSalary = $basicSalary + $totalAllowances;

        // Calculate taxable amount (exclude non-taxable allowances)
        $taxableAmount = $grossSalary;
        foreach ($allowanceDetails as $ad) {
            if (! $ad['is_taxable']) {
                $taxableAmount -= $ad['amount'];
            }
        }

        $deductionDetails = [];
        $totalOtherDeductions = 0;

        foreach ($structure ? $structure->deductions : [] as $item) {
            $calculated = $item->amount_type === 'percentage'
                ? round($grossSalary * $item->amount / 100, 2)
                : $item->amount;
            $deductionDetails[] = [
                'name' => $item->name,
                'amount_type' => $item->amount_type,
                'rate' => $item->amount,
                'amount' => $calculated,
                'pre_tax' => (bool) $item->is_taxable,
            ];
            $totalOtherDeductions += $calculated;

            // Pre-tax deductions (pension, NHF, health insurance) are reliefs.
            if ($item->is_taxable) {
                $taxableAmount -= $calculated;
            }
        }

        // Statutory pension and NHF (tax pack 1), unless the salary structure
        // already deducts them. Both are reliefs, so they come off before tax.
        $tenant = Tenant::find($tenantId);
        $statutory = PayrollStatutory::active($tenant) ? PayrollStatutory::settings($tenant) : null;
        $pensionablePay = $this->pensionablePay($basicSalary, $allowanceDetails);
        if ($statutory) {
            $already = fn (string $body) => collect($deductionDetails)->contains(fn ($d) => PayrollStatutory::classify($d['name']) === $body);
            $add = [];
            if ($statutory['pension_applies'] && ! $already('pension')) {
                $add[] = ['pension', 'Pension (employee '.(float) $statutory['pension_employee_rate'].'%)', $statutory['pension_employee_rate'], Money::percent($pensionablePay, $statutory['pension_employee_rate'])];
            }
            if ($employee->nhf_registered && ! $already('nhf')) {
                $add[] = ['nhf', 'NHF ('.(float) $statutory['nhf_rate'].'% of basic)', $statutory['nhf_rate'], Money::percent($basicSalary, $statutory['nhf_rate'])];
            }
            foreach ($add as [$body, $name, $rate, $amount]) {
                if ($amount <= 0) {
                    continue;
                }
                $deductionDetails[] = ['name' => $name, 'amount_type' => 'percentage', 'rate' => (float) $rate, 'amount' => $amount, 'pre_tax' => true, '_statutory' => $body];
                $totalOtherDeductions += $amount;
                $taxableAmount -= $amount;
            }
        }

        // Rent relief (Nigeria Tax Act 2025) also comes off before tax.
        $taxableAmount -= $this->monthlyRentRelief($employee);

        // Progressive tax calculation with flat-rate fallback
        $taxResult = $this->calculateTax(max(0, $taxableAmount), $tenantId, $flatTaxRate, 'monthly');
        $taxDeduction = $taxResult['tax'];

        // Include active loan/advance deductions
        $activeLoans = EmployeeLoan::getActiveDeductionsForEmployee($employee->id, $periodEnd);
        foreach ($activeLoans as $loan) {
            /** @var EmployeeLoan $loan */
            $loanAmount = min((float) $loan->installment_amount, (float) $loan->outstanding_balance);
            if ($loanAmount > 0) {
                $deductionDetails[] = [
                    'name' => ucfirst($loan->type).': '.($loan->description ?: $loan->loan_number),
                    'amount_type' => 'fixed',
                    'rate' => $loanAmount,
                    'amount' => $loanAmount,
                    '_loan_id' => $loan->id,
                ];
                $totalOtherDeductions += $loanAmount;
            }
        }

        $totalDeductions = $taxDeduction + $totalOtherDeductions;
        $netSalary = $grossSalary - $totalDeductions;

        // Employer contributions (not deducted from employee)
        $employerResult = $this->calculateEmployerContributions($grossSalary, $employerContributionRules);
        if ($statutory) {
            $employerResult = $this->addStatutoryEmployerContributions($employerResult, $statutory, $grossSalary, $pensionablePay);
        }

        return [
            'salary_structure_id' => $structure?->id,
            'salary_structure_snapshot' => $structure?->toSnapshot(),
            'basic_salary' => $basicSalary,
            'allowances' => $totalAllowances,
            'allowance_details' => $allowanceDetails,
            'overtime_hours' => 0,
            'overtime_amount' => 0,
            'gross_salary' => $grossSalary,
            'tax_deduction' => $taxDeduction,
            'other_deductions' => $totalOtherDeductions,
            'deduction_details' => array_merge($deductionDetails, [
                ['name' => '_tax_method', 'method' => $taxResult['method'], 'breakdown' => $taxResult['breakdown']],
            ]),
            'employer_contributions' => $employerResult['total'],
            'employer_contribution_details' => $employerResult['details'],
            'total_deductions' => $totalDeductions,
            'net_salary' => $netSalary,
            'statutory' => $this->statutorySnapshot($employee, $tenant, $pensionablePay),
        ];
    }

    /**
     * Pensionable pay (Pension Reform Act 2014 s.4): basic salary plus
     * housing and transport allowances.
     *
     * @param  array<int, array<string, mixed>>  $allowanceDetails
     */
    public function pensionablePay(float $basicSalary, array $allowanceDetails): float
    {
        return Money::add($basicSalary, ...array_map(
            fn ($a) => PayrollStatutory::isPensionableAllowance((string) ($a['name'] ?? '')) ? (float) ($a['amount'] ?? 0) : 0,
            $allowanceDetails
        ));
    }

    /**
     * Employer pension, NSITF and ITF (tax pack 1), each skipped when the
     * payroll run was given its own rule for it.
     *
     * @param  array{total: float, details: array}  $result
     * @param  array<string, mixed>  $settings
     * @return array{total: float, details: array}
     */
    protected function addStatutoryEmployerContributions(array $result, array $settings, float $grossSalary, float $pensionablePay): array
    {
        $given = collect($result['details'])->map(fn ($d) => PayrollStatutory::classify((string) ($d['name'] ?? '')))->filter()->all();
        $items = [
            'pension' => [$settings['pension_applies'], 'Pension (employer '.(float) $settings['pension_employer_rate'].'%)', $settings['pension_employer_rate'], $pensionablePay],
            'nsitf' => [$settings['nsitf_applies'], 'NSITF ('.(float) $settings['nsitf_rate'].'% of payroll)', $settings['nsitf_rate'], $grossSalary],
            'itf' => [$settings['itf_applies'], 'ITF ('.(float) $settings['itf_rate'].'% of payroll)', $settings['itf_rate'], $grossSalary],
        ];

        foreach ($items as $body => [$applies, $name, $rate, $base]) {
            $amount = Money::percent($base, $rate);
            if (! $applies || in_array($body, $given, true) || $amount <= 0) {
                continue;
            }
            $result['details'][] = ['name' => $name, 'type' => 'percentage', 'rate' => (float) $rate, 'cap' => null, 'amount' => $amount, '_statutory' => $body];
            $result['total'] = Money::add($result['total'], $amount);
        }

        return $result;
    }

    /**
     * Who the contributions go to, as at this payslip: PAYE state (the
     * employee's state of residence, else their address state, else the
     * business's state), PFA, RSA PIN and NHF number.
     *
     * @return array<string, mixed>
     */
    public function statutorySnapshot(Employee $employee, ?Tenant $tenant, float $pensionablePay): array
    {
        return [
            'tax_state' => $employee->tax_state ?: ($employee->state ?: $tenant?->state),
            'pfa_name' => $employee->pfa_name,
            'rsa_pin' => $employee->rsa_pin,
            'nhf_number' => $employee->nhf_number,
            'pensionable_pay' => $pensionablePay,
        ];
    }

    /**
     * Calculate employer contributions based on tenant configuration.
     *
     * Employer contributions are costs borne by the employer (not deducted
     * from employee pay) such as employer-side social security, pension
     * matching, health insurance employer share, etc.
     *
     * @param  float  $grossSalary  The employee's gross salary
     * @param  array  $contributions  Array of contribution rules, each with:
     *                                - name:   string  Label (e.g. "Employer Social Security")
     *                                - type:   string  'fixed' or 'percentage'
     *                                - rate:   float   Fixed amount or percentage
     *                                - cap:    float|null  Maximum contribution amount (null = no cap)
     * @return array{total: float, details: array}
     */
    public function calculateEmployerContributions(float $grossSalary, array $contributions): array
    {
        $total = 0;
        $details = [];

        foreach ($contributions as $contribution) {
            $name = $contribution['name'] ?? 'Unnamed';
            $type = $contribution['type'] ?? 'percentage';
            $rate = (float) ($contribution['rate'] ?? 0);
            $cap = isset($contribution['cap']) ? (float) $contribution['cap'] : null;

            if ($type === 'fixed') {
                $amount = $rate;
            } else {
                $amount = round($grossSalary * $rate / 100, 2);
            }

            if ($cap !== null && $amount > $cap) {
                $amount = $cap;
            }

            $total += $amount;
            $details[] = [
                'name' => $name,
                'type' => $type,
                'rate' => $rate,
                'cap' => $cap,
                'amount' => $amount,
            ];
        }

        return [
            'total' => round($total, 2),
            'details' => $details,
        ];
    }
}
