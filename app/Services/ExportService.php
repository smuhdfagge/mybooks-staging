<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Export;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Journal;
use App\Models\Payroll;
use App\Models\Vendor;
use App\Support\Csv;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ExportService
{
    protected int $tenantId;

    protected array $options;

    protected string $disk = 'exports';

    public function __construct()
    {
        $this->options = [];
    }

    /**
     * Get the storage disk for exports
     */
    protected function storage()
    {
        return Storage::disk($this->disk);
    }

    /**
     * Set tenant for export
     */
    public function forTenant(int $tenantId): self
    {
        $this->tenantId = $tenantId;

        return $this;
    }

    /**
     * Set export options
     */
    public function withOptions(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Process an export
     */
    public function processExport(Export $export): bool
    {
        try {
            $export->update([
                'status' => Export::STATUS_PROCESSING,
                'started_at' => now(),
            ]);

            $this->tenantId = $export->tenant_id;
            $this->options = $export->options ?? [];

            if ($export->type === Export::TYPE_FULL_BACKUP) {
                $result = $this->createFullBackup($export);
            } else {
                $result = $this->createDataExport($export);
            }

            if ($result) {
                $export->update([
                    'status' => Export::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'expires_at' => now()->addDays(7), // Exports expire after 7 days
                ]);

                return true;
            }

            throw new \Exception('Export failed to generate file');
        } catch (\Exception $e) {
            Log::error('Export failed', [
                'export_id' => $export->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $export->update([
                'status' => Export::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            return false;
        }
    }

    /**
     * Create full backup
     */
    protected function createFullBackup(Export $export): bool
    {
        $includedData = $export->included_data ?? [
            'customers', 'vendors', 'items', 'invoices', 'bills',
            'expenses', 'employees', 'payroll', 'journals', 'chart_of_accounts',
            'activity_logs',
        ];

        $backupData = [];

        foreach ($includedData as $dataType) {
            $backupData[$dataType] = $this->getDataForType($dataType);
        }

        $backupData['metadata'] = [
            'exported_at' => now()->toIso8601String(),
            'tenant_id' => $this->tenantId,
            'version' => '1.0',
            'included_data' => $includedData,
        ];

        if ($export->format === Export::FORMAT_ZIP) {
            return $this->createZipBackup($export, $backupData);
        }

        // JSON format
        $filename = 'backup_'.date('Y-m-d_His').'.json';
        $path = $this->tenantId.'/'.$filename;

        $content = json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $this->storage()->put($path, $content);

        $export->update([
            'filename' => $filename,
            'file_path' => $path,
            'file_size' => strlen($content),
        ]);

        return true;
    }

    /**
     * Create ZIP backup with multiple formats
     */
    protected function createZipBackup(Export $export, array $backupData): bool
    {
        $tempDir = storage_path('app/temp/'.uniqid('backup_'));
        mkdir($tempDir, 0755, true);

        try {
            // Create JSON file
            file_put_contents($tempDir.'/backup.json', json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // Create CSV files for each data type
            foreach ($backupData as $type => $data) {
                if ($type === 'metadata' || empty($data)) {
                    continue;
                }
                $this->writeCsvFile($tempDir.'/'.$type.'.csv', $data);
            }

            // Create ZIP archive with password protection
            $filename = 'backup_'.date('Y-m-d_His').'.zip';
            $zipPath = storage_path('app/exports/'.$this->tenantId);

            if (! is_dir($zipPath)) {
                mkdir($zipPath, 0755, true);
            }

            $zipFile = $zipPath.'/'.$filename;
            $zip = new ZipArchive;

            if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \Exception('Cannot create ZIP file');
            }

            // Generate a secure password for the backup
            $backupPassword = $this->generateBackupPassword();

            $files = glob($tempDir.'/*');
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }

            // Set encryption and password on all files in the ZIP
            if ($backupPassword) {
                $zip->setEncryptionIndex(0, ZipArchive::EM_AES_256);
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $zip->setEncryptionIndex($i, ZipArchive::EM_AES_256);
                }
                $zip->setPassword($backupPassword);
            }

            $zip->close();

            // Store the password with the export record (encrypted via Laravel's encrypt helper)
            $export->update([
                'filename' => $filename,
                'file_path' => $this->tenantId.'/'.$filename,
                'file_size' => filesize($zipFile),
                'options' => array_merge($export->options ?? [], [
                    'encrypted' => true,
                    'encryption_method' => 'AES-256',
                    'backup_password' => encrypt($backupPassword),
                ]),
            ]);

            return true;

        } finally {
            // Cleanup temp directory
            $this->deleteDirectory($tempDir);
        }
    }

    /**
     * Generate a random secure password for backup encryption.
     */
    protected function generateBackupPassword(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Create data export for specific type
     */
    protected function createDataExport(Export $export): bool
    {
        $data = $this->getDataForType($export->type);

        if (empty($data)) {
            // Create empty file with headers
            $data = [];
        }

        $filename = $export->type.'_'.date('Y-m-d_His');
        $path = (string) $this->tenantId;

        if (! $this->storage()->exists($path)) {
            $this->storage()->makeDirectory($path);
        }

        switch ($export->format) {
            case Export::FORMAT_CSV:
                return $this->exportToCsv($export, $data, $filename, $path);
            case Export::FORMAT_XLSX:
                return $this->exportToXlsx($export, $data, $filename, $path);
            case Export::FORMAT_JSON:
                return $this->exportToJson($export, $data, $filename, $path);
            case Export::FORMAT_PDF:
                return $this->exportToPdf($export, $data, $filename, $path);
            default:
                throw new \Exception('Unsupported format: '.$export->format);
        }
    }

    /**
     * Get data for export type
     */
    protected function getDataForType(string $type): array
    {
        $dateFrom = $this->options['date_from'] ?? null;
        $dateTo = $this->options['date_to'] ?? null;

        switch ($type) {
            case Export::TYPE_CUSTOMERS:
            case 'customers':
                return Customer::where('tenant_id', $this->tenantId)
                    ->get()
                    ->map(fn ($c) => [
                        'id' => $c->id,
                        'name' => $c->name,
                        'company_name' => $c->company_name,
                        'email' => $c->email,
                        'phone' => $c->phone,
                        'address' => $c->billing_address,
                        'city' => $c->city,
                        'state' => $c->state,
                        'postal_code' => $c->postal_code,
                        'country' => $c->country,
                        'tax_number' => $c->tax_number,
                        'is_active' => $c->is_active ? 'Yes' : 'No',
                        'created_at' => $c->created_at?->format('Y-m-d H:i:s'),
                    ])
                    ->toArray();

            case Export::TYPE_VENDORS:
            case 'vendors':
                return Vendor::where('tenant_id', $this->tenantId)
                    ->get()
                    ->map(fn ($v) => [
                        'id' => $v->id,
                        'name' => $v->name,
                        'company_name' => $v->company_name,
                        'email' => $v->email,
                        'phone' => $v->phone,
                        'address' => $v->address,
                        'city' => $v->city,
                        'state' => $v->state,
                        'postal_code' => $v->postal_code,
                        'country' => $v->country,
                        'tax_number' => $v->tax_number,
                        'is_active' => $v->is_active ? 'Yes' : 'No',
                        'created_at' => $v->created_at?->format('Y-m-d H:i:s'),
                    ])
                    ->toArray();

            case Export::TYPE_ITEMS:
            case 'items':
                return Item::where('tenant_id', $this->tenantId)
                    ->with(['category', 'inventory'])
                    ->get()
                    ->map(fn ($i) => [
                        'id' => $i->id,
                        'name' => $i->name,
                        'sku' => $i->sku,
                        'category' => $i->category?->name,
                        'type' => $i->type,
                        'description' => $i->description,
                        'selling_price' => $i->selling_price,
                        'cost_price' => $i->cost_price,
                        'tax_rate' => $i->effective_tax_rate,
                        'is_taxable' => $i->is_taxable ? 'Yes' : 'No',
                        'track_inventory' => $i->track_inventory ? 'Yes' : 'No',
                        'current_stock' => $i->inventory?->quantity ?? 0,
                        'reorder_level' => $i->reorder_level,
                        'is_active' => $i->is_active ? 'Yes' : 'No',
                        'created_at' => $i->created_at?->format('Y-m-d H:i:s'),
                    ])
                    ->toArray();

            case Export::TYPE_INVOICES:
            case 'invoices':
                $query = Invoice::where('tenant_id', $this->tenantId)
                    ->with(['customer', 'items']);

                if ($dateFrom) {
                    $query->whereDate('invoice_date', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $query->whereDate('invoice_date', '<=', $dateTo);
                }

                return $query->get()
                    ->map(fn ($inv) => [
                        'id' => $inv->id,
                        'invoice_number' => $inv->invoice_number,
                        'customer' => $inv->customer?->name,
                        'invoice_date' => $inv->invoice_date?->format('Y-m-d'),
                        'due_date' => $inv->due_date?->format('Y-m-d'),
                        'subtotal' => $inv->subtotal,
                        'tax_amount' => $inv->tax_amount,
                        'discount_amount' => $inv->discount_amount,
                        'total' => $inv->total,
                        'balance_due' => $inv->balance_due,
                        'status' => $inv->status,
                        'reference' => $inv->reference,
                        'notes' => $inv->notes,
                        'created_at' => $inv->created_at?->format('Y-m-d H:i:s'),
                    ])
                    ->toArray();

            case Export::TYPE_BILLS:
            case 'bills':
                $query = Bill::where('tenant_id', $this->tenantId)
                    ->with(['vendor', 'items']);

                if ($dateFrom) {
                    $query->whereDate('bill_date', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $query->whereDate('bill_date', '<=', $dateTo);
                }

                return $query->get()
                    ->map(fn ($bill) => [
                        'id' => $bill->id,
                        'bill_number' => $bill->bill_number,
                        'vendor' => $bill->vendor?->name,
                        'bill_date' => $bill->bill_date?->format('Y-m-d'),
                        'due_date' => $bill->due_date?->format('Y-m-d'),
                        'subtotal' => $bill->subtotal,
                        'tax_amount' => $bill->tax_amount,
                        'total' => $bill->total,
                        'balance_due' => $bill->balance_due,
                        'status' => $bill->status,
                        'reference' => $bill->vendor_bill_number,
                        'notes' => $bill->notes,
                        'created_at' => $bill->created_at?->format('Y-m-d H:i:s'),
                    ])
                    ->toArray();

            case Export::TYPE_EXPENSES:
            case 'expenses':
                $query = Expense::where('tenant_id', $this->tenantId)
                    ->with(['vendor', 'expenseAccount']);

                if ($dateFrom) {
                    $query->whereDate('expense_date', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $query->whereDate('expense_date', '<=', $dateTo);
                }

                return $query->get()
                    ->map(fn ($exp) => [
                        'id' => $exp->id,
                        'expense_date' => $exp->expense_date?->format('Y-m-d'),
                        'vendor' => $exp->vendor?->name,
                        'expense_account' => $exp->expenseAccount?->name,
                        'amount' => $exp->amount,
                        'tax_amount' => $exp->tax_amount,
                        'total' => $exp->total,
                        'description' => $exp->description,
                        'reference' => $exp->reference,
                        'payment_method' => $exp->payment_method,
                        'is_billable' => $exp->is_billable ? 'Yes' : 'No',
                        'created_at' => $exp->created_at?->format('Y-m-d H:i:s'),
                    ])
                    ->toArray();

            case Export::TYPE_EMPLOYEES:
            case 'employees':
                return Employee::where('tenant_id', $this->tenantId)
                    ->with(['department', 'designation'])
                    ->get()
                    ->map(fn ($emp) => [
                        'id' => $emp->id,
                        'employee_id' => $emp->employee_id,
                        'first_name' => $emp->first_name,
                        'last_name' => $emp->last_name,
                        'email' => $emp->email,
                        'phone' => $emp->phone,
                        'department' => $emp->department?->name,
                        'designation' => $emp->designation?->name,
                        'hire_date' => $emp->hire_date?->format('Y-m-d'),
                        'salary' => $emp->salary,
                        'employment_type' => $emp->employment_type,
                        'status' => $emp->status,
                        'created_at' => $emp->created_at?->format('Y-m-d H:i:s'),
                    ])
                    ->toArray();

            case Export::TYPE_PAYROLL:
            case 'payroll':
                $query = Payroll::where('tenant_id', $this->tenantId)
                    ->with(['employee']);

                if ($dateFrom) {
                    $query->whereDate('pay_date', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $query->whereDate('pay_date', '<=', $dateTo);
                }

                return $query->get()
                    ->map(fn ($pay) => [
                        'id' => $pay->id,
                        'employee' => $pay->employee?->first_name.' '.$pay->employee?->last_name,
                        'pay_period_start' => $pay->pay_period_start?->format('Y-m-d'),
                        'pay_period_end' => $pay->pay_period_end?->format('Y-m-d'),
                        'pay_date' => $pay->pay_date?->format('Y-m-d'),
                        'basic_salary' => $pay->basic_salary,
                        'allowances' => $pay->allowances,
                        'deductions' => $pay->total_deductions,
                        'net_pay' => $pay->net_salary,
                        'status' => $pay->status,
                        'created_at' => $pay->created_at?->format('Y-m-d H:i:s'),
                    ])
                    ->toArray();

            case Export::TYPE_JOURNALS:
            case 'journals':
                $query = Journal::where('tenant_id', $this->tenantId)
                    ->with(['entries.account']);

                if ($dateFrom) {
                    $query->whereDate('journal_date', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $query->whereDate('journal_date', '<=', $dateTo);
                }

                return $query->get()
                    ->map(fn ($j) => [
                        'id' => $j->id,
                        'journal_number' => $j->journal_number,
                        'journal_date' => $j->journal_date?->format('Y-m-d'),
                        'description' => $j->description,
                        'total_debit' => $j->entries->sum('debit'),
                        'total_credit' => $j->entries->sum('credit'),
                        'status' => $j->status,
                        'reference' => $j->reference,
                        'created_at' => $j->created_at?->format('Y-m-d H:i:s'),
                    ])
                    ->toArray();

            case Export::TYPE_CHART_OF_ACCOUNTS:
            case 'chart_of_accounts':
                return ChartOfAccount::where('tenant_id', $this->tenantId)
                    ->orderBy('account_code')
                    ->get()
                    ->map(fn ($acc) => [
                        'id' => $acc->id,
                        'account_code' => $acc->account_code,
                        'name' => $acc->name,
                        'type' => $acc->type,
                        'sub_type' => $acc->sub_type,
                        'description' => $acc->description,
                        'is_active' => $acc->is_active ? 'Yes' : 'No',
                        'is_system' => $acc->is_system ? 'Yes' : 'No',
                        'current_balance' => $acc->current_balance,
                        'created_at' => $acc->created_at?->format('Y-m-d H:i:s'),
                    ])
                    ->toArray();

            case Export::TYPE_ACTIVITY_LOGS:
            case 'activity_logs':
                $query = ActivityLog::where('tenant_id', $this->tenantId)
                    ->with('user')
                    ->orderBy('created_at', 'desc');

                if ($dateFrom) {
                    $query->whereDate('created_at', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $query->whereDate('created_at', '<=', $dateTo);
                }

                return $query->get()
                    ->map(fn ($log) => [
                        'id' => $log->id,
                        'date_time' => $log->created_at?->format('Y-m-d H:i:s'),
                        'user' => $log->user_name ?? 'System',
                        'action' => $log->action_label,
                        'module' => $log->model_type_short ?: '-',
                        'model_id' => $log->model_id,
                        'model_name' => $log->model_name,
                        'description' => $log->description,
                        'ip_address' => $log->ip_address,
                        'user_agent' => $log->user_agent,
                        'old_values' => $log->old_values ? json_encode($log->old_values) : null,
                        'new_values' => $log->new_values ? json_encode($log->new_values) : null,
                        'changed_fields' => $log->changed_fields ? implode(', ', $log->changed_fields) : null,
                    ])
                    ->toArray();

            default:
                return [];
        }
    }

    /**
     * Export to CSV
     */
    protected function exportToCsv(Export $export, array $data, string $filename, string $path): bool
    {
        $fullFilename = $filename.'.csv';
        $fullPath = $path.'/'.$fullFilename;

        $content = $this->arrayToCsv($data);
        $this->storage()->put($fullPath, $content);

        $export->update([
            'filename' => $fullFilename,
            'file_path' => $fullPath,
            'file_size' => strlen($content),
        ]);

        return true;
    }

    /**
     * Export to XLSX (using CSV format as fallback without PhpSpreadsheet)
     */
    protected function exportToXlsx(Export $export, array $data, string $filename, string $path): bool
    {
        // Check if PhpSpreadsheet is available
        if (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            return $this->exportToXlsxWithSpreadsheet($export, $data, $filename, $path);
        }

        // Fallback to CSV with .xlsx extension note
        $fullFilename = $filename.'.csv';
        $fullPath = $path.'/'.$fullFilename;

        $content = $this->arrayToCsv($data);
        $this->storage()->put($fullPath, $content);

        $export->update([
            'filename' => $fullFilename,
            'file_path' => $fullPath,
            'file_size' => strlen($content),
        ]);

        return true;
    }

    /**
     * Export to XLSX with PhpSpreadsheet
     */
    protected function exportToXlsxWithSpreadsheet(Export $export, array $data, string $filename, string $path): bool
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        if (! empty($data)) {
            // Headers
            $headers = array_keys($data[0]);
            $col = 1;
            foreach ($headers as $header) {
                $sheet->setCellValueByColumnAndRow($col, 1, ucwords(str_replace('_', ' ', $header)));
                $col++;
            }

            // Data
            $row = 2;
            foreach ($data as $item) {
                $col = 1;
                foreach ($item as $value) {
                    $sheet->setCellValueByColumnAndRow($col, $row, $value);
                    $col++;
                }
                $row++;
            }
        }

        $fullFilename = $filename.'.xlsx';
        $fullPath = storage_path('app/'.$path.'/'.$fullFilename);

        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($fullPath);

        $export->update([
            'filename' => $fullFilename,
            'file_path' => $path.'/'.$fullFilename,
            'file_size' => filesize($fullPath),
        ]);

        return true;
    }

    /**
     * Export to JSON
     */
    protected function exportToJson(Export $export, array $data, string $filename, string $path): bool
    {
        $fullFilename = $filename.'.json';
        $fullPath = $path.'/'.$fullFilename;

        $content = json_encode([
            'metadata' => [
                'exported_at' => now()->toIso8601String(),
                'type' => $export->type,
                'count' => count($data),
            ],
            'data' => $data,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $this->storage()->put($fullPath, $content);

        $export->update([
            'filename' => $fullFilename,
            'file_path' => $fullPath,
            'file_size' => strlen($content),
        ]);

        return true;
    }

    /**
     * Export to PDF using DomPDF
     */
    protected function exportToPdf(Export $export, array $data, string $filename, string $path): bool
    {
        if (! class_exists('\Barryvdh\DomPDF\Facade\Pdf')) {
            throw new \Exception('DomPDF is not installed. Please install barryvdh/laravel-dompdf.');
        }

        $html = $this->generateHtmlTable($export->type, $data);

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->setOption('isRemoteEnabled', false);

        $pdfContent = $pdf->output();

        // Verify the output is actual PDF binary, not raw HTML
        if (empty($pdfContent) || ! str_starts_with($pdfContent, '%PDF-')) {
            throw new \Exception('PDF rendering failed: DomPDF did not produce valid PDF output.');
        }

        $fullFilename = $filename.'.pdf';
        $fullPath = $path.'/'.$fullFilename;

        $this->storage()->put($fullPath, $pdfContent);

        $export->update([
            'filename' => $fullFilename,
            'file_path' => $fullPath,
            'file_size' => strlen($pdfContent),
        ]);

        return true;
    }

    /**
     * Generate HTML table for PDF export
     */
    protected function generateHtmlTable(string $type, array $data): string
    {
        $title = Export::getExportTypes()[$type] ?? ucwords(str_replace('_', ' ', $type));

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<title>'.$title.' Export</title>';
        $html .= '<style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #333; }
            table { border-collapse: collapse; width: 100%; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #4a5568; color: white; }
            tr:nth-child(even) { background-color: #f2f2f2; }
            .meta { color: #666; font-size: 12px; margin-bottom: 20px; }
        </style></head><body>';

        $html .= '<h1>'.$title.'</h1>';
        $html .= '<p class="meta">Exported on: '.now()->format('F j, Y g:i A').' | Total Records: '.count($data).'</p>';

        if (empty($data)) {
            $html .= '<p>No data available for export.</p>';
        } else {
            $html .= '<table><thead><tr>';

            foreach (array_keys($data[0]) as $header) {
                $html .= '<th>'.ucwords(str_replace('_', ' ', $header)).'</th>';
            }

            $html .= '</tr></thead><tbody>';

            foreach ($data as $row) {
                $html .= '<tr>';
                foreach ($row as $value) {
                    $html .= '<td>'.htmlspecialchars($value ?? '').'</td>';
                }
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
        }

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Convert array to CSV string
     */
    protected function arrayToCsv(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // Write headers
        Csv::writeRow($output, array_keys($data[0]));

        // Write data
        foreach ($data as $row) {
            Csv::writeRow($output, array_values($row));
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Write array to CSV file
     */
    protected function writeCsvFile(string $filepath, array $data): void
    {
        if (empty($data)) {
            file_put_contents($filepath, '');

            return;
        }

        $handle = fopen($filepath, 'w');

        // Write headers
        Csv::writeRow($handle, array_keys($data[0]));

        // Write data
        foreach ($data as $row) {
            Csv::writeRow($handle, array_values($row));
        }

        fclose($handle);
    }

    /**
     * Delete directory recursively
     */
    protected function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    /**
     * Clean up expired exports
     */
    public function cleanupExpiredExports(): int
    {
        $expiredExports = Export::where('expires_at', '<', now())
            ->whereNotNull('file_path')
            ->get();

        $count = 0;

        foreach ($expiredExports as $export) {
            if ($this->storage()->exists($export->file_path)) {
                $this->storage()->delete($export->file_path);
            }
            $export->delete();
            $count++;
        }

        return $count;
    }
}
