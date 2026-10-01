<?php

namespace App\Services\Payroll;

/**
 * Which statutory scheme a payslip line belongs to. Used both when payroll
 * is posted (JournalService picks the liability account) and when the
 * remittance schedules are built, so the schedules always agree with the
 * ledger.
 *
 * Lines worked out from the statutory settings carry their code in
 * 'statutory'. Older lines, or deductions a business set up by hand, are
 * recognised by name, as payroll posting always did.
 */
class StatutoryLines
{
    public const PAYE = 'paye';

    public const PENSION_EMPLOYEE = 'pension_employee';

    public const PENSION_EMPLOYER = 'pension_employer';

    public const NHF = 'nhf';

    public const NSITF = 'nsitf';

    public const ITF = 'itf';

    private const PENSION = '/pension|provident|retirement|401k|superannuation|nssf/';

    /**
     * Code for a deduction line (employee side): pension_employee, nhf, or
     * null for anything else (tax lines, loans, insurance...).
     *
     * @param  array<string, mixed>  $line
     */
    public static function deduction(array $line): ?string
    {
        $code = $line['statutory'] ?? null;
        if (in_array($code, [self::PENSION_EMPLOYEE, self::NHF], true)) {
            return $code;
        }
        if (! empty($line['_loan_id'])) {
            return null;
        }

        $name = strtolower((string) ($line['name'] ?? ''));
        // Tax lines go to tax payable first (same order as the posting).
        if (preg_match('/\btax\b|\bpaye\b|\bwithholding\b/', $name)) {
            return null;
        }
        if (preg_match('/\bnhf\b|housing fund/', $name)) {
            return self::NHF;
        }
        if (preg_match(self::PENSION, $name)) {
            return self::PENSION_EMPLOYEE;
        }

        return null;
    }

    /**
     * Code for an employer contribution line: pension_employer, nsitf, itf,
     * or null.
     *
     * @param  array<string, mixed>  $line
     */
    public static function contribution(array $line): ?string
    {
        $code = $line['statutory'] ?? null;
        if (in_array($code, [self::PENSION_EMPLOYER, self::NSITF, self::ITF], true)) {
            return $code;
        }

        $name = strtolower((string) ($line['name'] ?? ''));
        if (preg_match('/nsitf|employees?\'?s? compensation/', $name)) {
            return self::NSITF;
        }
        if (preg_match('/\bitf\b|industrial training/', $name)) {
            return self::ITF;
        }
        if (preg_match(self::PENSION, $name)) {
            return self::PENSION_EMPLOYER;
        }

        return null;
    }

    /** Logical account (AccountCodeService) holding what is owed for a code. */
    public static function liabilityKey(string $code): string
    {
        return match ($code) {
            self::PAYE => 'tax_payable',
            self::PENSION_EMPLOYEE, self::PENSION_EMPLOYER => 'pension_payable',
            self::NHF => 'nhf_payable',
            self::NSITF => 'nsitf_payable',
            self::ITF => 'itf_payable',
            default => 'payroll_liabilities',
        };
    }
}
