<?php

namespace App\Services\BankFileExporters;

use App\Contracts\BankFileExporter;
use Illuminate\Support\Collection;

/**
 * Standard bank CSV format for direct upload to bank portals.
 * Most banks accept CSV imports for bulk transfers.
 */
class CsvBankExporter implements BankFileExporter
{
    public function formatName(): string
    {
        return 'Bank CSV';
    }

    public function extension(): string
    {
        return 'csv';
    }

    public function mimeType(): string
    {
        return 'text/csv';
    }

    public function generate(Collection $disbursements, array $metadata = []): string
    {
        $output = fopen('php://temp', 'r+');

        fputcsv($output, [
            'Employee Name',
            'Bank Name',
            'Account Number',
            'Routing/Sort Code',
            'Amount',
            'Currency',
            'Reference',
            'Description',
        ]);

        foreach ($disbursements as $record) {
            fputcsv($output, [
                $record['employee_name'],
                $record['bank_name'] ?? '',
                $record['account_number'],
                $record['routing_number'] ?? '',
                number_format((float) $record['amount'], 2, '.', ''),
                $record['currency'] ?? 'NGN',
                $record['reference'] ?? '',
                $record['description'] ?? 'Salary Payment',
            ]);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }
}
