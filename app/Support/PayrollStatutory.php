<?php

namespace App\Support;

use App\Models\Tenant;
use Carbon\Carbon;

/**
 * Nigerian payroll contributions other than PAYE (Phase F, tax pack 1):
 * pension, NHF, NSITF and ITF. The rates below are defaults; each business
 * can change them on the Payroll Liabilities page (stored in
 * tenants.settings['statutory']).
 *
 * Sources (checked 2 October 2026):
 * - Pension: Pension Reform Act 2014 s.4(1): employer at least 10% and
 *   employee at least 8% of monthly emoluments (basic + housing +
 *   transport). Applies to employers with 3 or more staff (s.2).
 *   Remit within 7 working days of paying salaries (s.11(5)).
 * - NHF: National Housing Fund Act 1992 s.4: 2.5% of an employee's basic
 *   monthly salary, deducted by the employer, remitted within one month.
 * - NSITF: Employee's Compensation Act 2010 s.33: employer pays at least 1%
 *   of total monthly payroll. Commonly due by the 16th of the next month.
 * - ITF: Industrial Training Fund (Amendment) Act 2011 s.6: 1% of annual
 *   payroll for employers with 5 or more staff or turnover of ₦50m or more,
 *   due by 1 April of the next year.
 * - PAYE: to the State Internal Revenue Service of the employee's state of
 *   residence, by the 10th of the next month (Nigeria Tax Act 2025 /
 *   Nigeria Tax Administration Act 2025).
 */
final class PayrollStatutory
{
    public const BODIES = ['paye', 'pension', 'nhf', 'nsitf', 'itf'];

    public const DEFAULTS = [
        'enabled' => true,
        'pension_applies' => true,
        'pension_employee_rate' => 8.0,
        'pension_employer_rate' => 10.0,
        'nhf_rate' => 2.5,
        'nsitf_applies' => true,
        'nsitf_rate' => 1.0,
        'itf_applies' => false, // only 5+ staff or turnover ₦50m+, so the business turns it on
        'itf_rate' => 1.0,
    ];

    /** Account-code key each body's liability is held in. */
    public const LIABILITY_KEYS = [
        'paye' => 'tax_payable',
        'pension' => 'pension_payable',
        'nhf' => 'nhf_payable',
        'nsitf' => 'nsitf_payable',
        'itf' => 'itf_payable',
    ];

    public const LABELS = [
        'paye' => 'PAYE (State Internal Revenue Service)',
        'pension' => 'Pension (to each PFA)',
        'nhf' => 'National Housing Fund (NHF)',
        'nsitf' => 'NSITF (Employee Compensation)',
        'itf' => 'Industrial Training Fund (ITF)',
    ];

    public const DUE_RULES = [
        'paye' => '10th of the next month',
        'pension' => 'Within 7 working days of paying salaries',
        'nhf' => 'Within one month of the deduction',
        'nsitf' => '16th of the next month',
        'itf' => '1 April of the next year',
    ];

    public static function isNigerian(?Tenant $tenant): bool
    {
        return $tenant !== null && in_array(strtoupper(trim((string) $tenant->country)), ['NG', 'NGA', 'NIGERIA'], true);
    }

    /**
     * The business's settings merged over the defaults.
     *
     * @return array<string, mixed>
     */
    public static function settings(?Tenant $tenant): array
    {
        $saved = (array) ($tenant?->settings['statutory'] ?? []);

        return array_merge(self::DEFAULTS, array_intersect_key($saved, self::DEFAULTS));
    }

    /** Whether payroll should add the contributions for this business. */
    public static function active(?Tenant $tenant): bool
    {
        return self::isNigerian($tenant) && (bool) self::settings($tenant)['enabled'];
    }

    /**
     * Which body a payslip deduction or employer contribution belongs to,
     * from its name (names are set by the business, so this matches words).
     */
    public static function classify(string $name): ?string
    {
        $name = strtolower($name);

        return match (true) {
            (bool) preg_match('/\bnhf\b|housing fund/', $name) => 'nhf',
            (bool) preg_match('/nsitf|employees?\'?s? compensation/', $name) => 'nsitf',
            (bool) preg_match('/\bitf\b|industrial training/', $name) => 'itf',
            (bool) preg_match('/pension|\brsa\b|retirement/', $name) => 'pension',
            default => null,
        };
    }

    /** Housing and transport allowances count toward pensionable pay. */
    public static function isPensionableAllowance(string $name): bool
    {
        return (bool) preg_match('/hous|\brent\b|accommodat|transport/i', $name);
    }

    /** When a month's contributions are due. */
    public static function dueDate(string $body, Carbon $periodEnd): Carbon
    {
        $next = $periodEnd->copy()->startOfMonth()->addMonthNoOverflow();

        return match ($body) {
            'paye' => $next->copy()->day(10),
            'pension' => $periodEnd->copy()->addWeekdays(7),
            'nhf' => $next->copy()->endOfMonth(),
            'nsitf' => $next->copy()->day(16),
            'itf' => Carbon::create($periodEnd->year + 1, 4, 1),
            default => $next->copy()->endOfMonth(),
        };
    }
}
