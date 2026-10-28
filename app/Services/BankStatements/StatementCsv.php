<?php

namespace App\Services\BankStatements;

use Illuminate\Validation\ValidationException;

/**
 * Reads a bank statement exported as CSV (audit MB-08).
 *
 * Banks lay these out differently, so the columns are found by their
 * headings (in the first rows, after any account details above the table):
 * a date; a description or narration; optionally a reference; and either
 * one signed amount or separate money-in and money-out columns; optionally
 * the running balance. Dates are read day first (31/10/2026, 31-Oct-2026,
 * 2026-10-31); amounts may carry thousands separators, a currency sign,
 * brackets or a trailing DR/CR.
 *
 * Rows without a date and an amount (totals, blank or opening-balance rows)
 * are skipped; rows that have them but can't be read are listed as problems.
 */
class StatementCsv
{
    public const MAX_ROWS = 5000;

    /** Header row is looked for in this many rows from the top. */
    private const HEADER_SEARCH = 20;

    /** @var array<string, list<string>> heading words for each field, best first */
    private const HEADINGS = [
        'date' => ['transactiondate', 'transdate', 'txndate', 'date', 'postingdate', 'postdate', 'bookingdate', 'valuedate', 'tranDate'],
        'description' => ['description', 'narration', 'narrative', 'details', 'transactiondetails', 'particulars', 'remarks', 'memo'],
        'reference' => ['reference', 'ref', 'refno', 'referencenumber', 'transactionreference', 'transactionid', 'chequeno', 'chequenumber'],
        'amount' => ['amount', 'transactionamount'],
        'debit' => ['debit', 'debits', 'debitamount', 'withdrawal', 'withdrawals', 'withdrawalamount', 'moneyout', 'paidout', 'dr'],
        'credit' => ['credit', 'credits', 'creditamount', 'deposit', 'deposits', 'depositamount', 'lodgement', 'lodgements', 'moneyin', 'paidin', 'cr'],
        'balance' => ['balance', 'runningbalance', 'closingbalance', 'balanceafter', 'availablebalance'],
    ];

    private const DATE_FORMATS = [
        'd/m/Y', 'd-m-Y', 'd.m.Y', 'Y-m-d', 'Y/m/d', 'd M Y', 'd-M-Y', 'd M, Y', 'd F Y', 'd-M-y', 'd/m/y', 'd-m-y',
        'd/m/Y H:i', 'd/m/Y H:i:s', 'd-m-Y H:i:s', 'Y-m-d H:i:s', 'd-M-Y H:i:s', 'd-M-Y H:i',
    ];

    /**
     * @return array{lines: list<array{date: string, description: ?string, reference: ?string, amount: float, balance: ?float}>, problems: list<string>}
     *
     * @throws ValidationException (key "file") when the columns can't be found or nothing can be read
     */
    public function parse(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'The file could not be read.']);
        }

        try {
            $rows = [];
            while (($row = fgetcsv($handle, null, $this->delimiter($path), '"', '')) !== false) {
                $rows[] = $row;
                if (count($rows) > self::MAX_ROWS + self::HEADER_SEARCH) {
                    throw ValidationException::withMessages(['file' => 'That statement has more than '.number_format(self::MAX_ROWS).' lines. Export a shorter period.']);
                }
            }
        } finally {
            fclose($handle);
        }

        [$headerAt, $columns] = $this->findHeader($rows);

        $lines = [];
        $problems = [];
        foreach (array_slice($rows, $headerAt + 1, null, true) as $index => $row) {
            $rowNumber = $index + 1;
            $cell = fn (string $field) => isset($columns[$field]) ? trim((string) ($row[$columns[$field]] ?? '')) : '';

            $rawDate = $cell('date');
            if (isset($columns['amount'])) {
                $amount = $this->amount($cell('amount'));
            } else {
                $in = $this->amount($cell('credit'));
                $out = $this->amount($cell('debit'));
                $amount = $in === null && $out === null ? null : round(abs($in ?? 0) - abs($out ?? 0), 2);
            }
            if ($rawDate === '' || $amount === null || abs($amount) < 0.005) {
                continue; // blank, totals, opening balance, or nothing moved
            }

            $date = $this->date($rawDate);
            if ($date === null) {
                $problems[] = "Row {$rowNumber}: the date \"".mb_substr($rawDate, 0, 30).'" was not understood.';

                continue;
            }

            $balance = $cell('balance') === '' ? null : $this->amount($cell('balance'));
            $lines[] = [
                'date' => $date,
                'description' => $this->text($cell('description'), 500),
                'reference' => $this->text($cell('reference'), 100),
                'amount' => $amount,
                'balance' => $balance,
            ];
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['file' => $problems[0] ?? 'No transactions were found in that file.']);
        }

        return ['lines' => $lines, 'problems' => $problems];
    }

    /**
     * @param  list<array<int, string|null>>  $rows
     * @return array{0: int, 1: array<string, int>}
     */
    private function findHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, self::HEADER_SEARCH) as $at => $row) {
            $names = array_map(fn ($cell) => $this->key((string) $cell), $row);
            $columns = [];
            foreach (self::HEADINGS as $field => $headings) {
                foreach ($headings as $heading) {
                    $position = array_search(strtolower($heading), $names, true);
                    if ($position !== false && ! in_array($position, $columns, true)) {
                        $columns[$field] = (int) $position;

                        break;
                    }
                }
            }
            $hasAmount = isset($columns['amount']) || isset($columns['debit']) || isset($columns['credit']);
            if (isset($columns['date']) && $hasAmount) {
                return [$at, $columns];
            }
        }

        throw ValidationException::withMessages(['file' => 'Couldn\'t find the columns. The file needs a heading row with a date column and either an amount column or money in and money out columns.']);
    }

    private function key(string $heading): string
    {
        // Strip a UTF-8 byte order mark and anything that isn't a letter or digit.
        $heading = preg_replace('/^\xEF\xBB\xBF/', '', $heading) ?? $heading;

        return strtolower(preg_replace('/[^a-z0-9]/i', '', $heading) ?? '');
    }

    private function delimiter(string $path): string
    {
        $sample = (string) file_get_contents($path, false, null, 0, 4096);

        return substr_count($sample, ';') > substr_count($sample, ',') ? ';' : ',';
    }

    /** "1,234.50", "(1,234.50)", "₦1,234.50 DR", "1.234,50", "-50" → signed float; "" → null. */
    public function amount(string $value): ?float
    {
        $value = trim($value);
        if ($value === '' || $value === '-') {
            return null;
        }
        $negative = false;
        if (preg_match('/^\((.*)\)$/', $value, $m)) {
            $negative = true;
            $value = $m[1];
        }
        if (preg_match('/\s*(DR|CR)\.?$/i', $value, $m)) {
            $negative = $negative || strtoupper($m[1]) === 'DR';
            $value = (string) preg_replace('/\s*(DR|CR)\.?$/i', '', $value);
        }
        $value = (string) preg_replace('/[^0-9.,\-]/', '', $value);
        // The separator that comes last is the decimal point: "4,000.00" and
        // "4.000,00" are both four thousand; a lone ",50" ending is decimal too.
        $comma = strrpos($value, ',');
        $dot = strrpos($value, '.');
        if ($comma !== false && ($dot === false ? preg_match('/^[^,]*,\d{1,2}$/', $value) : $comma > $dot)) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        } else {
            $value = str_replace(',', '', $value);
        }
        if (str_starts_with($value, '-')) {
            $negative = ! $negative;
            $value = ltrim($value, '-');
        }
        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return round(($negative ? -1 : 1) * (float) $value, 2);
    }

    /** Day first; null when not a date. */
    public function date(string $value): ?string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        foreach (self::DATE_FORMATS as $format) {
            $parsed = \DateTimeImmutable::createFromFormat('!'.$format, $value);
            $errors = \DateTimeImmutable::getLastErrors();
            // Warnings mean an impossible date (31/02) was rolled over; refuse it.
            if ($parsed === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                continue;
            }
            if ((int) $parsed->format('Y') >= 1990 && (int) $parsed->format('Y') <= 2100) {
                return $parsed->format('Y-m-d');
            }
        }

        return null;
    }

    private function text(string $value, int $max): ?string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
