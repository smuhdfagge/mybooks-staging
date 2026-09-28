<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class ReportExportService
{
    protected string $reportTitle;

    protected array $reportData;

    protected array $filters;

    protected string $orientation = 'portrait';

    /**
     * Set the report title
     */
    public function setTitle(string $title): self
    {
        $this->reportTitle = $title;

        return $this;
    }

    /**
     * Set the report data
     */
    public function setData(array $data): self
    {
        $this->reportData = $data;

        return $this;
    }

    /**
     * Set the report filters
     */
    public function setFilters(array $filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * Set the page orientation for PDF
     */
    public function setOrientation(string $orientation): self
    {
        $this->orientation = $orientation;

        return $this;
    }

    /**
     * Export to PDF
     */
    public function exportToPdf(string $view, array $data = [])
    {
        $tenant = auth()->user()->tenant;
        $data['reportTitle'] = $this->reportTitle ?? 'Report';
        $data['filters'] = $this->filters ?? [];
        $data['generatedAt'] = now()->format('F j, Y g:i A');
        $data['companyName'] = $tenant->name ?? config('app.name');
        $data['companyEmail'] = $tenant->email ?? '';
        $data['companyPhone'] = $tenant->phone ?? '';
        $data['companyAddress'] = $tenant->address ?? '';
        $data['companyLogo'] = null;

        // Get logo as base64 for PDF embedding
        if ($tenant->logo && Storage::disk('public')->exists($tenant->logo)) {
            $logoPath = Storage::disk('public')->path($tenant->logo);
            $logoData = base64_encode(file_get_contents($logoPath));
            $logoMime = mime_content_type($logoPath);
            $data['companyLogo'] = "data:{$logoMime};base64,{$logoData}";
        }

        $pdf = Pdf::loadView($view, $data);
        $pdf->setPaper('a4', $this->orientation);

        $filename = str_replace(' ', '_', strtolower($this->reportTitle ?? 'report')).'_'.now()->format('Y-m-d').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Export to CSV
     */
    public function exportToCsv(array $rows, array $headers = [])
    {
        $filename = str_replace(' ', '_', strtolower($this->reportTitle ?? 'report')).'_'.now()->format('Y-m-d').'.csv';

        $callback = function () use ($rows, $headers) {
            $file = fopen('php://output', 'w');

            // Add BOM for Excel UTF-8 compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Write headers
            if (! empty($headers)) {
                fputcsv($file, $headers);
            }

            // Write data rows
            foreach ($rows as $row) {
                fputcsv($file, is_array($row) ? $row : (array) $row);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Generate Profit & Loss data for export
     */
    public function profitLossData(array $data): array
    {
        return [
            'headers' => ['Category', 'Amount'],
            'rows' => [
                ['Revenue', number_format($data['revenue'], 2)],
                ['Less: Cost of Goods Sold', '('.number_format($data['costOfGoodsSold'], 2).')'],
                ['Gross Profit', number_format($data['grossProfit'], 2)],
                ['Less: Operating Expenses', '('.number_format($data['operatingExpenses'], 2).')'],
                ['Less: Salaries & Wages', '('.number_format($data['payroll'], 2).')'],
                ['Total Expenses', '('.number_format($data['totalExpenses'], 2).')'],
                ['Net '.($data['netProfit'] >= 0 ? 'Profit' : 'Loss'), number_format($data['netProfit'], 2)],
            ],
        ];
    }

    /**
     * Generate Balance Sheet data for export
     */
    public function balanceSheetData(array $data): array
    {
        $equity = $data['accountsReceivable'] - $data['accountsPayable'];

        return [
            'headers' => ['Category', 'Item', 'Amount'],
            'rows' => [
                ['Assets', 'Accounts Receivable', number_format($data['accountsReceivable'], 2)],
                ['Assets', 'Total Assets', number_format($data['accountsReceivable'], 2)],
                ['Liabilities', 'Accounts Payable', number_format($data['accountsPayable'], 2)],
                ['Equity', 'Retained Earnings', number_format($equity, 2)],
                ['', 'Total Liabilities & Equity', number_format($data['accountsPayable'] + $equity, 2)],
            ],
        ];
    }

    /**
     * Generate Cash Flow data for export
     */
    public function cashFlowData(array $data): array
    {
        return [
            'headers' => ['Category', 'Amount'],
            'rows' => [
                ['Cash Inflows', ''],
                ['Payments Received', number_format($data['paymentsReceived'], 2)],
                ['Total Cash Inflows', number_format($data['totalInflows'], 2)],
                ['', ''],
                ['Cash Outflows', ''],
                ['Payments Made', number_format($data['paymentsMade'], 2)],
                ['Expenses Paid', number_format($data['expensesPaid'], 2)],
                ['Payroll Paid', number_format($data['payrollPaid'], 2)],
                ['Total Cash Outflows', number_format($data['totalOutflows'], 2)],
                ['', ''],
                ['Net Cash Flow', number_format($data['netCashFlow'], 2)],
            ],
        ];
    }

    /**
     * Generate Trial Balance data for export
     */
    public function trialBalanceData($accounts, $totalDebits, $totalCredits): array
    {
        $rows = [];
        $rows[] = ['Account Code', 'Account Name', 'Type', 'Debit', 'Credit'];

        foreach ($accounts as $account) {
            $rows[] = [
                $account->account_code,
                $account->name,
                $account->type,
                $account->total_debit > 0 ? number_format($account->total_debit, 2) : '',
                $account->total_credit > 0 ? number_format($account->total_credit, 2) : '',
            ];
        }

        $rows[] = ['', 'Totals', '', number_format($totalDebits, 2), number_format($totalCredits, 2)];

        return [
            'headers' => array_shift($rows),
            'rows' => $rows,
        ];
    }

    /**
     * Generate General Ledger data for export
     */
    public function generalLedgerData($entries, $selectedAccount): array
    {
        $rows = [];

        foreach ($entries as $entry) {
            $rows[] = [
                $entry->journal->journal_date?->format('Y-m-d'),
                $entry->journal->journal_number,
                $entry->description ?? $entry->journal->description,
                $entry->debit > 0 ? number_format($entry->debit, 2) : '',
                $entry->credit > 0 ? number_format($entry->credit, 2) : '',
            ];
        }

        return [
            'headers' => ['Date', 'Journal #', 'Description', 'Debit', 'Credit'],
            'rows' => $rows,
        ];
    }

    /**
     * Generate Accounts Receivable data for export
     */
    public function accountsReceivableData($invoices, $agingData): array
    {
        $rows = [];

        foreach ($invoices as $invoice) {
            $rows[] = [
                $invoice->invoice_number,
                $invoice->customer->name ?? 'N/A',
                $invoice->invoice_date?->format('Y-m-d'),
                $invoice->due_date?->format('Y-m-d'),
                number_format($invoice->total, 2),
                number_format($invoice->balance_due, 2),
                $invoice->status,
            ];
        }

        return [
            'headers' => ['Invoice #', 'Customer', 'Invoice Date', 'Due Date', 'Total', 'Balance Due', 'Status'],
            'rows' => $rows,
            'summary' => [
                'Current' => number_format($agingData['current'], 2),
                '1-30 Days' => number_format($agingData['days30'], 2),
                '31-60 Days' => number_format($agingData['days60'], 2),
                '61-90 Days' => number_format($agingData['days90'], 2),
                '91-120 Days' => number_format($agingData['days120'], 2),
                'Over 120 Days' => number_format($agingData['over120'], 2),
                'Total' => number_format($agingData['total'], 2),
            ],
        ];
    }

    /**
     * Generate Accounts Payable data for export
     */
    public function accountsPayableData($bills, $agingData): array
    {
        $rows = [];

        foreach ($bills as $bill) {
            $rows[] = [
                $bill->bill_number,
                $bill->vendor->name ?? 'N/A',
                $bill->bill_date?->format('Y-m-d'),
                $bill->due_date?->format('Y-m-d'),
                number_format($bill->total, 2),
                number_format($bill->balance_due, 2),
                $bill->status,
            ];
        }

        return [
            'headers' => ['Bill #', 'Vendor', 'Bill Date', 'Due Date', 'Total', 'Balance Due', 'Status'],
            'rows' => $rows,
            'summary' => [
                'Current' => number_format($agingData['current'], 2),
                '1-30 Days' => number_format($agingData['days30'], 2),
                '31-60 Days' => number_format($agingData['days60'], 2),
                '61-90 Days' => number_format($agingData['days90'], 2),
                '91-120 Days' => number_format($agingData['days120'], 2),
                'Over 120 Days' => number_format($agingData['over120'], 2),
                'Total' => number_format($agingData['total'], 2),
            ],
        ];
    }

    /**
     * Generate Sales by Customer data for export
     */
    public function salesByCustomerData($customers): array
    {
        $rows = [];

        foreach ($customers as $customer) {
            $rows[] = [
                $customer->name,
                $customer->company_name ?? '',
                $customer->invoices_count,
                number_format($customer->invoices_sum_total ?? 0, 2),
                number_format($customer->invoices_sum_amount_paid ?? 0, 2),
                number_format(($customer->invoices_sum_total ?? 0) - ($customer->invoices_sum_amount_paid ?? 0), 2),
            ];
        }

        return [
            'headers' => ['Customer', 'Company', 'Invoices', 'Total Sales', 'Amount Paid', 'Outstanding'],
            'rows' => $rows,
        ];
    }

    /**
     * Generate Sales by Item data for export
     */
    public function salesByItemData($items): array
    {
        $rows = [];

        foreach ($items as $item) {
            $rows[] = [
                $item->name,
                $item->sku ?? '',
                $item->quantity_sold,
                number_format($item->selling_price, 2),
                number_format($item->total_sales, 2),
            ];
        }

        return [
            'headers' => ['Item', 'SKU', 'Qty Sold', 'Unit Price', 'Total Sales'],
            'rows' => $rows,
        ];
    }

    /**
     * Generate Purchase by Vendor data for export
     */
    public function purchaseByVendorData($vendors): array
    {
        $rows = [];

        foreach ($vendors as $vendor) {
            $rows[] = [
                $vendor->name,
                $vendor->company_name ?? '',
                $vendor->bills_count,
                number_format($vendor->bills_sum_total ?? 0, 2),
                number_format($vendor->bills_sum_amount_paid ?? 0, 2),
                number_format(($vendor->bills_sum_total ?? 0) - ($vendor->bills_sum_amount_paid ?? 0), 2),
            ];
        }

        return [
            'headers' => ['Vendor', 'Company', 'Bills', 'Total Purchases', 'Amount Paid', 'Outstanding'],
            'rows' => $rows,
        ];
    }

    /**
     * Generate Inventory Summary data for export
     */
    public function inventorySummaryData($items): array
    {
        $rows = [];

        foreach ($items as $item) {
            $rows[] = [
                $item->name,
                $item->sku ?? '',
                $item->stock_quantity,
                $item->reorder_level,
                number_format($item->cost_price, 2),
                number_format($item->stock_value, 2),
                $item->is_low_stock ? 'Yes' : 'No',
            ];
        }

        return [
            'headers' => ['Item', 'SKU', 'Stock Qty', 'Reorder Level', 'Cost Price', 'Stock Value', 'Low Stock'],
            'rows' => $rows,
        ];
    }

    /**
     * Generate Payroll Summary data for export
     */
    public function payrollSummaryData($payrolls, $byEmployee): array
    {
        $rows = [];

        foreach ($byEmployee as $record) {
            $rows[] = [
                $record['employee']->first_name.' '.$record['employee']->last_name,
                $record['employee']->employee_id ?? '',
                $record['count'],
                number_format($record['gross'], 2),
                number_format($record['deductions'], 2),
                number_format($record['net'], 2),
            ];
        }

        return [
            'headers' => ['Employee', 'Employee ID', 'Pay Periods', 'Gross Salary', 'Deductions', 'Net Salary'],
            'rows' => $rows,
        ];
    }

    /**
     * Generate Payroll by Department data for export
     */
    public function payrollByDepartmentData($byDepartment): array
    {
        $rows = [];

        foreach ($byDepartment as $record) {
            $rows[] = [
                $record['department_name'],
                $record['employee_count'],
                number_format($record['gross'], 2),
                number_format($record['allowances'], 2),
                number_format($record['overtime'], 2),
                number_format($record['tax'], 2),
                number_format($record['deductions'], 2),
                number_format($record['net'], 2),
            ];
        }

        return [
            'headers' => ['Department', 'Employees', 'Gross Salary', 'Allowances', 'Overtime', 'Tax', 'Total Deductions', 'Net Salary'],
            'rows' => $rows,
        ];
    }

    /**
     * Generate Employee Earnings data for export
     */
    public function employeeEarningsData($payrolls): array
    {
        $rows = [];

        foreach ($payrolls as $payroll) {
            $rows[] = [
                ($payroll->employee->first_name ?? '').' '.($payroll->employee->last_name ?? ''),
                $payroll->employee->department->name ?? 'N/A',
                $payroll->pay_date->format('Y-m-d'),
                number_format($payroll->basic_salary, 2),
                number_format($payroll->allowances, 2),
                number_format($payroll->overtime_amount, 2),
                number_format($payroll->gross_salary, 2),
                number_format($payroll->tax_deduction, 2),
                number_format($payroll->total_deductions, 2),
                number_format($payroll->net_salary, 2),
            ];
        }

        return [
            'headers' => ['Employee', 'Department', 'Pay Date', 'Basic Salary', 'Allowances', 'Overtime', 'Gross Salary', 'Tax', 'Total Deductions', 'Net Salary'],
            'rows' => $rows,
        ];
    }

    /**
     * Generate Payroll Register data for export
     */
    public function payrollRegisterData($payrolls): array
    {
        $rows = [];

        foreach ($payrolls as $payroll) {
            $rows[] = [
                $payroll->payroll_number,
                ($payroll->employee->first_name ?? '').' '.($payroll->employee->last_name ?? ''),
                $payroll->employee->department->name ?? 'N/A',
                number_format($payroll->basic_salary, 2),
                number_format($payroll->allowances, 2),
                number_format($payroll->overtime_amount, 2),
                number_format($payroll->gross_salary, 2),
                number_format($payroll->tax_deduction, 2),
                number_format($payroll->other_deductions, 2),
                number_format($payroll->total_deductions, 2),
                number_format($payroll->net_salary, 2),
                number_format($payroll->employer_contributions, 2),
                ucfirst($payroll->status),
            ];
        }

        return [
            'headers' => ['Payroll #', 'Employee', 'Department', 'Basic Salary', 'Allowances', 'Overtime', 'Gross Salary', 'Tax', 'Other Deductions', 'Total Deductions', 'Net Salary', 'Employer Contributions', 'Status'],
            'rows' => $rows,
        ];
    }

    /**
     * Generate YTD Earnings data for export
     */
    public function ytdEarningsData($byEmployee): array
    {
        $rows = [];

        foreach ($byEmployee as $record) {
            $rows[] = [
                $record['employee']->first_name.' '.$record['employee']->last_name,
                $record['employee']->department->name ?? 'N/A',
                $record['pay_periods'],
                number_format($record['ytd_basic'], 2),
                number_format($record['ytd_allowances'], 2),
                number_format($record['ytd_overtime'], 2),
                number_format($record['ytd_gross'], 2),
                number_format($record['ytd_tax'], 2),
                number_format($record['ytd_total_deductions'], 2),
                number_format($record['ytd_net'], 2),
                number_format($record['ytd_employer_contributions'], 2),
            ];
        }

        return [
            'headers' => ['Employee', 'Department', 'Pay Periods', 'YTD Basic', 'YTD Allowances', 'YTD Overtime', 'YTD Gross', 'YTD Tax', 'YTD Deductions', 'YTD Net', 'YTD Employer Contributions'],
            'rows' => $rows,
        ];
    }

    /**
     * Generate Tax Liability Payroll data for export
     */
    public function taxLiabilityPayrollData($byEmployee): array
    {
        $rows = [];

        foreach ($byEmployee as $record) {
            $rows[] = [
                $record['employee']->first_name.' '.$record['employee']->last_name,
                $record['employee']->department->name ?? 'N/A',
                $record['pay_periods'],
                number_format($record['taxable_income'], 2),
                number_format($record['tax_deducted'], 2),
                $record['effective_rate'].'%',
            ];
        }

        return [
            'headers' => ['Employee', 'Department', 'Pay Periods', 'Taxable Income', 'Tax Deducted', 'Effective Rate'],
            'rows' => $rows,
        ];
    }

    /**
     * Generate Employer Contributions data for export
     */
    public function employerContributionsData($byEmployee, $contributionTypes): array
    {
        $rows = [];

        foreach ($byEmployee as $record) {
            $rows[] = [
                $record['employee']->first_name.' '.$record['employee']->last_name,
                $record['employee']->department->name ?? 'N/A',
                number_format($record['gross_salary'], 2),
                number_format($record['employer_contributions'], 2),
                ($record['cost_ratio'] ?? 0).'%',
            ];
        }

        return [
            'headers' => ['Employee', 'Department', 'Gross Salary', 'Employer Contributions', 'Cost Ratio'],
            'rows' => $rows,
        ];
    }

    /**
     * Generate Bank Disbursement data for export
     */
    public function bankDisbursementData($payrolls): array
    {
        $rows = [];

        foreach ($payrolls as $payroll) {
            $rows[] = [
                $payroll->payroll_number,
                ($payroll->employee->first_name ?? '').' '.($payroll->employee->last_name ?? ''),
                $payroll->employee->employee_id ?? '',
                '****'.substr($payroll->employee->bank_account_number ?? '0000', -4),
                $payroll->employee->bank_name ?? 'N/A',
                ucfirst(str_replace('_', ' ', $payroll->payment_method ?? 'N/A')),
                number_format($payroll->net_salary, 2),
                ucfirst($payroll->status),
            ];
        }

        return [
            'headers' => ['Payroll #', 'Employee', 'Employee ID', 'Bank Account', 'Bank Name', 'Payment Method', 'Net Amount', 'Status'],
            'rows' => $rows,
        ];
    }

    /**
     * Generate Salary Revision History data for export
     */
    public function salaryRevisionHistoryData($versions): array
    {
        $rows = [];

        foreach ($versions as $version) {
            $rows[] = [
                $version->salaryStructure->name ?? 'N/A',
                'v'.$version->version_number,
                $version->effective_date ? \Carbon\Carbon::parse($version->effective_date)->format('Y-m-d') : 'N/A',
                $version->changedByUser ? ($version->changedByUser->first_name.' '.$version->changedByUser->last_name) : 'System',
                $version->change_reason ?? '-',
                $version->created_at->format('Y-m-d'),
            ];
        }

        return [
            'headers' => ['Structure', 'Version', 'Effective Date', 'Changed By', 'Change Reason', 'Date'],
            'rows' => $rows,
        ];
    }
}
