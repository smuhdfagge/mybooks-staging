<?php

namespace App\Services\Reports;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\SalaryStructureVersion;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The six detailed payroll reports, shared by the web pages and the API
 * (finding N9: the API routes pointed at methods that didn't exist).
 *
 * Each method returns the figures only; callers add their own filter lists
 * and presentation.
 */
class PayrollReportService
{
    /** Detailed register for one month. */
    public function payrollRegister(int $tenantId, string $month, ?string $status = null, $departmentId = null): array
    {
        $startDate = Carbon::parse($month.'-01')->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $query = Payroll::where('tenant_id', $tenantId)
            ->whereBetween('pay_period_start', [$startDate, $endDate])
            ->with(['employee.department', 'salaryStructure']);

        if ($status) {
            $query->where('status', $status);
        }
        if ($departmentId) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
        }

        $payrolls = $query->orderBy('payroll_number')->get();

        $totals = [
            'basic_salary' => $payrolls->sum('basic_salary'),
            'allowances' => $payrolls->sum('allowances'),
            'overtime_amount' => $payrolls->sum('overtime_amount'),
            'gross_salary' => $payrolls->sum('gross_salary'),
            'tax_deduction' => $payrolls->sum('tax_deduction'),
            'other_deductions' => $payrolls->sum('other_deductions'),
            'total_deductions' => $payrolls->sum('total_deductions'),
            'net_salary' => $payrolls->sum('net_salary'),
            'employer_contributions' => $payrolls->sum('employer_contributions'),
        ];

        $statusCounts = [
            'draft' => $payrolls->where('status', 'draft')->count(),
            'approved' => $payrolls->where('status', 'approved')->count(),
            'paid' => $payrolls->where('status', 'paid')->count(),
            'cancelled' => $payrolls->where('status', 'cancelled')->count(),
        ];

        return compact('payrolls', 'totals', 'statusCounts', 'startDate', 'endDate');
    }

    /** Cumulative earnings per employee for a year. */
    public function ytdEarnings(int $tenantId, int $year, $employeeId = null): array
    {
        $startDate = Carbon::create($year, 1, 1)->startOfYear();
        $endDate = Carbon::create($year, 12, 31)->endOfYear();

        $query = Payroll::where('tenant_id', $tenantId)
            ->whereIn('status', [Payroll::STATUS_APPROVED, Payroll::STATUS_PAID])
            ->whereBetween('pay_date', [$startDate, $endDate])
            ->with('employee.department');

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $payrolls = $query->orderBy('pay_date')->get();

        $byEmployee = $payrolls->groupBy('employee_id')->map(function ($records) {
            $monthlyBreakdown = $records->groupBy(fn ($p) => $p->pay_date->format('Y-m'))
                ->map(fn ($monthRecords) => [
                    'basic_salary' => (float) $monthRecords->sum('basic_salary'),
                    'allowances' => (float) $monthRecords->sum('allowances'),
                    'overtime' => (float) $monthRecords->sum('overtime_amount'),
                    'gross' => (float) $monthRecords->sum('gross_salary'),
                    'tax' => (float) $monthRecords->sum('tax_deduction'),
                    'deductions' => (float) $monthRecords->sum('total_deductions'),
                    'net' => (float) $monthRecords->sum('net_salary'),
                    'employer_contributions' => (float) $monthRecords->sum('employer_contributions'),
                ])->sortKeys();

            return [
                'employee' => $records->first()->employee,
                'ytd_basic' => (float) $records->sum('basic_salary'),
                'ytd_allowances' => (float) $records->sum('allowances'),
                'ytd_overtime' => (float) $records->sum('overtime_amount'),
                'ytd_gross' => (float) $records->sum('gross_salary'),
                'ytd_tax' => (float) $records->sum('tax_deduction'),
                'ytd_other_deductions' => (float) $records->sum('other_deductions'),
                'ytd_total_deductions' => (float) $records->sum('total_deductions'),
                'ytd_net' => (float) $records->sum('net_salary'),
                'ytd_employer_contributions' => (float) $records->sum('employer_contributions'),
                'pay_periods' => $records->count(),
                'monthly_breakdown' => $monthlyBreakdown,
            ];
        })->sortBy(fn ($r) => $r['employee']?->first_name)->values();

        $grandTotals = [
            'basic' => $byEmployee->sum('ytd_basic'),
            'allowances' => $byEmployee->sum('ytd_allowances'),
            'overtime' => $byEmployee->sum('ytd_overtime'),
            'gross' => $byEmployee->sum('ytd_gross'),
            'tax' => $byEmployee->sum('ytd_tax'),
            'deductions' => $byEmployee->sum('ytd_total_deductions'),
            'net' => $byEmployee->sum('ytd_net'),
            'employer_contributions' => $byEmployee->sum('ytd_employer_contributions'),
        ];

        return compact('byEmployee', 'grandTotals', 'startDate', 'endDate');
    }

    /** Tax deducted, for filing. */
    public function taxLiability(int $tenantId, string $startDate, string $endDate): array
    {
        $payrolls = $this->approvedOrPaid($tenantId, $startDate, $endDate);
        $rate = fn (Collection $r) => $r->sum('gross_salary') > 0
            ? round(($r->sum('tax_deduction') / $r->sum('gross_salary')) * 100, 2)
            : 0;

        $byEmployee = $payrolls->groupBy('employee_id')->map(fn ($records) => [
            'employee' => $records->first()->employee,
            'taxable_income' => (float) $records->sum('gross_salary'),
            'tax_deducted' => (float) $records->sum('tax_deduction'),
            'pay_periods' => $records->count(),
            'effective_rate' => $rate($records),
        ])->sortByDesc('tax_deducted')->values();

        $monthlyBreakdown = $payrolls->groupBy(fn ($p) => $p->pay_date->format('Y-m'))
            ->map(fn ($records, $month) => [
                'month' => $month,
                'employee_count' => $records->unique('employee_id')->count(),
                'total_taxable' => (float) $records->sum('gross_salary'),
                'total_tax' => (float) $records->sum('tax_deduction'),
                'effective_rate' => $rate($records),
            ])->sortKeys()->values();

        $byDepartment = $payrolls->groupBy(fn ($p) => $p->employee?->department_id ?? 0)
            ->map(fn ($records) => [
                'department_name' => $records->first()->employee?->department?->name ?? 'Unassigned',
                'employee_count' => $records->unique('employee_id')->count(),
                'total_taxable' => (float) $records->sum('gross_salary'),
                'total_tax' => (float) $records->sum('tax_deduction'),
            ])->sortByDesc('total_tax')->values();

        $totals = [
            'total_taxable' => (float) $payrolls->sum('gross_salary'),
            'total_tax' => (float) $payrolls->sum('tax_deduction'),
            'employee_count' => $payrolls->unique('employee_id')->count(),
            'effective_rate' => $rate($payrolls),
        ];

        return compact('byEmployee', 'monthlyBreakdown', 'byDepartment', 'totals');
    }

    /** Employer-side costs (pension, NHF and similar). */
    public function employerContributions(int $tenantId, string $startDate, string $endDate): array
    {
        $payrolls = $this->approvedOrPaid($tenantId, $startDate, $endDate);

        $contributionTypes = [];
        foreach ($payrolls as $payroll) {
            foreach ($payroll->employer_contribution_details ?? [] as $detail) {
                $name = $detail['name'] ?? 'Unknown';
                $contributionTypes[$name] ??= ['name' => $name, 'total' => 0, 'count' => 0];
                $contributionTypes[$name]['total'] += (float) ($detail['amount'] ?? 0);
                $contributionTypes[$name]['count']++;
            }
        }
        $contributionTypes = collect($contributionTypes)->sortByDesc('total')->values();

        $byEmployee = $payrolls->where('employer_contributions', '>', 0)->groupBy('employee_id')
            ->map(fn ($records) => [
                'employee' => $records->first()->employee,
                'gross_salary' => (float) $records->sum('gross_salary'),
                'employer_contributions' => (float) $records->sum('employer_contributions'),
                'details' => $records->pluck('employer_contribution_details')->flatten(1)->groupBy('name')
                    ->map(fn ($items) => (float) $items->sum('amount')),
                'cost_ratio' => $records->sum('gross_salary') > 0
                    ? round(($records->sum('employer_contributions') / $records->sum('gross_salary')) * 100, 2)
                    : 0,
            ])->sortByDesc('employer_contributions')->values();

        $monthlyTrend = $payrolls->groupBy(fn ($p) => $p->pay_date->format('Y-m'))
            ->map(fn ($records, $month) => [
                'month' => $month,
                'total_gross' => (float) $records->sum('gross_salary'),
                'total_contributions' => (float) $records->sum('employer_contributions'),
                'employee_count' => $records->unique('employee_id')->count(),
            ])->sortKeys()->values();

        $totals = [
            'total_gross' => (float) $payrolls->sum('gross_salary'),
            'total_employer_contributions' => (float) $payrolls->sum('employer_contributions'),
            'total_net' => (float) $payrolls->sum('net_salary'),
            'total_cost' => (float) $payrolls->sum('gross_salary') + (float) $payrolls->sum('employer_contributions'),
            'employee_count' => $payrolls->unique('employee_id')->count(),
        ];

        return compact('contributionTypes', 'byEmployee', 'monthlyTrend', 'totals');
    }

    /** Net pay to send to the bank. */
    public function bankDisbursement(int $tenantId, string $startDate, string $endDate, ?string $status, ?string $paymentMethod = null): array
    {
        $query = Payroll::where('tenant_id', $tenantId)
            ->whereBetween('pay_date', $this->days($startDate, $endDate))
            ->with('employee')
            ->orderBy('payroll_number');

        if ($status) {
            $query->where('status', $status);
        }
        if ($paymentMethod) {
            $query->where('payment_method', $paymentMethod);
        }

        $payrolls = $query->get();

        $byPaymentMethod = $payrolls->groupBy('payment_method')->map(fn ($records, $method) => [
            'method' => $method ?: 'Not Specified',
            'count' => $records->count(),
            'total' => (float) $records->sum('net_salary'),
        ])->values();

        $totals = [
            'count' => $payrolls->count(),
            'total_net' => (float) $payrolls->sum('net_salary'),
            'total_gross' => (float) $payrolls->sum('gross_salary'),
        ];

        return compact('payrolls', 'byPaymentMethod', 'totals');
    }

    /** Salary structure changes, and one employee's pay month by month. */
    public function salaryRevisionHistory(int $tenantId, $employeeId = null): array
    {
        $versions = SalaryStructureVersion::whereHas('salaryStructure', fn ($q) => $q->where('tenant_id', $tenantId))
            ->with(['salaryStructure', 'changedByUser'])
            ->orderBy('created_at', 'desc')
            ->get();

        $salaryProgression = collect();
        $selectedEmployee = null;

        if ($employeeId) {
            $selectedEmployee = Employee::where('tenant_id', $tenantId)->find($employeeId);

            if ($selectedEmployee) {
                $salaryProgression = Payroll::where('tenant_id', $tenantId)
                    ->where('employee_id', $employeeId)
                    ->whereIn('status', [Payroll::STATUS_APPROVED, Payroll::STATUS_PAID])
                    ->orderBy('pay_date')
                    ->get()
                    ->groupBy(fn ($p) => $p->pay_date->format('Y-m'))
                    ->map(fn ($records, $month) => [
                        'month' => $month,
                        'basic_salary' => (float) $records->avg('basic_salary'),
                        'allowances' => (float) $records->avg('allowances'),
                        'gross_salary' => (float) $records->avg('gross_salary'),
                        'net_salary' => (float) $records->avg('net_salary'),
                    ])->sortKeys()->values();
            }
        }

        return compact('versions', 'salaryProgression', 'selectedEmployee');
    }

    private function approvedOrPaid(int $tenantId, string $startDate, string $endDate): Collection
    {
        return Payroll::where('tenant_id', $tenantId)
            ->whereIn('status', [Payroll::STATUS_APPROVED, Payroll::STATUS_PAID])
            ->whereBetween('pay_date', $this->days($startDate, $endDate))
            ->with('employee.department')
            ->orderBy('pay_date')
            ->get();
    }

    /**
     * Whole days: an end date of "2026-09-30" includes pay dates later that
     * day (before, a payroll paid on the end date was left out).
     */
    private function days(string $startDate, string $endDate): array
    {
        return [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()];
    }
}
