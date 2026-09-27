<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface BankFileExporter
{
    /**
     * Get the format name (e.g. 'NACHA', 'BACS', 'CSV').
     */
    public function formatName(): string;

    /**
     * Get the file extension.
     */
    public function extension(): string;

    /**
     * Get the content MIME type.
     */
    public function mimeType(): string;

    /**
     * Generate the bank file content from a collection of disbursement records.
     *
     * Each record should have: employee_name, bank_name, account_number,
     * routing_number, amount, reference, currency
     */
    public function generate(Collection $disbursements, array $metadata = []): string;
}
