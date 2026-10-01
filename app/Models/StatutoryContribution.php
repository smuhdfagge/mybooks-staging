<?php

namespace App\Models;

use App\Services\Payroll\StatutoryLines;
use App\Traits\BelongsToTenant;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * One business's rate, base and due date for a Nigerian payroll scheme
 * (PAYE, pension, NHF, NSITF, ITF). Seeded from DEFAULTS with the source of
 * each figure; the business can change them on Payroll > Statutory settings.
 *
 * PAYE has no rate here: its bands are the business's tax brackets.
 */
class StatutoryContribution extends Model
{
    use BelongsToTenant;

    public const BASE_PENSIONABLE = 'pensionable';

    public const BASE_BASIC = 'basic';

    public const BASE_GROSS = 'gross';

    /** Due on day N of the month after the pay month. */
    public const DUE_DAY_OF_NEXT_MONTH = 'day_of_next_month';

    /** Due N working days (Monday to Friday) after the pay date. */
    public const DUE_WORKING_DAYS_AFTER_PAY = 'working_days_after_pay';

    /** Due on the last day of the month after the pay month. */
    public const DUE_END_OF_NEXT_MONTH = 'end_of_next_month';

    /** Due on the 1st of month N of the following year (annual levies). */
    public const DUE_NEXT_YEAR = 'next_year';

    public const DUE_RULES = [
        self::DUE_DAY_OF_NEXT_MONTH => 'Day of the next month',
        self::DUE_WORKING_DAYS_AFTER_PAY => 'Working days after pay day',
        self::DUE_END_OF_NEXT_MONTH => 'End of the next month',
        self::DUE_NEXT_YEAR => '1st of a month in the following year',
    ];

    /**
     * Defaults, with sources. Where the law is unclear the commonly applied
     * value is used; each can be changed per business.
     */
    public const DEFAULTS = [
        StatutoryLines::PAYE => [
            'name' => 'PAYE', 'rate' => null, 'base' => null, 'is_enabled' => true,
            'due_rule' => self::DUE_DAY_OF_NEXT_MONTH, 'due_value' => 10, 'effective_from' => '2026-01-01',
            'source' => 'Bands: Nigeria Tax Act 2025 (from 1 Jan 2026). Paid to the IRS of the state where the employee lives, '
                .'by the 10th of the next month (PITA s.81, kept by the Nigeria Tax Administration Act 2025).',
        ],
        StatutoryLines::PENSION_EMPLOYEE => [
            'name' => 'Pension (employee)', 'rate' => 8, 'base' => self::BASE_PENSIONABLE, 'is_enabled' => true,
            'due_rule' => self::DUE_WORKING_DAYS_AFTER_PAY, 'due_value' => 7, 'effective_from' => '2014-07-01',
            'source' => 'Pension Reform Act 2014 s.4(1): at least 8% of monthly basic, housing and transport. '
                .'s.11: paid to the PFA within 7 working days of paying salary. Tax relief: Nigeria Tax Act 2025.',
        ],
        StatutoryLines::PENSION_EMPLOYER => [
            'name' => 'Pension (employer)', 'rate' => 10, 'base' => self::BASE_PENSIONABLE, 'is_enabled' => true,
            'due_rule' => self::DUE_WORKING_DAYS_AFTER_PAY, 'due_value' => 7, 'effective_from' => '2014-07-01',
            'source' => 'Pension Reform Act 2014 s.4(1): at least 10% of monthly basic, housing and transport, '
                .'paid with the employee share within 7 working days (s.11).',
        ],
        StatutoryLines::NHF => [
            'name' => 'NHF', 'rate' => 2.5, 'base' => self::BASE_BASIC, 'is_enabled' => true,
            'due_rule' => self::DUE_END_OF_NEXT_MONTH, 'due_value' => null, 'effective_from' => '1992-01-01',
            'source' => 'National Housing Fund Act 1992 s.4: 2.5% of basic monthly salary, deducted by the employer and '
                .'paid to the Federal Mortgage Bank within one month. Tax relief: Nigeria Tax Act 2025.',
        ],
        StatutoryLines::NSITF => [
            'name' => 'NSITF (Employees\' Compensation)', 'rate' => 1, 'base' => self::BASE_GROSS, 'is_enabled' => true,
            'due_rule' => self::DUE_DAY_OF_NEXT_MONTH, 'due_value' => 16, 'effective_from' => '2011-01-01',
            'source' => 'Employees\' Compensation Act 2010 s.33: employer pays at least 1% of total monthly payroll to NSITF. '
                .'The Act sets no day; the 16th of the next month is commonly used. Confirm with your accountant.',
        ],
        StatutoryLines::ITF => [
            'name' => 'ITF (Industrial Training Fund)', 'rate' => 1, 'base' => self::BASE_GROSS, 'is_enabled' => false,
            'due_rule' => self::DUE_NEXT_YEAR, 'due_value' => 4, 'effective_from' => '2011-01-01',
            'source' => 'ITF Act as amended in 2011: 1% of annual payroll for employers with 5 or more staff or turnover of '
                .'N50 million or more, paid by 1 April of the next year. Off by default: turn it on if you are above either threshold.',
        ],
    ];

    /** Pay items counted as pensionable with basic salary (PRA 2014 s.4(1)). */
    public const DEFAULT_PENSIONABLE_COMPONENTS = ['Housing', 'Transport'];

    protected $fillable = [
        'tenant_id', 'code', 'name', 'rate', 'base', 'is_enabled', 'due_rule', 'due_value', 'effective_from', 'source',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'is_enabled' => 'boolean',
        'due_value' => 'integer',
        'effective_from' => 'date',
    ];

    /**
     * The business's settings, one per code, creating any missing ones from
     * the defaults.
     *
     * @return Collection<string, StatutoryContribution>
     */
    public static function forTenant(int $tenantId): Collection
    {
        $rows = self::withoutGlobalScopes()->where('tenant_id', $tenantId)->get()->keyBy('code');

        foreach (self::DEFAULTS as $code => $values) {
            if (! $rows->has($code)) {
                $row = new self(['tenant_id' => $tenantId, 'code' => $code] + $values);
                $row->skipTenantGuard = true;
                try {
                    $row->save();
                } catch (UniqueConstraintViolationException) {
                    // Another request created it first.
                    $row = self::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', $code)->firstOrFail();
                }
                $rows->put($code, $row);
            }
        }

        return $rows;
    }

    /**
     * When this scheme's money for the pay month is due. $payDate is the
     * latest pay date in the month (for the working-days rule).
     */
    public function dueDate(CarbonInterface $month, ?CarbonInterface $payDate = null): Carbon
    {
        $start = Carbon::parse($month)->startOfMonth();
        $value = (int) $this->due_value;

        return match ($this->due_rule) {
            self::DUE_WORKING_DAYS_AFTER_PAY => Carbon::parse($payDate ?? $start->copy()->endOfMonth())->startOfDay()->addWeekdays(max(0, $value)),
            self::DUE_END_OF_NEXT_MONTH => $start->copy()->addMonthNoOverflow()->endOfMonth()->startOfDay(),
            self::DUE_NEXT_YEAR => $start->copy()->addYear()->month(max(1, min(12, $value)))->startOfMonth(),
            default => $start->copy()->addMonthNoOverflow()->day(max(1, min($value ?: 10, $start->copy()->addMonthNoOverflow()->daysInMonth))),
        };
    }

    public function dueRuleLabel(): string
    {
        $v = (int) $this->due_value;

        return match ($this->due_rule) {
            self::DUE_WORKING_DAYS_AFTER_PAY => "{$v} working days after pay day",
            self::DUE_END_OF_NEXT_MONTH => 'by the end of the next month',
            self::DUE_NEXT_YEAR => 'by 1 '.Carbon::create(2000, max(1, min(12, $v)), 1)->format('F').' of the next year',
            default => 'by day '.$v.' of the next month',
        };
    }
}
