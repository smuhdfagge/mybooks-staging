<?php

namespace App\Http\Controllers\Reports;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\SalaryStructureVersion;
use App\Services\Reports\PayrollReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Payroll reports and their exports.
 *
 * Split out of the old 3,400-line ReportController (finding L4). Route
 * names are unchanged.
 */
class PayrollReportController extends ReportController
{
    public function payrollSummary(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $payrolls = Payroll::where('tenant_id', $tenantId)
            ->whereBetween('pay_date', [$startDate, $endDate])
            ->with('employee')
            ->get();

        $totalGross = $payrolls->sum('gross_salary');
        $totalDeductions = $payrolls->sum('total_deductions');
        $totalNet = $payrolls->sum('net_salary');

        // Group by employee
        $byEmployee = $payrolls->groupBy('employee_id')->map(function ($records) {
            return [
                'employee' => $records->first()->employee,
                'gross' => $records->sum('gross_salary'),
                'deductions' => $records->sum('total_deductions'),
                'net' => $records->sum('net_salary'),
                'count' => $records->count(),
            ];
        })->values();

        return view('reports.payroll-summary', compact(
            'payrolls', 'byEmployee', 'totalGross', 'totalDeductions',
            'totalNet', 'startDate', 'endDate'
        ));
    }

    public function payrollByDepartment(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $payrolls = Payroll::where('tenant_id', $tenantId)
            ->whereBetween('pay_date', [$startDate, $endDate])
            ->with('employee.department')
            ->get();

        $totalGross = $payrolls->sum('gross_salary');
        $totalDeductions = $payrolls->sum('total_deductions');
        $totalNet = $payrolls->sum('net_salary');

        $byDepartment = $payrolls->groupBy(function ($payroll) {
            return $payroll->employee?->department_id ?? 0;
        })->map(function ($records) {
            $department = $records->first()->employee?->department;

            return [
                'department' => $department,
                'department_name' => $department?->name ?? 'Unassigned',
                'employee_count' => $records->unique('employee_id')->count(),
                'gross' => $records->sum('gross_salary'),
                'allowances' => $records->sum('allowances'),
                'overtime' => $records->sum('overtime_amount'),
                'tax' => $records->sum('tax_deduction'),
                'deductions' => $records->sum('total_deductions'),
                'net' => $records->sum('net_salary'),
                'records_count' => $records->count(),
            ];
        })->sortByDesc('gross')->values();

        return view('reports.payroll-by-department', compact(
            'byDepartment', 'totalGross', 'totalDeductions', 'totalNet',
            'startDate', 'endDate'
        ));
    }

    public function employeeEarnings(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $employeeId = $request->get('employee_id');

        $employees = Employee::where('tenant_id', $tenantId)
            ->orderBy('first_name')
            ->get();

        $query = Payroll::where('tenant_id', $tenantId)
            ->whereBetween('pay_date', [$startDate, $endDate])
            ->with('employee.department');

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $payrolls = $query->orderBy('pay_date', 'desc')->get();

        $totalGross = $payrolls->sum('gross_salary');
        $totalAllowances = $payrolls->sum('allowances');
        $totalOvertime = $payrolls->sum('overtime_amount');
        $totalTax = $payrolls->sum('tax_deduction');
        $totalDeductions = $payrolls->sum('total_deductions');
        $totalNet = $payrolls->sum('net_salary');

        return view('reports.employee-earnings', compact(
            'payrolls', 'employees', 'totalGross', 'totalAllowances',
            'totalOvertime', 'totalTax', 'totalDeductions', 'totalNet',
            'startDate', 'endDate', 'employeeId'
        ));
    }

    /**
     * Payroll Register — Detailed monthly register with all salary components
     */
    public function payrollRegister(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $month = $request->get('month', now()->format('Y-m'));
        $status = $request->get('status');
        $departmentId = $request->get('department_id');
        $departments = Department::where('tenant_id', $tenantId)->orderBy('name')->get();

        return view('reports.payroll-register', app(PayrollReportService::class)
            ->payrollRegister($tenantId, $month, $status, $departmentId)
            + compact('departments', 'month', 'status', 'departmentId'));
    }

    /**
     * Year-to-Date (YTD) Earnings — Cumulative earnings per employee
     */
    public function ytdEarnings(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $year = (int) $request->get('year', now()->year);
        $employeeId = $request->get('employee_id');
        $employees = Employee::where('tenant_id', $tenantId)->orderBy('first_name')->get();

        return view('reports.ytd-earnings', app(PayrollReportService::class)
            ->ytdEarnings($tenantId, $year, $employeeId)
            + compact('employees', 'year', 'employeeId'));
    }

    /**
     * Tax Liability Report — Tax deductions summary for filing
     */
    public function taxLiabilityPayroll(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        return view('reports.tax-liability-payroll', app(PayrollReportService::class)
            ->taxLiability(auth()->user()->tenant_id, $startDate, $endDate)
            + compact('startDate', 'endDate'));
    }

    /**
     * Employer Contribution Summary — Employer-side costs breakdown
     */
    public function employerContributions(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        return view('reports.employer-contributions', app(PayrollReportService::class)
            ->employerContributions(auth()->user()->tenant_id, $startDate, $endDate)
            + compact('startDate', 'endDate'));
    }

    /**
     * Bank Disbursement Report — Payment list for bank transfers
     */
    public function bankDisbursement(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $status = $request->get('status', Payroll::STATUS_APPROVED);
        $paymentMethod = $request->get('payment_method');

        return view('reports.bank-disbursement', app(PayrollReportService::class)
            ->bankDisbursement(auth()->user()->tenant_id, $startDate, $endDate, $status, $paymentMethod)
            + compact('startDate', 'endDate', 'status', 'paymentMethod'));
    }

    /**
     * Salary Revision History — Track salary structure changes over time
     */
    public function salaryRevisionHistory(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $employeeId = $request->get('employee_id');
        $employees = Employee::where('tenant_id', $tenantId)->orderBy('first_name')->get();

        return view('reports.salary-revision-history', app(PayrollReportService::class)
            ->salaryRevisionHistory($tenantId, $employeeId)
            + compact('employees', 'employeeId'));
    }

    /**
     * Export Payroll Summary Report
     */
    public function exportPayrollSummary(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $format = $request->get('format', 'pdf');

        $payrolls = Payroll::where('tenant_id', $tenantId)
            ->whereBetween('pay_date', [$startDate, $endDate])
            ->with('employee')
            ->get();

        $totalGross = $payrolls->sum('gross_salary');
        $totalDeductions = $payrolls->sum('total_deductions');
        $totalNet = $payrolls->sum('net_salary');

        $byEmployee = $payrolls->groupBy('employee_id')->map(function ($records) {
            return [
                'employee' => $records->first()->employee,
                'gross' => $records->sum('gross_salary'),
                'deductions' => $records->sum('total_deductions'),
                'net' => $records->sum('net_salary'),
                'count' => $records->count(),
            ];
        })->values();

        $data = compact('payrolls', 'byEmployee', 'totalGross', 'totalDeductions', 'totalNet', 'startDate', 'endDate');

        if ($format === 'csv') {
            $exportData = $this->exportService->payrollSummaryData($payrolls, $byEmployee);

            return $this->exportService
                ->setTitle('Payroll Summary')
                ->setFilters(['Period' => "$startDate to $endDate"])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Payroll Summary')
            ->setFilters(['Period' => "$startDate to $endDate"])
            ->exportToPdf('reports.pdf.payroll-summary', $data);
    }

    /**
     * Export Payroll by Department Report
     */
    public function exportPayrollByDepartment(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $format = $request->get('format', 'pdf');

        $payrolls = Payroll::where('tenant_id', $tenantId)
            ->whereBetween('pay_date', [$startDate, $endDate])
            ->with('employee.department')
            ->get();

        $totalGross = $payrolls->sum('gross_salary');
        $totalDeductions = $payrolls->sum('total_deductions');
        $totalNet = $payrolls->sum('net_salary');

        $byDepartment = $payrolls->groupBy(function ($payroll) {
            return $payroll->employee?->department_id ?? 0;
        })->map(function ($records) {
            $department = $records->first()->employee?->department;

            return [
                'department' => $department,
                'department_name' => $department?->name ?? 'Unassigned',
                'employee_count' => $records->unique('employee_id')->count(),
                'gross' => $records->sum('gross_salary'),
                'allowances' => $records->sum('allowances'),
                'overtime' => $records->sum('overtime_amount'),
                'tax' => $records->sum('tax_deduction'),
                'deductions' => $records->sum('total_deductions'),
                'net' => $records->sum('net_salary'),
                'records_count' => $records->count(),
            ];
        })->sortByDesc('gross')->values();

        $data = compact('byDepartment', 'totalGross', 'totalDeductions', 'totalNet', 'startDate', 'endDate');

        if ($format === 'csv') {
            $exportData = $this->exportService->payrollByDepartmentData($byDepartment);

            return $this->exportService
                ->setTitle('Payroll by Department')
                ->setFilters(['Period' => "$startDate to $endDate"])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Payroll by Department')
            ->setFilters(['Period' => "$startDate to $endDate"])
            ->exportToPdf('reports.pdf.payroll-by-department', $data);
    }

    /**
     * Export Employee Earnings Report
     */
    public function exportEmployeeEarnings(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $employeeId = $request->get('employee_id');
        $format = $request->get('format', 'pdf');

        $query = Payroll::where('tenant_id', $tenantId)
            ->whereBetween('pay_date', [$startDate, $endDate])
            ->with('employee.department');

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $payrolls = $query->orderBy('pay_date', 'desc')->get();

        $totalGross = $payrolls->sum('gross_salary');
        $totalAllowances = $payrolls->sum('allowances');
        $totalOvertime = $payrolls->sum('overtime_amount');
        $totalTax = $payrolls->sum('tax_deduction');
        $totalDeductions = $payrolls->sum('total_deductions');
        $totalNet = $payrolls->sum('net_salary');

        $data = compact('payrolls', 'totalGross', 'totalAllowances', 'totalOvertime', 'totalTax', 'totalDeductions', 'totalNet', 'startDate', 'endDate');

        if ($format === 'csv') {
            $exportData = $this->exportService->employeeEarningsData($payrolls);

            return $this->exportService
                ->setTitle('Employee Earnings')
                ->setFilters(['Period' => "$startDate to $endDate"])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Employee Earnings')
            ->setFilters(['Period' => "$startDate to $endDate"])
            ->exportToPdf('reports.pdf.employee-earnings', $data);
    }

    /**
     * Export Payroll Register
     */
    public function exportPayrollRegister(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $month = $request->get('month', now()->format('Y-m'));
        $status = $request->get('status');
        $departmentId = $request->get('department_id');
        $format = $request->get('format', 'pdf');

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

        $data = compact('payrolls', 'totals', 'month', 'startDate', 'endDate');

        if ($format === 'csv') {
            $exportData = $this->exportService->payrollRegisterData($payrolls);

            return $this->exportService
                ->setTitle('Payroll Register')
                ->setFilters(['Month' => $month])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Payroll Register')
            ->setFilters(['Month' => $month])
            ->setOrientation('landscape')
            ->exportToPdf('reports.pdf.payroll-register', $data);
    }

    /**
     * Export YTD Earnings
     */
    public function exportYtdEarnings(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $year = $request->get('year', now()->year);
        $employeeId = $request->get('employee_id');
        $format = $request->get('format', 'pdf');

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
            $employee = $records->first()->employee;

            return [
                'employee' => $employee,
                'ytd_basic' => (float) $records->sum('basic_salary'),
                'ytd_allowances' => (float) $records->sum('allowances'),
                'ytd_overtime' => (float) $records->sum('overtime_amount'),
                'ytd_gross' => (float) $records->sum('gross_salary'),
                'ytd_tax' => (float) $records->sum('tax_deduction'),
                'ytd_total_deductions' => (float) $records->sum('total_deductions'),
                'ytd_net' => (float) $records->sum('net_salary'),
                'ytd_employer_contributions' => (float) $records->sum('employer_contributions'),
                'pay_periods' => $records->count(),
            ];
        })->sortBy(fn ($r) => $r['employee']->first_name)->values();

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

        $data = compact('byEmployee', 'grandTotals', 'year', 'startDate', 'endDate');

        if ($format === 'csv') {
            $exportData = $this->exportService->ytdEarningsData($byEmployee);

            return $this->exportService
                ->setTitle('Year-to-Date Earnings')
                ->setFilters(['Year' => $year])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Year-to-Date Earnings')
            ->setFilters(['Year' => $year])
            ->setOrientation('landscape')
            ->exportToPdf('reports.pdf.ytd-earnings', $data);
    }

    /**
     * Export Tax Liability Payroll Report
     */
    public function exportTaxLiabilityPayroll(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $format = $request->get('format', 'pdf');

        $payrolls = Payroll::where('tenant_id', $tenantId)
            ->whereIn('status', [Payroll::STATUS_APPROVED, Payroll::STATUS_PAID])
            ->whereBetween('pay_date', [$startDate, $endDate])
            ->with('employee.department')
            ->orderBy('pay_date')
            ->get();

        $byEmployee = $payrolls->groupBy('employee_id')->map(function ($records) {
            $employee = $records->first()->employee;

            return [
                'employee' => $employee,
                'taxable_income' => (float) $records->sum('gross_salary'),
                'tax_deducted' => (float) $records->sum('tax_deduction'),
                'pay_periods' => $records->count(),
                'effective_rate' => $records->sum('gross_salary') > 0
                    ? round(($records->sum('tax_deduction') / $records->sum('gross_salary')) * 100, 2)
                    : 0,
            ];
        })->sortByDesc('tax_deducted')->values();

        $totals = [
            'total_taxable' => (float) $payrolls->sum('gross_salary'),
            'total_tax' => (float) $payrolls->sum('tax_deduction'),
        ];

        $data = compact('byEmployee', 'totals', 'startDate', 'endDate');

        if ($format === 'csv') {
            $exportData = $this->exportService->taxLiabilityPayrollData($byEmployee);

            return $this->exportService
                ->setTitle('Tax Liability - Payroll')
                ->setFilters(['Period' => "$startDate to $endDate"])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Tax Liability - Payroll')
            ->setFilters(['Period' => "$startDate to $endDate"])
            ->exportToPdf('reports.pdf.tax-liability-payroll', $data);
    }

    /**
     * Export Employer Contributions Report
     */
    public function exportEmployerContributions(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $format = $request->get('format', 'pdf');

        $payrolls = Payroll::where('tenant_id', $tenantId)
            ->whereIn('status', [Payroll::STATUS_APPROVED, Payroll::STATUS_PAID])
            ->whereBetween('pay_date', [$startDate, $endDate])
            ->with('employee.department')
            ->get();

        $contributionTypes = [];
        foreach ($payrolls as $payroll) {
            if (! empty($payroll->employer_contribution_details)) {
                foreach ($payroll->employer_contribution_details as $detail) {
                    $name = $detail['name'] ?? 'Unknown';
                    if (! isset($contributionTypes[$name])) {
                        $contributionTypes[$name] = ['name' => $name, 'total' => 0, 'count' => 0];
                    }
                    $contributionTypes[$name]['total'] += (float) ($detail['amount'] ?? 0);
                    $contributionTypes[$name]['count']++;
                }
            }
        }
        $contributionTypes = collect($contributionTypes)->sortByDesc('total')->values();

        $byEmployee = $payrolls->where('employer_contributions', '>', 0)->groupBy('employee_id')->map(function ($records) {
            $employee = $records->first()->employee;

            return [
                'employee' => $employee,
                'gross_salary' => (float) $records->sum('gross_salary'),
                'employer_contributions' => (float) $records->sum('employer_contributions'),
            ];
        })->sortByDesc('employer_contributions')->values();

        $totals = [
            'total_gross' => (float) $payrolls->sum('gross_salary'),
            'total_employer_contributions' => (float) $payrolls->sum('employer_contributions'),
        ];

        $data = compact('contributionTypes', 'byEmployee', 'totals', 'startDate', 'endDate');

        if ($format === 'csv') {
            $exportData = $this->exportService->employerContributionsData($byEmployee, $contributionTypes);

            return $this->exportService
                ->setTitle('Employer Contributions')
                ->setFilters(['Period' => "$startDate to $endDate"])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Employer Contributions')
            ->setFilters(['Period' => "$startDate to $endDate"])
            ->exportToPdf('reports.pdf.employer-contributions', $data);
    }

    /**
     * Export Bank Disbursement Report
     */
    public function exportBankDisbursement(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $status = $request->get('status', Payroll::STATUS_APPROVED);
        $format = $request->get('format', 'csv');

        $query = Payroll::where('tenant_id', $tenantId)
            ->whereBetween('pay_date', [$startDate, $endDate])
            ->with('employee')
            ->orderBy('payroll_number');

        if ($status) {
            $query->where('status', $status);
        }

        $payrolls = $query->get();
        $totals = ['count' => $payrolls->count(), 'total_net' => (float) $payrolls->sum('net_salary')];

        $data = compact('payrolls', 'totals', 'startDate', 'endDate');

        if ($format === 'csv') {
            $exportData = $this->exportService->bankDisbursementData($payrolls);

            return $this->exportService
                ->setTitle('Bank Disbursement')
                ->setFilters(['Period' => "$startDate to $endDate"])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Bank Disbursement')
            ->setFilters(['Period' => "$startDate to $endDate"])
            ->exportToPdf('reports.pdf.bank-disbursement', $data);
    }

    /**
     * Export Salary Revision History
     */
    public function exportSalaryRevisionHistory(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $format = $request->get('format', 'pdf');

        $versions = SalaryStructureVersion::whereHas('salaryStructure', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })
            ->with(['salaryStructure', 'changedByUser'])
            ->orderBy('created_at', 'desc')
            ->get();

        $data = compact('versions');

        if ($format === 'csv') {
            $exportData = $this->exportService->salaryRevisionHistoryData($versions);

            return $this->exportService
                ->setTitle('Salary Revision History')
                ->setFilters([])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Salary Revision History')
            ->setFilters([])
            ->exportToPdf('reports.pdf.salary-revision-history', $data);
    }
}
