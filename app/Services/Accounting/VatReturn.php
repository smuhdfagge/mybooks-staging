<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Services\AccountCodeService;
use App\Services\JournalService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * VAT return from the ledger (finding A5).
 *
 * Output VAT is the movement on the output VAT account (Sales Tax Payable,
 * 2400) in the period; input VAT the movement on the Input VAT account
 * (1410). Every posted document counts: invoices whatever their payment
 * status, cash sales, refunds and credit notes (which reduce output VAT),
 * bills whatever their status, and expenses. A cancelled document's
 * journal and its reversal cancel out. Settlement journals are left out.
 *
 * Each figure can be traced to its journals and documents (lines()), and
 * settle() moves the period's VAT into VAT Payable when the return is filed.
 */
class VatReturn
{
    public const SETTLEMENT = 'vat_settlement';

    /**
     * @return array{outputAccount: string, inputAccount: string, totalOutputTax: float, totalInputTax: float,
     *               netTaxPayable: float, outputLines: Collection<int, mixed>, inputLines: Collection<int, mixed>,
     *               outputTaxByRate: Collection<int, mixed>, inputTaxByRate: Collection<int, mixed>,
     *               totalOutputTaxable: float, totalInputTaxable: float, settlement: ?Journal}
     */
    public function build(int $tenantId, string $from, string $to): array
    {
        $outputCode = AccountCodeService::resolve($tenantId, 'sales_tax_payable');
        $inputCode = AccountCodeService::resolve($tenantId, 'input_vat');

        $outputLines = $this->lines($tenantId, $outputCode, $from, $to, 'credit');
        $inputLines = $this->lines($tenantId, $inputCode, $from, $to, 'debit');

        $outputByRate = $this->byRate($outputLines);
        $inputByRate = $this->byRate($inputLines);

        $totalOutput = round($outputLines->sum('vat'), 2);
        $totalInput = round($inputLines->sum('vat'), 2);

        return [
            'outputAccount' => $outputCode,
            'inputAccount' => $inputCode,
            'totalOutputTax' => $totalOutput,
            'totalInputTax' => $totalInput,
            'netTaxPayable' => round($totalOutput - $totalInput, 2),
            'outputLines' => $outputLines,
            'inputLines' => $inputLines,
            'outputTaxByRate' => $outputByRate,
            'inputTaxByRate' => $inputByRate,
            'totalOutputTaxable' => round($outputByRate->sum('taxable_amount'), 2),
            'totalInputTaxable' => round($inputByRate->sum('taxable_amount'), 2),
            'settlement' => $this->settlementFor($tenantId, $from, $to),
        ];
    }

    /**
     * One row per journal that moved the VAT account: date, document, party,
     * VAT (positive = adds to the side), and the document's net amount.
     *
     * @return Collection<int, mixed>
     */
    public function lines(int $tenantId, string $accountCode, string $from, string $to, string $increasesBy): Collection
    {
        $account = ChartOfAccount::where('tenant_id', $tenantId)->where('account_code', $accountCode)->first();
        if (! $account) {
            return collect();
        }

        $sign = $increasesBy === 'credit' ? 'SUM(je.credit) - SUM(je.debit)' : 'SUM(je.debit) - SUM(je.credit)';

        $rows = DB::table('journal_entries as je')
            ->join('journals as j', 'j.id', '=', 'je.journal_id')
            ->where('je.account_id', $account->id)
            ->where('j.tenant_id', $tenantId)
            ->where('j.is_posted', true)
            ->whereNull('j.deleted_at')
            ->where('j.journal_date', '>=', $from)
            ->where('j.journal_date', '<', Carbon::parse($to)->addDay()->toDateString())
            ->where(fn ($q) => $q->whereNull('j.journal_type')->orWhere('j.journal_type', '!=', self::SETTLEMENT))
            ->groupBy('j.id', 'j.journal_date', 'j.journal_number', 'j.reference', 'j.description', 'j.reference_type', 'j.reference_id')
            ->orderBy('j.journal_date')
            ->orderBy('j.id')
            ->selectRaw("j.id as journal_id, j.journal_date, j.journal_number, j.reference, j.description, j.reference_type, j.reference_id, {$sign} as vat")
            ->get();

        // Load the documents in one query per type.
        $documents = $rows->groupBy('reference_type')->flatMap(function ($group, $type) {
            if (! $type || ! class_exists($type)) {
                return [];
            }
            $with = array_values(array_filter(['customer', 'vendor', 'items', 'invoice.customer'], fn ($r) => method_exists($type, explode('.', $r)[0])));

            $query = $type::withoutGlobalScopes()->with($with);
            if (in_array(SoftDeletes::class, class_uses_recursive($type), true)) {
                $query->withTrashed();
            }

            return $query->whereIn('id', $group->pluck('reference_id')->filter()->unique())->get()
                ->mapWithKeys(fn ($doc) => [$type.'#'.$doc->id => $doc]);
        });

        return $rows->map(function ($row) use ($documents) {
            $doc = $documents->get($row->reference_type.'#'.$row->reference_id);
            $party = data_get($doc, 'customer.name') ?? data_get($doc, 'vendor.name') ?? data_get($doc, 'invoice.customer.name');

            return (object) [
                'journal_id' => $row->journal_id,
                'date' => Carbon::parse($row->journal_date),
                'number' => $row->reference ?: $row->journal_number,
                'type' => $row->reference_type ? class_basename($row->reference_type) : 'Journal',
                'description' => $row->description,
                'party' => $party,
                'vat' => round((float) $row->vat, 2),
                'document' => $doc,
            ];
        })->filter(fn ($l) => abs($l->vat) >= 0.005)->values();
    }

    /**
     * VAT by rate, from each document's lines, scaled to the VAT the ledger
     * actually recorded for it (so reversals and credit notes count against).
     * VAT without rate details (expenses, manual journals) is shown on its own.
     *
     * @param  Collection<int, mixed>  $lines
     * @return Collection<int, mixed>
     */
    protected function byRate(Collection $lines): Collection
    {
        $buckets = [];
        foreach ($lines as $line) {
            $items = data_get($line->document, 'items') ?? collect();
            $docTax = (float) $items->sum('tax_amount');

            if ($docTax <= 0) {
                $key = 'other';
                $buckets[$key] ??= ['tax_rate' => null, 'taxable_amount' => 0.0, 'tax_amount' => 0.0, 'docs' => []];
                $buckets[$key]['tax_amount'] += $line->vat;
                $buckets[$key]['docs'][$line->journal_id] = true;

                continue;
            }

            $share = $line->vat / $docTax; // 1 for the document, -1 for its reversal
            foreach ($items as $item) {
                $rate = (float) $item->tax_rate;
                if ($rate <= 0 || (float) $item->tax_amount == 0.0) {
                    continue;
                }
                $key = number_format($rate, 2);
                $buckets[$key] ??= ['tax_rate' => $rate, 'taxable_amount' => 0.0, 'tax_amount' => 0.0, 'docs' => []];
                $buckets[$key]['tax_amount'] += (float) $item->tax_amount * $share;
                $buckets[$key]['taxable_amount'] += ((float) $item->tax_amount * 100 / $rate) * $share;
                $buckets[$key]['docs'][$line->journal_id] = true;
            }
        }

        return collect($buckets)
            ->map(fn ($b) => (object) [
                'tax_rate' => $b['tax_rate'],
                'taxable_amount' => round($b['taxable_amount'], 2),
                'tax_amount' => round($b['tax_amount'], 2),
                'transaction_count' => count($b['docs']),
            ])
            ->sortBy(fn ($b) => $b->tax_rate ?? PHP_INT_MAX)
            ->values();
    }

    public function settlementFor(int $tenantId, string $from, string $to): ?Journal
    {
        return Journal::where('tenant_id', $tenantId)
            ->where('journal_type', self::SETTLEMENT)
            ->where('reference', $this->settlementReference($from, $to))
            ->where('is_posted', true)
            ->first();
    }

    /**
     * When the return is filed: clear the period's output and input VAT into
     * VAT Payable (or a VAT refund due, if input is larger). Dated the last
     * day of the period. Once per period.
     */
    public function settle(int $tenantId, string $from, string $to): Journal
    {
        if ($this->settlementFor($tenantId, $from, $to)) {
            throw new RuntimeException('This VAT period has already been settled.');
        }

        $return = $this->build($tenantId, $from, $to);
        $output = $return['totalOutputTax'];
        $input = $return['totalInputTax'];
        if (abs($output) < 0.005 && abs($input) < 0.005) {
            throw new RuntimeException('There is no VAT to settle in this period.');
        }

        $payableCode = AccountCodeService::resolve($tenantId, 'vat_payable');
        $service = app(JournalService::class);

        return DB::transaction(function () use ($tenantId, $from, $to, $return, $output, $input, $payableCode, $service) {
            $journal = Journal::create([
                'tenant_id' => $tenantId,
                'journal_number' => Journal::generateNumber($tenantId),
                'journal_date' => $to,
                'reference' => $this->settlementReference($from, $to),
                'description' => "VAT return {$from} to {$to}",
                'journal_type' => self::SETTLEMENT,
                'status' => 'posted',
                'is_posted' => true,
                'posted_at' => now(),
                'created_by' => auth()->id(),
            ]);

            // Clear output VAT (a credit balance) and input VAT (a debit balance).
            $this->entry($service, $journal, $return['outputAccount'], $output, 0, 'Output VAT for the period');
            $this->entry($service, $journal, $return['inputAccount'], 0, $input, 'Input VAT for the period');
            $net = round($output - $input, 2);
            $this->entry($service, $journal, $payableCode, $net < 0 ? -$net : 0, $net > 0 ? $net : 0,
                $net >= 0 ? 'VAT owed for the period' : 'VAT refund due for the period');

            $journal->updateTotals();
            $journal->save();
            $service->updateAccountBalances($journal);

            return $journal;
        });
    }

    private function entry(JournalService $service, Journal $journal, string $code, float $debit, float $credit, string $text): void
    {
        // A negative figure (e.g. more credit notes than sales) flips side.
        if ($debit < 0 || $credit < 0) {
            [$debit, $credit] = [max(0, -$credit), max(0, -$debit)];
        }
        if ($debit >= 0.005 || $credit >= 0.005) {
            $service->createEntry($journal, $code, $debit, $credit, $text);
        }
    }

    private function settlementReference(string $from, string $to): string
    {
        return "VAT-{$from}-{$to}";
    }
}
