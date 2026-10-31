<?php

namespace App\Http\Controllers\Reports;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\SalaryStructureVersion;
use App\Services\Reports\PayrollReportService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Payroll reports and their exports.
 *
 * Split out of the old 3,400-line ReportController (finding L4). Route
 * names are unchanged.
 */
class PayrollReportController extends ReportController
{
    /**
     * Approved and paid payslips with a pay date in the period (T6: not
     * drafts or cancelled runs).
     *
     * @return Builder<Payroll>
     */
    private function paidIn(int $tenantId, string $startDate, string $endDate): Builder
    {
        return Payroll::where('tenant_id', $tenantId)
            ->whereIn('status', [Payroll::STATUS_APPROVED, Payroll::STATUS_PAID])
            ->whereBetween('pay_date', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()]);
    }

    /**
     * Pay per employee, added up in the database, largest gross first.
     *
     * One page of rows with $perPage, else all of them (a collection).
     *
     * @param  Builder<Payroll>  $payslips
     */
    private function byEmployee(Builder $payslips, ?int $perPage = null)
    {
        $query = (clone $payslips)->reorder()->toBase()
            ->selectRaw('employee_id, COUNT(*) as payslips, COALESCE(SUM(gross_salary), 0) as gross, COALESCE(SUM(total_deductions), 0) as deductions, COALESCE(SUM(net_salary), 0) as net')
            ->groupBy('employee_id')->orderByDesc('gross')->orderBy('employee_id');
        $rows = $perPage ? $query->paginate($perPage)->withQueryString() : $query->get();

        $ids = collect($rows instanceof LengthAwarePaginator ? $rows->items() : $rows)->pluck('employee_id')->filter()->all();
        $employees = Employee::whereIn('id', $ids)->get()->keyBy('id');
        $shape = fn ($row) => [
            'employee' => $employees->get($row->employee_id),
            'gross' => round((float) $row->gross, 2),
            'deductions' => round((float) $row->deductions, 2),
            'net' => round((float) $row->net, 2),
            'count' => (int) $row->payslips,
        ];

        return $rows instanceof LengthAwarePaginator ? $rows->through($shape) : $rows->map($shape)->values();
    }

    public function payrollSummary(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        // Totals over the period, one page of employees (audit MB-45).
        $payslips = $this->paidIn($tenantId, $startDate, $endDate);
        $sums = app(PayrollReportService::class)->sums($payslips, ['gross_salary', 'total_deductions', 'net_salary']);
        $totalGross = $sums['gross_salary'];
        $totalDeductions = $sums['total_deductions'];
        $totalNet = $sums['net_salary'];
        $payslipCount = $sums['payslips'];
        $employeeCount = $sums['employees'];
        $byEmployee = $this->byEmployee($payslips, PayrollReportService::PER_PAGE);

        return view('reports.payroll-summary', compact(
            'byEmployee', 'totalGross', 'totalDeductions', 'totalNet',
            'payslipCount', 'employeeCount', 'startDate', 'endDate'
        ));
    }

    public function payrollByDepartment(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $payrolls = Payroll::where('tenant_id', $tenantId)
            ->whereIn('status', [Payroll::STATUS_APPROVED, Payroll::STATUS_PAID]) // not drafts or cancelled runs (T6)
            ->whereBetween('pay_date', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()])
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

        $query = $this->earningsQuery($tenantId, $startDate, $endDate, $employeeId);

        // One page of payslips, totals over the period (audit MB-45).
        // A copy: paginate() puts the page's LIMIT/OFFSET on the query, which the totals must not have.
        $payrolls = (clone $query)->paginate(PayrollReportService::PER_PAGE)->withQueryString();
        $sums = app(PayrollReportService::class)->sums($query, ['basic_salary', 'allowances', 'overtime_amount', 'gross_salary', 'tax_deduction', 'total_deductions', 'net_salary']);
        $totalBasic = $sums['basic_salary'];
        $totalGross = $sums['gross_salary'];
        $totalAllowances = $sums['allowances'];
        $totalOvertime = $sums['overtime_amount'];
        $totalTax = $sums['tax_deduction'];
        $totalDeductions = $sums['total_deductions'];
        $totalNet = $sums['net_salary'];

        return view('reports.employee-earnings', compact(
            'payrolls', 'employees', 'totalBasic', 'totalGross', 'totalAllowances',
            'totalOvertime', 'totalTax', 'totalDeductions', 'totalNet',
            'startDate', 'endDate', 'employeeId'
        ));
    }

    /**
     * Employee earnings: approved and paid payslips, newest first.
     *
     * @return Builder<Payroll>
     */
    private function earningsQuery(int $tenantId, string $startDate, string $endDate, $employeeId): Builder
    {
        return $this->paidIn($tenantId, $startDate, $endDate)
            ->with('employee.department')
            ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
            ->orderBy('pay_date', 'desc')->orderBy('id', 'desc');
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
            ->payrollRegister($tenantId, $month, $status, $departmentId, PayrollReportService::PER_PAGE)
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
            ->bankDisbursement(auth()->user()->tenant_id, $startDate, $endDate, $status, $paymentMethod, PayrollReportService::PER_PAGE)
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

        $payslips = $this->paidIn($tenantId, $startDate, $endDate);
        $byEmployee = $this->byEmployee($payslips); // one row per employee, added up in the database

        if ($format === 'csv') {
            $exportData = $this->exportService->payrollSummaryData(null, $byEmployee);

            return $this->exportService
                ->setTitle('Payroll Summary')
                ->setFilters(['Period' => "$startDate to $endDate"])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        // The PDF lists every payslip too.
        $payrolls = (clone $payslips)->with('employee')->orderBy('pay_date')->get();
        $sums = app(PayrollReportService::class)->sums($payslips, ['gross_salary', 'total_deductions', 'net_salary']);
        $totalGross = $sums['gross_salary'];
        $totalDeductions = $sums['total_deductions'];
        $totalNet = $sums['net_salary'];
        $data = compact('payrolls', 'byEmployee', 'totalGross', 'totalDeductions', 'totalNet', 'startDate', 'endDate');

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
            ->whereIn('status', [Payroll::STATUS_APPROVED, Payroll::STATUS_PAID]) // not drafts or cancelled runs (T6)
            ->whereBetween('pay_date', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()])
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

        $query = $this->earningsQuery($tenantId, $startDate, $endDate, $employeeId);

        if ($format === 'csv') {
            // Read in chunks as the file is written (audit MB-45).
            $exportData = $this->exportService->employeeEarningsData($query->lazy(500));

            return $this->exportService
                ->setTitle('Employee Earnings')
                ->setFilters(['Period' => "$startDate to $endDate"])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        $payrolls = $query->get();
        $sums = app(PayrollReportService::class)->sums($query, ['gross_salary', 'allowances', 'overtime_amount', 'tax_deduction', 'total_deductions', 'net_salary']);
        $totalGross = $sums['gross_salary'];
        $totalAllowances = $sums['allowances'];
        $totalOvertime = $sums['overtime_amount'];
        $totalTax = $sums['tax_deduction'];
        $totalDeductions = $sums['total_deductions'];
        $totalNet = $sums['net_salary'];
        $data = compact('payrolls', 'totalGross', 'totalAllowances', 'totalOvertime', 'totalTax', 'totalDeductions', 'totalNet', 'startDate', 'endDate');

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
        $reports = app(PayrollReportService::class);

        if ($format === 'csv') {
            // Read in chunks as the file is written (audit MB-45).
            $exportData = $this->exportService->payrollRegisterData($reports->registerQuery($tenantId, $month, $status, $departmentId)->lazy(500));

            return $this->exportService
                ->setTitle('Payroll Register')
                ->setFilters(['Month' => $month])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        $data = $reports->payrollRegister($tenantId, $month, $status, $departmentId) + compact('month');

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
            ->whereBetween('pay_date', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()])
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
            ->whereBetween('pay_date', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()])
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
            ->whereBetween('pay_date', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()])
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

        $reports = app(PayrollReportService::class);
        // The page's "Paid by" filter applies to the file too.
        $query = $reports->bankDisbursementQuery($tenantId, $startDate, $endDate, $status, $request->get('payment_method'));

        if ($format === 'csv') {
            // Read in chunks as the file is written (audit MB-45).
            $exportData = $this->exportService->bankDisbursementData($query->lazy(500));

            return $this->exportService
                ->setTitle('Bank Disbursement')
                ->setFilters(['Period' => "$startDate to $endDate"])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        $payrolls = $query->get();
        $sums = $reports->sums($query, ['net_salary']);
        $totals = ['count' => $sums['payslips'], 'total_net' => (float) $sums['net_salary']];
        $data = compact('payrolls', 'totals', 'startDate', 'endDate');

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
