<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\StatutoryContribution;
use App\Models\TaxBracket;
use App\Models\Tenant;
use App\Services\Payroll\StatutoryLines;
use App\Services\Payroll\StatutorySettings;
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
        $statutory = $this->statutory($tenantId);

        foreach ($structure ? $structure->deductions : [] as $item) {
            // With statutory settings on, pension and NHF come from the
            // settings below, not from a structure deduction of the same kind.
            if ($statutory && StatutoryLines::deduction(['name' => $item->name])) {
                continue;
            }
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

        // Statutory employee contributions (pension, NHF): reliefs under the
        // Nigeria Tax Act 2025, so they come off before tax like any other
        // pre-tax deduction.
        $bases = [
            StatutoryContribution::BASE_BASIC => (float) $basicSalary,
            StatutoryContribution::BASE_GROSS => (float) $grossSalary,
            StatutoryContribution::BASE_PENSIONABLE => $this->pensionableBase((float) $basicSalary, $allowanceDetails, $statutory['pensionable_components'] ?? []),
        ];
        foreach ($statutory ? [StatutoryLines::PENSION_EMPLOYEE, StatutoryLines::NHF] : [] as $code) {
            if ($line = $this->statutoryLine($statutory['rates'][$code] ?? null, $bases)) {
                $deductionDetails[] = $line + ['amount_type' => 'percentage', 'pre_tax' => true];
                $totalOtherDeductions += $line['amount'];
                $taxableAmount -= $line['amount'];
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

        // Employer contributions (not deducted from employee). With statutory
        // settings on, employer pension, NSITF and ITF come from the settings.
        if ($statutory) {
            $employerContributionRules = array_values(array_filter($employerContributionRules, fn ($rule) => StatutoryLines::contribution($rule) === null));
        }
        $employerResult = $this->calculateEmployerContributions($grossSalary, $employerContributionRules);
        foreach ($statutory ? [StatutoryLines::PENSION_EMPLOYER, StatutoryLines::NSITF, StatutoryLines::ITF] : [] as $code) {
            if ($line = $this->statutoryLine($statutory['rates'][$code] ?? null, $bases)) {
                $employerResult['details'][] = $line + ['type' => 'percentage', 'cap' => null];
                $employerResult['total'] = round($employerResult['total'] + $line['amount'], 2);
            }
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
            'taxable_income' => round(max(0, $taxableAmount), 2),
            'tax_state_id' => $employee->tax_state_id,
            'pension_fund_administrator_id' => $employee->pension_fund_administrator_id,
            'tax_deduction' => $taxDeduction,
            'other_deductions' => $totalOtherDeductions,
            'deduction_details' => array_merge($deductionDetails, [
                ['name' => '_tax_method', 'method' => $taxResult['method'], 'breakdown' => $taxResult['breakdown']],
            ]),
            'employer_contributions' => $employerResult['total'],
            'employer_contribution_details' => $employerResult['details'],
            'total_deductions' => $totalDeductions,
            'net_salary' => $netSalary,
        ];
    }

    /** @var array<int, array{rates: Collection<string, StatutoryContribution>, pensionable_components: list<string>}|null> */
    protected array $statutoryCache = [];

    /**
     * The business's statutory rates when payroll works them out
     * (Payroll > Statutory settings), or null when it does not.
     *
     * @return array{rates: Collection<string, StatutoryContribution>, pensionable_components: list<string>}|null
     */
    public function statutory(int $tenantId): ?array
    {
        if (! array_key_exists($tenantId, $this->statutoryCache)) {
            $settings = StatutorySettings::get($tenantId);
            $this->statutoryCache[$tenantId] = $settings['auto']
                ? ['rates' => StatutoryContribution::forTenant($tenantId), 'pensionable_components' => $settings['pensionable_components']]
                : null;
        }

        return $this->statutoryCache[$tenantId];
    }

    /**
     * Pensionable pay (Pension Reform Act 2014 s.4(1)): basic salary plus the
     * allowances the business marked pensionable (housing and transport by
     * default), matched by name.
     *
     * @param  array<int, array<string, mixed>>  $allowanceDetails
     * @param  list<string>  $components
     */
    public function pensionableBase(float $basic, array $allowanceDetails, array $components): float
    {
        $base = $basic;
        foreach ($allowanceDetails as $allowance) {
            $name = strtolower((string) ($allowance['name'] ?? ''));
            foreach ($components as $component) {
                if ($component !== '' && str_contains($name, strtolower($component))) {
                    $base += (float) $allowance['amount'];
                    break;
                }
            }
        }

        return round($base, 2);
    }

    /**
     * One statutory payslip line from a setting, or null when it is off.
     *
     * @param  array<string, float>  $bases
     * @return array{name: string, rate: float, amount: float, base: float, statutory: string}|null
     */
    protected function statutoryLine(?StatutoryContribution $setting, array $bases): ?array
    {
        if (! $setting || ! $setting->is_enabled || (float) $setting->rate <= 0) {
            return null;
        }
        $base = $bases[$setting->base] ?? $bases[StatutoryContribution::BASE_GROSS];
        $amount = round($base * (float) $setting->rate / 100, 2);

        return $amount > 0
            ? ['name' => $setting->name, 'rate' => (float) $setting->rate, 'amount' => $amount, 'base' => $base, 'statutory' => $setting->code]
            : null;
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
