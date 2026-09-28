<?php

namespace App\Services\BankFileExporters;

use App\Contracts\BankFileExporter;
use Illuminate\Support\Collection;

/**
 * NACHA (ACH) file format for US bank transfers.
 * Generates a Balanced ACH file with File Header, Batch Header,
 * Entry Detail records, Batch Control, and File Control.
 *
 * Spec: https://www.nacha.org/system/files/2024-01/2024-ACH-Rules-Online-TOC.pdf
 */
class NachaExporter implements BankFileExporter
{
    public function formatName(): string
    {
        return 'NACHA (ACH)';
    }

    public function extension(): string
    {
        return 'ach';
    }

    public function mimeType(): string
    {
        return 'text/plain';
    }

    public function generate(Collection $disbursements, array $metadata = []): string
    {
        $immediateOrigin = str_pad($metadata['originator_id'] ?? '0000000000', 10);
        $immediateDestination = str_pad($metadata['destination_id'] ?? '0000000000', 10);
        $companyName = str_pad(substr($metadata['company_name'] ?? 'COMPANY', 0, 16), 16);
        $companyId = str_pad($metadata['company_id'] ?? '0000000000', 10);
        $batchDate = $metadata['effective_date'] ?? now()->format('ymd');
        $fileCreationDate = now()->format('ymd');
        $fileCreationTime = now()->format('Hi');

        $lines = [];
        $entryHash = 0;
        $totalDebit = 0;
        $totalCredit = 0;
        $entryCount = 0;

        // 1 - File Header Record
        $lines[] = '1'                                    // Record Type
            .'01'                                        // Priority Code
            .' '.$immediateDestination                 // Immediate Destination (b + 10)
            .' '.$immediateOrigin                      // Immediate Origin (b + 10)
            .$fileCreationDate                           // File Creation Date
            .$fileCreationTime                           // File Creation Time
            .'A'                                         // File ID Modifier
            .'094'                                       // Record Size
            .'10'                                        // Blocking Factor
            .'1'                                         // Format Code
            .str_pad(substr($metadata['dest_name'] ?? 'DEST BANK', 0, 23), 23) // Destination Name
            .str_pad(substr($metadata['origin_name'] ?? 'ORIGIN CO', 0, 23), 23) // Origin Name
            .str_pad($metadata['reference'] ?? '', 8);   // Reference Code

        // 5 - Batch Header Record
        $lines[] = '5'                                    // Record Type
            .'220'                                       // Service Class (220=mixed)
            .$companyName                                // Company Name
            .str_pad('', 20)                             // Company Discretionary Data
            .$companyId                                  // Company Identification
            .'PPD'                                       // Standard Entry Class
            .str_pad('PAYROLL', 10)                      // Company Entry Description
            .$batchDate                                  // Company Descriptive Date
            .$batchDate                                  // Effective Entry Date
            .'   '                                       // Settlement Date (Julian - filled by bank)
            .'1'                                         // Originator Status Code
            .str_pad(substr($immediateOrigin, 0, 8), 8)  // Originating DFI ID
            .'0000001';                                  // Batch Number

        // 6 - Entry Detail Records
        foreach ($disbursements as $record) {
            $routingNumber = preg_replace('/\D/', '', $record['routing_number'] ?? '000000000');
            $checkDigit = substr($routingNumber, -1);
            $transitRouting = str_pad(substr($routingNumber, 0, 8), 8, '0', STR_PAD_LEFT);
            $accountNumber = str_pad(substr($record['account_number'] ?? '', 0, 17), 17);
            $amount = str_pad((int) round((float) $record['amount'] * 100), 10, '0', STR_PAD_LEFT);
            $name = str_pad(substr($record['employee_name'] ?? '', 0, 22), 22);
            $id = str_pad(substr($record['employee_id'] ?? '', 0, 15), 15);
            $entryCount++;

            $entryHash += (int) $transitRouting;
            $totalCredit += (int) round((float) $record['amount'] * 100);

            $lines[] = '6'                                // Record Type
                .'22'                                    // Transaction Code (22=credit checking)
                .$transitRouting                         // Receiving DFI ID
                .$checkDigit                             // Check Digit
                .$accountNumber                          // DFI Account Number
                .$amount                                 // Amount
                .$id                                     // Individual Identification
                .$name                                   // Individual Name
                .'  '                                    // Discretionary Data
                .'0'                                     // Addenda Record Indicator
                .str_pad(substr($immediateOrigin, 0, 8), 8, '0', STR_PAD_LEFT)
                .str_pad($entryCount, 7, '0', STR_PAD_LEFT); // Trace Number
        }

        // 8 - Batch Control Record
        $entryHashStr = str_pad(substr((string) $entryHash, -10), 10, '0', STR_PAD_LEFT);
        $lines[] = '8'                                    // Record Type
            .'220'                                       // Service Class
            .str_pad($entryCount, 6, '0', STR_PAD_LEFT) // Entry/Addenda Count
            .$entryHashStr                               // Entry Hash
            .str_pad($totalDebit, 12, '0', STR_PAD_LEFT)  // Total Debit
            .str_pad($totalCredit, 12, '0', STR_PAD_LEFT) // Total Credit
            .$companyId                                  // Company ID
            .str_pad('', 19)                             // Message Authentication Code
            .str_pad('', 6)                              // Reserved
            .str_pad(substr($immediateOrigin, 0, 8), 8, '0', STR_PAD_LEFT)
            .'0000001';                                  // Batch Number

        // 9 - File Control Record
        $blockCount = (int) ceil((count($lines) + 1) / 10);
        $lines[] = '9'                                    // Record Type
            .'000001'                                    // Batch Count
            .str_pad($blockCount, 6, '0', STR_PAD_LEFT)  // Block Count
            .str_pad($entryCount, 8, '0', STR_PAD_LEFT) // Entry/Addenda Count
            .$entryHashStr                               // Entry Hash
            .str_pad($totalDebit, 12, '0', STR_PAD_LEFT)
            .str_pad($totalCredit, 12, '0', STR_PAD_LEFT)
            .str_pad('', 39);                            // Reserved

        // Pad to block of 10 lines
        while (count($lines) % 10 !== 0) {
            $lines[] = str_pad('', 94, '9');
        }

        return implode("\r\n", $lines)."\r\n";
    }
}
