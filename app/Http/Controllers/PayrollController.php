<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPayrollBatch;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollBatch;
use App\Services\BankFileExportService;
use App\Services\PayrollTaxService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PayrollController extends Controller
{
    public function index()
    {
        return view('payroll.index');
    }

    public function create()
    {
        $employees = Employee::where('status', 'active')->get();

        return view('payroll.create', compact('employees'));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'pay_period_start' => 'required|date',
            'pay_period_end' => 'required|date|after:pay_period_start',
            'basic_salary' => 'required|numeric|min:0',
            'allowances' => 'nullable|numeric|min:0',
            'overtime_hours' => 'nullable|numeric|min:0',
            'overtime_amount' => 'nullable|numeric|min:0',
            'tax_deduction' => 'nullable|numeric|min:0',
            'other_deductions' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $grossSalary = ($validated['basic_salary'] ?? 0)
                     + ($validated['allowances'] ?? 0)
                     + ($validated['overtime_amount'] ?? 0);

        $totalDeductions = ($validated['tax_deduction'] ?? 0) + ($validated['other_deductions'] ?? 0);
        $netSalary = $grossSalary - $totalDeductions;

        $payroll = Payroll::create([
            'tenant_id' => $tenantId,
            'payroll_number' => Payroll::generateNumber($tenantId),
            'employee_id' => $validated['employee_id'],
            'pay_period_start' => $validated['pay_period_start'],
            'pay_period_end' => $validated['pay_period_end'],
            'pay_date' => $validated['pay_period_end'],
            'basic_salary' => $validated['basic_salary'],
            'allowances' => $validated['allowances'] ?? 0,
            'overtime_hours' => $validated['overtime_hours'] ?? 0,
            'overtime_amount' => $validated['overtime_amount'] ?? 0,
            'gross_salary' => $grossSalary,
            'tax_deduction' => $validated['tax_deduction'] ?? 0,
            'other_deductions' => $validated['other_deductions'] ?? 0,
            'total_deductions' => $totalDeductions,
            'net_salary' => $netSalary,
            'status' => 'draft',
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('payroll.show', $payroll)->with('success', 'Payroll record created.');
    }

    public function show(Payroll $payroll)
    {
        abort_unless($payroll->tenant_id === auth()->user()->tenant_id, 403);

        $payroll->load('employee.department', 'employee.designation');

        return view('payroll.show', compact('payroll'));
    }

    public function edit(Payroll $payroll)
    {
        abort_unless($payroll->tenant_id === auth()->user()->tenant_id, 403);

        if ($payroll->status === 'paid') {
            return redirect()->route('payroll.show', $payroll)->with('error', 'Paid payroll cannot be edited.');
        }

        $employees = Employee::where('status', 'active')->get();

        return view('payroll.edit', compact('payroll', 'employees'));
    }

    public function update(Request $request, Payroll $payroll)
    {
        abort_unless($payroll->tenant_id === auth()->user()->tenant_id, 403);

        if ($payroll->status === 'paid') {
            return redirect()->route('payroll.show', $payroll)->with('error', 'Paid payroll cannot be updated.');
        }

        $validated = $request->validate([
            'pay_period_start' => 'required|date',
            'pay_period_end' => 'required|date|after:pay_period_start',
            'basic_salary' => 'required|numeric|min:0',
            'allowances' => 'nullable|numeric|min:0',
            'overtime_hours' => 'nullable|numeric|min:0',
            'overtime_amount' => 'nullable|numeric|min:0',
            'tax_deduction' => 'nullable|numeric|min:0',
            'other_deductions' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $grossSalary = ($validated['basic_salary'] ?? 0)
                     + ($validated['allowances'] ?? 0)
                     + ($validated['overtime_amount'] ?? 0);

        $totalDeductions = ($validated['tax_deduction'] ?? 0) + ($validated['other_deductions'] ?? 0);
        $netSalary = $grossSalary - $totalDeductions;

        $payroll->update([
            'pay_period_start' => $validated['pay_period_start'],
            'pay_period_end' => $validated['pay_period_end'],
            'pay_date' => $validated['pay_period_end'],
            'basic_salary' => $validated['basic_salary'],
            'allowances' => $validated['allowances'] ?? 0,
            'overtime_hours' => $validated['overtime_hours'] ?? 0,
            'overtime_amount' => $validated['overtime_amount'] ?? 0,
            'gross_salary' => $grossSalary,
            'tax_deduction' => $validated['tax_deduction'] ?? 0,
            'other_deductions' => $validated['other_deductions'] ?? 0,
            'total_deductions' => $totalDeductions,
            'net_salary' => $netSalary,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('payroll.show', $payroll)->with('success', 'Payroll record updated.');
    }

    public function destroy(Payroll $payroll)
    {
        abort_unless($payroll->tenant_id === auth()->user()->tenant_id, 403);

        if ($payroll->status === 'paid') {
            return redirect()->route('payroll.index')->with('error', 'Paid payroll cannot be deleted.');
        }

        $payroll->delete();

        return redirect()->route('payroll.index')->with('success', 'Payroll record deleted.');
    }

    public function approve(Payroll $payroll)
    {
        abort_unless($payroll->tenant_id === auth()->user()->tenant_id, 403);

        if ($payroll->status !== 'draft') {
            return redirect()->route('payroll.show', $payroll)->with('error', 'Only draft payroll can be approved.');
        }

        // Segregation of duties: creator cannot approve their own payroll
        if ($payroll->created_by === auth()->id()) {
            return redirect()->route('payroll.show', $payroll)->with('error', 'You cannot approve a payroll you created. A different user must approve it.');
        }

        try {
            // Posts the payroll cost into its pay period (A10).
            $payroll->approve(auth()->id());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('payroll.show', $payroll)
                ->with('error', collect($e->errors())->flatten()->first() ?? 'This payroll cannot be approved.');
        }

        // Log the activity
        $payroll->logCustomActivity(ActivityLog::ACTION_APPROVED, "Payroll '{$payroll->payroll_number}' was approved");

        return redirect()->route('payroll.show', $payroll)->with('success', 'Payroll approved.');
    }

    public function markAsPaid(Payroll $payroll)
    {
        abort_unless($payroll->tenant_id === auth()->user()->tenant_id, 403);

        if ($payroll->status !== 'approved') {
            return redirect()->route('payroll.show', $payroll)->with('error', 'Only approved payroll can be marked as paid.');
        }

        $payroll->markAsPaid();

        // Log the activity
        $payroll->logCustomActivity(ActivityLog::ACTION_PAID, "Payroll '{$payroll->payroll_number}' was marked as paid");

        return redirect()->route('payroll.show', $payroll)->with('success', 'Payroll marked as paid.');
    }

    public function bulkCreate()
    {
        $employees = Employee::where('status', 'active')->get();

        return view('payroll.bulk-create', compact('employees'));
    }

    public function bulkStore(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'pay_period_start' => 'required|date',
            'pay_period_end' => 'required|date|after:pay_period_start',
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => Rule::exists('employees', 'id')->where('tenant_id', $tenantId),
        ]);

        DB::beginTransaction();

        try {
            $employees = Employee::whereIn('id', $validated['employee_ids'])->get();

            foreach ($employees as $employee) {
                $basicSalary = $employee->salary ?? 0;
                $grossSalary = $basicSalary;
                $netSalary = $grossSalary;

                Payroll::create([
                    'tenant_id' => $tenantId,
                    'payroll_number' => Payroll::generateNumber($tenantId),
                    'employee_id' => $employee->id,
                    'pay_period_start' => $validated['pay_period_start'],
                    'pay_period_end' => $validated['pay_period_end'],
                    'pay_date' => $validated['pay_period_end'],
                    'basic_salary' => $basicSalary,
                    'allowances' => 0,
                    'overtime_hours' => 0,
                    'overtime_amount' => 0,
                    'gross_salary' => $grossSalary,
                    'tax_deduction' => 0,
                    'other_deductions' => 0,
                    'total_deductions' => 0,
                    'net_salary' => $netSalary,
                    'status' => 'draft',
                    'created_by' => auth()->id(),
                ]);
            }

            DB::commit();

            return redirect()->route('payroll.index')->with('success', count($employees).' payroll records created.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->withErrors(['error' => 'Failed to create payroll records.']);
        }
    }

    public function generateForm()
    {
        $currentMonth = now()->format('Y-m');
        $employeesWithStructures = Employee::where('status', 'active')
            ->whereNotNull('salary_structure_id')
            ->with(['salaryStructure.items', 'department', 'designation'])
            ->get();

        return view('payroll.generate', compact('employeesWithStructures', 'currentMonth'));
    }

    public function generate(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'month' => 'required|date_format:Y-m',
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => Rule::exists('employees', 'id')->where('tenant_id', $tenantId),
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'employer_contributions' => 'nullable|array',
            'employer_contributions.*.name' => 'required_with:employer_contributions|string',
            'employer_contributions.*.type' => 'required_with:employer_contributions|in:fixed,percentage',
            'employer_contributions.*.rate' => 'required_with:employer_contributions|numeric|min:0',
            'employer_contributions.*.cap' => 'nullable|numeric|min:0',
        ]);

        $payPeriodStart = \Carbon\Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();
        $payPeriodEnd = $payPeriodStart->copy()->endOfMonth();
        $flatTaxRate = $validated['tax_rate'] ?? 0;
        $employerContributionRules = $validated['employer_contributions'] ?? [];

        // Check for duplicate payroll in the same month
        $existingPayrolls = Payroll::whereIn('employee_id', $validated['employee_ids'])
            ->where('pay_period_start', $payPeriodStart->toDateString())
            ->where('pay_period_end', $payPeriodEnd->toDateString())
            ->pluck('employee_id')
            ->toArray();

        if (! empty($existingPayrolls)) {
            $existingNames = Employee::whereIn('id', $existingPayrolls)->get()
                ->map(fn ($e) => $e->full_name)->implode(', ');

            return back()->withInput()->withErrors([
                'employee_ids' => "Payroll already exists for: {$existingNames} in this period.",
            ]);
        }

        $taxService = app(PayrollTaxService::class);

        DB::beginTransaction();

        try {
            // Create the batch
            $batch = PayrollBatch::create([
                'tenant_id' => $tenantId,
                'batch_number' => PayrollBatch::generateNumber($tenantId),
                'pay_period_start' => $payPeriodStart->toDateString(),
                'pay_period_end' => $payPeriodEnd->toDateString(),
                'tax_rate' => $flatTaxRate,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            $created = 0;
            $employeeIds = $validated['employee_ids'];

            // Process in chunks to limit salary data exposure in memory
            foreach (array_chunk($employeeIds, 10) as $chunk) {
                $employees = Employee::whereIn('id', $chunk)
                    ->with(['salaryStructure.items'])
                    ->get();

                foreach ($employees as $employee) {
                    $structure = $employee->salaryStructure;
                    if (! $structure) {
                        continue;
                    }

                    $basicSalary = $structure->basic_salary;
                    $allowanceDetails = [];
                    $totalAllowances = 0;

                    foreach ($structure->allowances as $item) {
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

                    foreach ($structure->deductions as $item) {
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

                    // Progressive tax calculation with flat-rate fallback
                    $taxResult = $taxService->calculateTax(max(0, $taxableAmount), $tenantId, $flatTaxRate, 'monthly');
                    $taxDeduction = $taxResult['tax'];

                    // Include active loan/advance deductions
                    $activeLoans = \App\Models\EmployeeLoan::getActiveDeductionsForEmployee(
                        $employee->id,
                        $payPeriodEnd->toDateString()
                    );
                    foreach ($activeLoans as $loan) {
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
                    $employerResult = $taxService->calculateEmployerContributions($grossSalary, $employerContributionRules);

                    Payroll::create([
                        'tenant_id' => $tenantId,
                        'payroll_batch_id' => $batch->id,
                        'payroll_number' => Payroll::generateNumber($tenantId),
                        'employee_id' => $employee->id,
                        'salary_structure_id' => $structure->id,
                        'salary_structure_snapshot' => $structure->toSnapshot(),
                        'pay_period_start' => $payPeriodStart->toDateString(),
                        'pay_period_end' => $payPeriodEnd->toDateString(),
                        'pay_date' => $payPeriodEnd->toDateString(),
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
                        'status' => 'draft',
                        'created_by' => auth()->id(),
                    ]);

                    $created++;

                    // Clear sensitive salary variables after each employee
                    unset($basicSalary, $grossSalary, $netSalary, $taxableAmount);
                    unset($allowanceDetails, $deductionDetails, $taxResult, $employerResult);
                    unset($totalAllowances, $totalOtherDeductions, $totalDeductions);
                    unset($taxDeduction, $structure);
                }

                // Release chunk from memory before loading next
                unset($employees);
            }

            // Update batch totals
            $batch->recalculateTotals();

            DB::commit();

            return redirect()->route('payroll-batches.show', $batch)
                ->with('success', "{$created} payroll records generated for ".$payPeriodStart->format('F Y').'.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->withErrors(['error' => 'Failed to generate payroll: '.$e->getMessage()]);
        }
    }

    // ─── Payroll Batch Methods ───

    public function showBatch(Request $request, PayrollBatch $payrollBatch)
    {
        abort_unless($payrollBatch->tenant_id === auth()->user()->tenant_id, 403);

        $payrollBatch->load(['createdBy', 'approvedBy']);

        $query = $payrollBatch->payrolls()
            ->with(['employee.department', 'employee.designation']);

        if ($search = $request->input('search')) {
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            if (in_array($status, ['draft', 'approved', 'paid', 'cancelled'])) {
                $query->where('status', $status);
            }
        }

        if ($department = $request->input('department')) {
            $query->whereHas('employee', function ($q) use ($department) {
                $q->where('department_id', $department);
            });
        }

        $perPage = in_array($request->input('per_page'), [10, 15, 25, 50]) ? (int) $request->input('per_page') : 15;
        $payrolls = $query->paginate($perPage)->withQueryString();

        $departments = Department::orderBy('name')->pluck('name', 'id');

        return view('payroll.batch-show', compact('payrollBatch', 'payrolls', 'departments'));
    }

    public function approveBatch(PayrollBatch $payrollBatch)
    {
        abort_unless($payrollBatch->tenant_id === auth()->user()->tenant_id, 403);

        if ($payrollBatch->status !== 'draft') {
            return redirect()->route('payroll-batches.show', $payrollBatch)
                ->with('error', 'Only draft batches can be approved.');
        }

        // Segregation of duties: creator cannot approve their own batch
        if ($payrollBatch->created_by === auth()->id()) {
            return redirect()->route('payroll-batches.show', $payrollBatch)
                ->with('error', 'You cannot approve a batch you created. A different user must approve it.');
        }

        DB::beginTransaction();

        try {
            $payrollBatch->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            // One by one, so each payroll's cost is posted (A10).
            foreach ($payrollBatch->payrolls()->where('status', 'draft')->get() as $payroll) {
                $payroll->approve(auth()->id());
            }

            DB::commit();

            return redirect()->route('payroll-batches.show', $payrollBatch)
                ->with('success', 'Payroll batch approved successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();

            return redirect()->route('payroll-batches.show', $payrollBatch)
                ->with('error', collect($e->errors())->flatten()->first() ?? 'Failed to approve batch.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->route('payroll-batches.show', $payrollBatch)
                ->with('error', 'Failed to approve batch.');
        }
    }

    public function markBatchAsPaid(PayrollBatch $payrollBatch)
    {
        abort_unless($payrollBatch->tenant_id === auth()->user()->tenant_id, 403);

        // A failed background run can be tried again (N7)
        if (! in_array($payrollBatch->status, [PayrollBatch::STATUS_APPROVED, PayrollBatch::STATUS_FAILED], true)) {
            return redirect()->route('payroll-batches.show', $payrollBatch)
                ->with('error', 'Only approved batches can be marked as paid.');
        }

        $approvedCount = $payrollBatch->payrolls()->where('status', 'approved')->count();

        // For small batches (≤10), process synchronously for immediate feedback
        if ($approvedCount <= 10) {
            DB::beginTransaction();

            try {
                $payrollBatch->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

                foreach ($payrollBatch->payrolls()->where('status', 'approved')->get() as $payroll) {
                    $payroll->markAsPaid();
                }

                DB::commit();

                return redirect()->route('payroll-batches.show', $payrollBatch)
                    ->with('success', 'Payroll batch marked as paid.');
            } catch (\Illuminate\Validation\ValidationException $e) {
                DB::rollBack();

                return redirect()->route('payroll-batches.show', $payrollBatch)
                    ->with('error', collect($e->errors())->flatten()->first() ?? 'Validation failed while marking batch as paid.');
            } catch (\Exception $e) {
                DB::rollBack();
                report($e);

                return redirect()->route('payroll-batches.show', $payrollBatch)
                    ->with('error', 'Failed to mark batch as paid: '.$e->getMessage());
            }
        }

        // Large batches run in the background. The batch shows "processing"
        // until the job has paid every record; it only becomes "paid" at the
        // end, and "failed" if the job fails (N7).
        $payrollBatch->update([
            'status' => PayrollBatch::STATUS_PROCESSING,
            'paid_at' => null,
            'failure_reason' => null,
        ]);

        ProcessPayrollBatch::dispatch($payrollBatch);

        return redirect()->route('payroll-batches.show', $payrollBatch)
            ->with('success', "Payroll batch queued for processing ({$approvedCount} records). It will show as paid once every record and journal entry is done.");
    }

    public function destroyBatch(PayrollBatch $payrollBatch)
    {
        abort_unless($payrollBatch->tenant_id === auth()->user()->tenant_id, 403);

        if (in_array($payrollBatch->status, [PayrollBatch::STATUS_PAID, PayrollBatch::STATUS_PROCESSING], true)) {
            return redirect()->route('payroll.index')
                ->with('error', 'A paid batch, or one being processed, cannot be deleted.');
        }

        DB::beginTransaction();

        try {
            // Delete all related payroll records
            $payrollBatch->payrolls()->delete();
            $payrollBatch->delete();

            DB::commit();

            return redirect()->route('payroll.index')
                ->with('success', 'Payroll batch deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->route('payroll.index')
                ->with('error', 'Failed to delete batch.');
        }
    }

    public function payslip(Payroll $payroll)
    {
        abort_unless($payroll->tenant_id === auth()->user()->tenant_id, 403);

        $payroll->load('employee.department', 'employee.designation');
        $tenant = auth()->user()->tenant;

        $data = $this->buildPayslipData($payroll, $tenant);

        $pdf = Pdf::loadView('payroll.pdf.payslip', $data);
        $pdf->setPaper('a4', 'portrait');

        $filename = 'payslip_'.$payroll->payroll_number.'_'.now()->format('Y-m-d').'.pdf';

        return $pdf->download($filename);
    }

    public function batchPayslips(PayrollBatch $payrollBatch)
    {
        abort_unless($payrollBatch->tenant_id === auth()->user()->tenant_id, 403);

        $payrollBatch->load('payrolls.employee.department', 'payrolls.employee.designation');
        $tenant = auth()->user()->tenant;

        $payslips = [];
        foreach ($payrollBatch->payrolls as $payroll) {
            $payslips[] = $this->buildPayslipData($payroll, $tenant);
        }

        $data = [
            'payslips' => $payslips,
            'batchNumber' => $payrollBatch->batch_number,
        ];

        $pdf = Pdf::loadView('payroll.pdf.payslip-batch', $data);
        $pdf->setPaper('a4', 'portrait');

        $filename = 'payslips_batch_'.$payrollBatch->batch_number.'_'.now()->format('Y-m-d').'.pdf';

        return $pdf->download($filename);
    }

    private function buildPayslipData(Payroll $payroll, $tenant): array
    {
        $companyLogo = null;
        if ($tenant->logo && Storage::disk('public')->exists($tenant->logo)) {
            $logoPath = Storage::disk('public')->path($tenant->logo);
            $logoData = base64_encode(file_get_contents($logoPath));
            $logoMime = mime_content_type($logoPath);
            $companyLogo = "data:{$logoMime};base64,{$logoData}";
        }

        return [
            'payroll' => $payroll,
            'employee' => $payroll->employee,
            'companyName' => $tenant->name ?? config('app.name'),
            'companyEmail' => $tenant->email ?? '',
            'companyPhone' => $tenant->phone ?? '',
            'companyAddress' => $tenant->address ?? '',
            'companyLogo' => $companyLogo,
            'generatedAt' => now()->format('F j, Y g:i A'),
        ];
    }

    /**
     * Export bank file for a payroll batch in the specified format.
     */
    public function exportBankFile(PayrollBatch $payrollBatch, BankFileExportService $exportService)
    {
        abort_unless($payrollBatch->tenant_id === auth()->user()->tenant_id, 403);

        if (! in_array($payrollBatch->status, ['approved', 'paid'])) {
            return redirect()->route('payroll-batches.show', $payrollBatch)
                ->with('error', 'Bank file can only be exported for approved or paid batches.');
        }

        $format = request()->input('format', 'csv');

        try {
            $result = $exportService->exportBatch($payrollBatch, $format);

            return response($result['content'])
                ->header('Content-Type', $result['mime_type'])
                ->header('Content-Disposition', 'attachment; filename="'.$result['filename'].'"');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('payroll-batches.show', $payrollBatch)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Show form for applying a statutory tax template.
     */
    public function taxTemplates()
    {
        $templates = \App\Models\StatutoryTaxTemplate::orderBy('country_code')
            ->orderByDesc('tax_year')
            ->get()
            ->groupBy('country_code');

        $currentBrackets = \App\Models\TaxBracket::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('payroll.tax-templates', compact('templates', 'currentBrackets'));
    }

    /**
     * Apply a statutory tax template to the current tenant.
     */
    public function applyTaxTemplate(Request $request)
    {
        $validated = $request->validate([
            'template_id' => 'required|exists:statutory_tax_templates,id',
        ]);

        $template = \App\Models\StatutoryTaxTemplate::findOrFail($validated['template_id']);
        $tenantId = auth()->user()->tenant_id;

        $created = $template->applyToTenant($tenantId);

        return redirect()->route('payroll.tax-templates')
            ->with('success', "{$template->name} applied successfully. {$created} tax brackets created.");
    }

    /**
     * Calculate retroactive pay adjustments for an employee when salary structure changes.
     */
    public function retroactiveAdjustment(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'effective_from' => 'required|date',
            'recalculate' => 'nullable|boolean',
        ]);

        $employee = Employee::with('salaryStructure.items')->findOrFail($validated['employee_id']);
        $effectiveFrom = $validated['effective_from'];
        $doRecalculate = $validated['recalculate'] ?? false;

        // Find payrolls in the affected period that used an older salary structure
        $affectedPayrolls = Payroll::where('employee_id', $employee->id)
            ->where('pay_period_start', '>=', $effectiveFrom)
            ->whereIn('status', ['paid', 'approved'])
            ->orderBy('pay_period_start')
            ->get();

        if ($affectedPayrolls->isEmpty()) {
            return back()->with('info', 'No payroll records found in the affected period.');
        }

        $adjustments = [];
        $structure = $employee->salaryStructure;

        if (! $structure) {
            return back()->with('error', 'Employee has no active salary structure.');
        }

        $taxService = app(PayrollTaxService::class);

        foreach ($affectedPayrolls as $payroll) {
            // Calculate what the payroll should have been with the current structure
            $newBasic = $structure->basic_salary;
            $newAllowances = $structure->calculateAllowances();
            $newGross = $newBasic + $newAllowances + (float) $payroll->overtime_amount;

            $taxableAmount = $newGross;
            foreach ($structure->allowances as $item) {
                if (! $item->is_taxable) {
                    $calculated = $item->amount_type === 'percentage'
                        ? round($newBasic * $item->amount / 100, 2)
                        : $item->amount;
                    $taxableAmount -= $calculated;
                }
            }

            $taxableAmount -= $structure->calculatePreTaxDeductions();

            $taxResult = $taxService->calculateTax(max(0, $taxableAmount), $tenantId, 0, 'monthly');
            $newTax = $taxResult['tax'];

            $newDeductions = $structure->calculateDeductions();
            $newTotalDeductions = $newTax + $newDeductions;
            $newNet = $newGross - $newTotalDeductions;

            $difference = round($newNet - (float) $payroll->net_salary, 2);

            $adjustments[] = [
                'payroll' => $payroll,
                'old_gross' => (float) $payroll->gross_salary,
                'new_gross' => $newGross,
                'old_net' => (float) $payroll->net_salary,
                'new_net' => $newNet,
                'difference' => $difference,
            ];
        }

        $totalAdjustment = collect($adjustments)->sum('difference');

        if ($doRecalculate && $totalAdjustment != 0) {
            // Create an adjustment payroll record for the difference
            $payroll = Payroll::create([
                'tenant_id' => $tenantId,
                'payroll_number' => Payroll::generateNumber($tenantId),
                'employee_id' => $employee->id,
                'salary_structure_id' => $structure->id,
                'salary_structure_snapshot' => $structure->toSnapshot(),
                'pay_period_start' => $affectedPayrolls->first()->pay_period_start,
                'pay_period_end' => $affectedPayrolls->last()->pay_period_end,
                'pay_date' => now()->toDateString(),
                'basic_salary' => $totalAdjustment > 0 ? $totalAdjustment : 0,
                'allowances' => 0,
                'overtime_hours' => 0,
                'overtime_amount' => 0,
                'gross_salary' => abs($totalAdjustment),
                'tax_deduction' => 0,
                'other_deductions' => $totalAdjustment < 0 ? abs($totalAdjustment) : 0,
                'total_deductions' => $totalAdjustment < 0 ? abs($totalAdjustment) : 0,
                'net_salary' => $totalAdjustment,
                'status' => 'draft',
                'notes' => "Retroactive adjustment for period {$affectedPayrolls->first()->pay_period_start->format('M Y')} to {$affectedPayrolls->last()->pay_period_end->format('M Y')} based on salary structure change effective {$effectiveFrom}.",
                'created_by' => auth()->id(),
            ]);

            return redirect()->route('payroll.show', $payroll)
                ->with('success', 'Retroactive adjustment payroll created. Net difference: '.number_format($totalAdjustment, 2));
        }

        return view('payroll.retroactive-preview', compact('employee', 'adjustments', 'totalAdjustment', 'effectiveFrom'));
    }
}
