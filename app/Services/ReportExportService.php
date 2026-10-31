<?php

namespace App\Services;

use App\Support\Csv;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;

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
    /**
     * A money value for a CSV cell.
     *
     * These builders feed the CSV export only — the PDFs render from
     * Blade views with the raw figures — and they used number_format, so
     * every cell went out as "1,234.56". A spreadsheet reads that as
     * text, which is why a column of them could not be totalled: an
     * accounting export nobody can add up.
     *
     * Plain decimals now, and the accounting brackets that meant a
     * negative are written as one, so the column sums to what the report
     * says.
     */
    protected function money(float|int|string|null $value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals, '.', '');
    }

    /** @param  iterable<array<int, mixed>|object>  $rows  an array, or rows made as they are written (a large payroll export, audit MB-45) */
    public function exportToCsv(iterable $rows, array $headers = [])
    {
        $filename = str_replace(' ', '_', strtolower($this->reportTitle ?? 'report')).'_'.now()->format('Y-m-d').'.csv';

        $callback = function () use ($rows, $headers) {
            $file = fopen('php://output', 'w');

            // Add BOM for Excel UTF-8 compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Write headers
            if (! empty($headers)) {
                Csv::writeRow($file, $headers);
            }

            // Write data rows
            foreach ($rows as $row) {
                Csv::writeRow($file, is_array($row) ? $row : (array) $row);
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
                ['Revenue', $this->money($data['revenue'], 2)],
                ['Less: Cost of Goods Sold', $this->money(-$data['costOfGoodsSold'])],
                ['Gross Profit', $this->money($data['grossProfit'], 2)],
                ['Less: Operating Expenses', $this->money(-$data['operatingExpenses'])],
                ['Less: Salaries & Wages', $this->money(-$data['payroll'])],
                ['Total Expenses', $this->money(-$data['totalExpenses'])],
                ['Net '.($data['netProfit'] >= 0 ? 'Profit' : 'Loss'), $this->money($data['netProfit'], 2)],
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
                ['Assets', 'Accounts Receivable', $this->money($data['accountsReceivable'], 2)],
                ['Assets', 'Total Assets', $this->money($data['accountsReceivable'], 2)],
                ['Liabilities', 'Accounts Payable', $this->money($data['accountsPayable'], 2)],
                ['Equity', 'Retained Earnings', $this->money($equity, 2)],
                ['', 'Total Liabilities & Equity', $this->money($data['accountsPayable'] + $equity, 2)],
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
                ['Payments Received', $this->money($data['paymentsReceived'], 2)],
                ['Total Cash Inflows', $this->money($data['totalInflows'], 2)],
                ['', ''],
                ['Cash Outflows', ''],
                ['Payments Made', $this->money($data['paymentsMade'], 2)],
                ['Expenses Paid', $this->money($data['expensesPaid'], 2)],
                ['Payroll Paid', $this->money($data['payrollPaid'], 2)],
                ['Total Cash Outflows', $this->money($data['totalOutflows'], 2)],
                ['', ''],
                ['Net Cash Flow', $this->money($data['netCashFlow'], 2)],
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
                $account->balance_debit > 0 ? $this->money($account->balance_debit, 2) : '',
                $account->balance_credit > 0 ? $this->money($account->balance_credit, 2) : '',
            ];
        }

        $rows[] = ['', 'Totals', '', $this->money($totalDebits, 2), $this->money($totalCredits, 2)];

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
                $entry->debit > 0 ? $this->money($entry->debit, 2) : '',
                $entry->credit > 0 ? $this->money($entry->credit, 2) : '',
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
                $this->money($invoice->total, 2),
                $this->money($invoice->balance_due, 2),
                $invoice->status,
            ];
        }

        return [
            'headers' => ['Invoice #', 'Customer', 'Invoice Date', 'Due Date', 'Total', 'Balance Due', 'Status'],
            'rows' => $rows,
            'summary' => [
                'Current' => $this->money($agingData['current'], 2),
                '1-30 Days' => $this->money($agingData['days30'], 2),
                '31-60 Days' => $this->money($agingData['days60'], 2),
                '61-90 Days' => $this->money($agingData['days90'], 2),
                '91-120 Days' => $this->money($agingData['days120'], 2),
                'Over 120 Days' => $this->money($agingData['over120'], 2),
                'Total' => $this->money($agingData['total'], 2),
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
                $this->money($bill->total, 2),
                $this->money($bill->balance_due, 2),
                $bill->status,
            ];
        }

        return [
            'headers' => ['Bill #', 'Vendor', 'Bill Date', 'Due Date', 'Total', 'Balance Due', 'Status'],
            'rows' => $rows,
            'summary' => [
                'Current' => $this->money($agingData['current'], 2),
                '1-30 Days' => $this->money($agingData['days30'], 2),
                '31-60 Days' => $this->money($agingData['days60'], 2),
                '61-90 Days' => $this->money($agingData['days90'], 2),
                '91-120 Days' => $this->money($agingData['days120'], 2),
                'Over 120 Days' => $this->money($agingData['over120'], 2),
                'Total' => $this->money($agingData['total'], 2),
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
                $this->money($customer->invoices_sum_total ?? 0, 2),
                $this->money($customer->invoices_sum_amount_paid ?? 0, 2),
                $this->money(($customer->invoices_sum_total ?? 0) - ($customer->invoices_sum_amount_paid ?? 0), 2),
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
                $this->money($item->selling_price, 2),
                $this->money($item->total_sales, 2),
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
                $this->money($vendor->bills_sum_total ?? 0, 2),
                $this->money($vendor->bills_sum_amount_paid ?? 0, 2),
                $this->money(($vendor->bills_sum_total ?? 0) - ($vendor->bills_sum_amount_paid ?? 0), 2),
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
                $this->money($item->cost_price, 2),
                $this->money($item->stock_value, 2),
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
                $record['employee'] ? $record['employee']->first_name.' '.$record['employee']->last_name : 'Employee removed',
                $record['employee']->employee_id ?? '',
                $record['count'],
                $this->money($record['gross'], 2),
                $this->money($record['deductions'], 2),
                $this->money($record['net'], 2),
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
                $this->money($record['gross'], 2),
                $this->money($record['allowances'], 2),
                $this->money($record['overtime'], 2),
                $this->money($record['tax'], 2),
                $this->money($record['deductions'], 2),
                $this->money($record['net'], 2),
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
        // Made one at a time as the file is written: $payrolls may be a
        // LazyCollection read in chunks (audit MB-45).
        $rows = LazyCollection::make(function () use ($payrolls) {
            foreach ($payrolls as $payroll) {
                yield [
                    ($payroll->employee->first_name ?? '').' '.($payroll->employee->last_name ?? ''),
                    $payroll->employee->department->name ?? 'N/A',
                    $payroll->pay_date->format('Y-m-d'),
                    $this->money($payroll->basic_salary, 2),
                    $this->money($payroll->allowances, 2),
                    $this->money($payroll->overtime_amount, 2),
                    $this->money($payroll->gross_salary, 2),
                    $this->money($payroll->tax_deduction, 2),
                    $this->money($payroll->total_deductions, 2),
                    $this->money($payroll->net_salary, 2),
                ];
            }
        });

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
        // Made one at a time as the file is written: $payrolls may be a
        // LazyCollection read in chunks (audit MB-45).
        $rows = LazyCollection::make(function () use ($payrolls) {
            foreach ($payrolls as $payroll) {
                yield [
                    $payroll->payroll_number,
                    ($payroll->employee->first_name ?? '').' '.($payroll->employee->last_name ?? ''),
                    $payroll->employee->department->name ?? 'N/A',
                    $this->money($payroll->basic_salary, 2),
                    $this->money($payroll->allowances, 2),
                    $this->money($payroll->overtime_amount, 2),
                    $this->money($payroll->gross_salary, 2),
                    $this->money($payroll->tax_deduction, 2),
                    $this->money($payroll->other_deductions, 2),
                    $this->money($payroll->total_deductions, 2),
                    $this->money($payroll->net_salary, 2),
                    $this->money($payroll->employer_contributions, 2),
                    ucfirst($payroll->status),
                ];
            }
        });

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
                $this->money($record['ytd_basic'], 2),
                $this->money($record['ytd_allowances'], 2),
                $this->money($record['ytd_overtime'], 2),
                $this->money($record['ytd_gross'], 2),
                $this->money($record['ytd_tax'], 2),
                $this->money($record['ytd_total_deductions'], 2),
                $this->money($record['ytd_net'], 2),
                $this->money($record['ytd_employer_contributions'], 2),
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
                $this->money($record['taxable_income'], 2),
                $this->money($record['tax_deducted'], 2),
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
                $this->money($record['gross_salary'], 2),
                $this->money($record['employer_contributions'], 2),
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
        // Made one at a time as the file is written: $payrolls may be a
        // LazyCollection read in chunks (audit MB-45).
        $rows = LazyCollection::make(function () use ($payrolls) {
            foreach ($payrolls as $payroll) {
                yield [
                    $payroll->payroll_number,
                    ($payroll->employee->first_name ?? '').' '.($payroll->employee->last_name ?? ''),
                    $payroll->employee->employee_id ?? '',
                    '****'.substr($payroll->employee->bank_account_number ?? '0000', -4),
                    $payroll->employee->bank_name ?? 'N/A',
                    ucfirst(str_replace('_', ' ', $payroll->payment_method ?? 'N/A')),
                    $this->money($payroll->net_salary, 2),
                    ucfirst($payroll->status),
                ];
            }
        });

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
                $version->effective_date ? Carbon::parse($version->effective_date)->format('Y-m-d') : 'N/A',
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
