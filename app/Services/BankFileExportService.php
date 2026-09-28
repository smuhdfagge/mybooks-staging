<?php

namespace App\Services;

use App\Contracts\BankFileExporter;
use App\Models\Payroll;
use App\Models\PayrollBatch;
use App\Services\BankFileExporters\CsvBankExporter;
use App\Services\BankFileExporters\NachaExporter;
use Illuminate\Support\Collection;

class BankFileExportService
{
    /**
     * Available export formats.
     */
    protected array $exporters = [
        'csv' => CsvBankExporter::class,
        'nacha' => NachaExporter::class,
    ];

    /**
     * Get available format names.
     */
    public function availableFormats(): array
    {
        return collect($this->exporters)->map(function ($class) {
            $exporter = new $class;

            return [
                'key' => array_search($class, $this->exporters),
                'name' => $exporter->formatName(),
                'extension' => $exporter->extension(),
            ];
        })->values()->toArray();
    }

    /**
     * Build disbursement records from a payroll batch.
     */
    public function buildDisbursementRecords(PayrollBatch $batch): Collection
    {
        $payrolls = $batch->payrolls()
            ->with('employee')
            ->where('status', Payroll::STATUS_PAID)
            ->where('net_salary', '>', 0)
            ->get();

        return $this->buildRecordsFromPayrolls($payrolls);
    }

    /**
     * Build disbursement records from a collection of payrolls.
     */
    public function buildRecordsFromPayrolls(Collection $payrolls): Collection
    {
        return $payrolls->map(function (Payroll $payroll) {
            $employee = $payroll->employee;

            return [
                'employee_id' => $employee->employee_id ?? '',
                'employee_name' => $employee->full_name,
                'bank_name' => $employee->bank_name ?? '',
                'account_number' => $employee->bank_account_number ?? '',
                'routing_number' => $employee->bank_routing_number ?? '',
                'amount' => $payroll->net_salary,
                'currency' => $payroll->currency ?? 'NGN',
                'reference' => $payroll->payroll_number,
                'description' => "Salary {$payroll->pay_period_start->format('M Y')}",
            ];
        });
    }

    /**
     * Generate a bank file for a payroll batch in the specified format.
     */
    public function exportBatch(PayrollBatch $batch, string $format = 'csv', array $metadata = []): array
    {
        $exporter = $this->resolveExporter($format);
        $records = $this->buildDisbursementRecords($batch);

        $metadata = array_merge([
            'company_name' => auth()->user()?->tenant?->name ?? 'Company',
            'effective_date' => $batch->pay_period_end?->format('ymd') ?? now()->format('ymd'),
        ], $metadata);

        return [
            'content' => $exporter->generate($records, $metadata),
            'filename' => "payroll-{$batch->batch_number}-{$format}.{$exporter->extension()}",
            'mime_type' => $exporter->mimeType(),
            'record_count' => $records->count(),
            'total_amount' => $records->sum('amount'),
        ];
    }

    /**
     * Generate a bank file from a collection of payrolls.
     */
    public function exportPayrolls(Collection $payrolls, string $format = 'csv', array $metadata = []): array
    {
        $exporter = $this->resolveExporter($format);
        $records = $this->buildRecordsFromPayrolls($payrolls);

        return [
            'content' => $exporter->generate($records, $metadata),
            'filename' => "payroll-disbursement-{$format}.{$exporter->extension()}",
            'mime_type' => $exporter->mimeType(),
            'record_count' => $records->count(),
            'total_amount' => $records->sum('amount'),
        ];
    }

    protected function resolveExporter(string $format): BankFileExporter
    {
        $class = $this->exporters[$format] ?? null;

        if (! $class) {
            throw new \InvalidArgumentException("Unknown bank file format: {$format}. Available: ".implode(', ', array_keys($this->exporters)));
        }

        return new $class;
    }
}
