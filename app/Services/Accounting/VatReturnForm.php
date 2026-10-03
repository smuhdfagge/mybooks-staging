<?php

namespace App\Services\Accounting;

use App\Models\Bill;
use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceRefund;
use App\Models\SalesReceipt;
use App\Models\TaxRate;
use App\Models\VendorCredit;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The monthly VAT return laid out like the Nigeria Revenue Service (formerly
 * FIRS) VAT Form 002, with its sales and purchases schedules.
 *
 * Line numbers follow Form 002 (headquarters):
 *   10  total sales/income (excluding VAT)       15  total purchases
 *   20  income from sales                         25  less exempt supplies
 *   30  less zero-rated supplies                  35  sales adjustments
 *   40  sales subject to VAT (20-25-30+35)        45  output VAT
 *   50  standard-rated domestic purchases         55  zero-rated domestic purchases
 *   60  domestic purchases (50+55)                65  imported goods
 *   70  purchases subject to input VAT (60+65)    75  input VAT
 *   80  VAT payable/(credit) (45-75)              85  VAT withheld at source
 *   90  automatic/electronic VAT paid             95  net VAT payable/(refundable)
 *   100 VAT credit brought forward                105 VAT credit claimable
 *   110 VAT credit relieved                       115 credit carried forward
 *   120 VAT payable
 * Checked 3 October 2026 against VAT Form 002 as published by FIRS
 * (old.firs.gov.ng/wp-content/uploads/2020/10/VAT-FORM-20-02-20.pdf; line 5,
 * the branch count, is not used). Rev360, which replaced TaxPro-Max on 30
 * April 2026, builds the same return from the sales and purchases sheets
 * of its Excel template. The printed form words lines 10-45 as income
 * "received"; MyBooks reports by tax point (see below), and line 35 is used
 * for credit notes, refunds and cancellations of earlier sales.
 *
 * Where the figures come from:
 *  - Supplies are the lines of every sales document whose journal is dated
 *    in the month (invoices whatever their payment status, cash sales), by
 *    the treatment recorded on each line. MyBooks is on the accrual basis:
 *    VAT is due when the invoice is issued or paid, whichever is first, so
 *    the month of the invoice is used, not the month cash came in.
 *  - Credit notes and refunds reduce supplies and output VAT in the month
 *    they are issued (line 35). So does cancelling a document from an
 *    earlier month. Cancelling one from the same month just takes it out.
 *  - Purchases are bills, expenses and supplier credits. A supplier credit
 *    (purchase return or price reduction) is a negative purchase in the
 *    month it is dated, on the line of its treatment (standard-rated goods
 *    off line 50, zero-rated off 55), and its VAT reduces input VAT, as its
 *    journal reduces the Input VAT account. Supplier credit refunds,
 *    supplier advances, payments (with or without WHT), delivery notes and
 *    quotations post no VAT and are not on the return.
 *  - Output VAT (45) and input VAT (75) are the movements on the ledger's
 *    VAT accounts for the month, the same figures VatReturn::settle()
 *    clears, so the return always agrees with the ledger. The
 *    reconciliation shows how the document schedules add up to them and
 *    lists any VAT posted without a document (manual journals).
 *  - Input VAT: since 1 January 2026 (NTA 2025) VAT on services and fixed
 *    assets is recoverable as well as on goods, when it relates to taxable
 *    (standard or zero-rated) supplies. VAT linked to exempt supplies is
 *    not; where the business has exempt supplies the page shows the
 *    exempt share for the accountant to apportion. The return does not
 *    apportion it automatically.
 *  - Lines 65, 85 and 90 aren't in the ledger and are entered by hand;
 *    line 100 comes from the previous filed return.
 */
class VatReturnForm
{
    /** Form 002 sections and line wording (shortened where the form is long-winded). */
    public const SECTIONS = [
        'A' => ['Sales and purchases', [
            10 => 'Total sales/income for the month (excluding VAT)',
            15 => 'Total purchases for the month',
            20 => 'Income from sales for the month (excluding VAT)',
            25 => 'Less: exempt goods and services included in line 20',
            30 => 'Less: zero-rated goods and services included in line 20',
            35 => 'Sales adjustments (credit notes, refunds, cancellations)',
            40 => 'Sales subject to VAT (line 20 - 25 - 30 + 35)',
        ]],
        'B' => ['Output tax', [
            45 => 'Total output tax',
        ]],
        'C' => ['Input tax', [
            50 => 'Domestic purchases other than zero-rated and exempt',
            55 => 'Domestic purchases of zero-rated goods and services',
            60 => 'Total domestic purchases subject to input tax (line 50 + 55)',
            65 => 'Imported goods for the month',
            70 => 'Total purchases subject to input tax (line 60 + 65)',
            75 => 'Total input tax',
        ]],
        'D' => ['VAT payable', [
            80 => 'VAT payable/(credit) for the month (line 45 - 75)',
            85 => 'Less: VAT deducted at source (by MDAs, oil and gas companies)',
            90 => 'Less: automatic/electronic VAT payment in the month',
            95 => 'Net VAT payable/(refundable) (line 80 - 85 - 90)',
            100 => 'Previous unrelieved VAT credit brought forward',
            105 => 'Total VAT credit claimable',
            110 => 'VAT credit relieved',
            115 => 'Unrelieved VAT credit carried forward',
            120 => 'VAT payable',
        ]],
    ];

    /**
     * A month's return is due by the 21st of the next month (Nigeria Tax
     * Administration Act 2025, as under the VAT Act before it). VAT is
     * paid with the return.
     * Checked 3 October 2026: Form 002 says "not later than 21st day of
     * the month following the month of reporting"; 2026 filing calendars
     * (e.g. taxlytech.com, June 2026) give the same date.
     */
    public const DUE_DAY = 21;

    private const SALES = [Invoice::class, SalesReceipt::class, CreditNote::class, InvoiceRefund::class];

    private const PURCHASES = [Bill::class, Expense::class, VendorCredit::class];

    /** Plain names for the schedules. */
    private const DOCUMENT_LABELS = [
        Invoice::class => 'Invoice',
        SalesReceipt::class => 'Cash sale',
        CreditNote::class => 'Credit note',
        InvoiceRefund::class => 'Refund',
        Bill::class => 'Bill',
        Expense::class => 'Expense',
        VendorCredit::class => 'Supplier credit',
    ];

    public function __construct(private VatReturn $ledger) {}

    /**
     * @param  string  $month  YYYY-MM
     * @param  array{imports?: float|int|string|null, import_vat?: float|int|string|null, vat_withheld?: float|int|string|null, auto_vat_paid?: float|int|string|null}  $manual
     * @return array<string, mixed>
     */
    public function build(int $tenantId, string $month, array $manual = [], float $creditBroughtForward = 0.0): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $from = $start->toDateString();
        $to = $start->copy()->endOfMonth()->toDateString();

        $ledger = $this->ledger->build($tenantId, $from, $to);
        $outputByJournal = $ledger['outputLines']->groupBy('journal_id')->map(fn ($g) => round($g->sum('vat'), 2));
        $inputByJournal = $ledger['inputLines']->groupBy('journal_id')->map(fn ($g) => round($g->sum('vat'), 2));

        $journals = $this->journals($tenantId, $from, $to);
        $documents = $this->documents($journals);

        $sales = collect();
        $purchases = collect();
        foreach ($journals as $journal) {
            $doc = $documents->get($journal->reference_type.'#'.$journal->reference_id);
            if (! $doc) {
                continue;
            }
            $isSale = in_array($journal->reference_type, self::SALES, true);
            $rows = $this->rowsFor($journal, $doc, $from);
            $ledgerVat = ($isSale ? $outputByJournal : $inputByJournal)->get($journal->id, 0.0);
            $rows = $this->matchLedgerVat($rows, $ledgerVat);
            $isSale ? $sales->push(...$rows) : $purchases->push(...$rows);
        }

        $supplies = $sales->where('category', 'supply');
        $adjustments = $sales->where('category', 'adjustment');
        $inScope = fn ($rows) => $rows->filter(fn ($r) => $r->treatment !== VatTreatment::OUT_OF_SCOPE);
        $net = fn ($rows) => Money::sum($rows->pluck('net'));
        $vat = fn ($rows) => Money::sum($rows->pluck('vat'));
        $of = fn ($rows, ?string $t) => $rows->filter(fn ($r) => $r->treatment === $t);

        $standardRate = $this->standardRate($tenantId);
        $L = [];
        $L[20] = $net($inScope($supplies));
        $L[25] = $net($of($supplies, VatTreatment::EXEMPT));
        $L[30] = $net($of($supplies, VatTreatment::ZERO));
        // Adjustments to zero-rated and exempt supplies carry no VAT, so
        // they come off lines 25 and 30 rather than line 35.
        $L[25] = Money::add($L[25], $net($of($adjustments, VatTreatment::EXEMPT)));
        $L[30] = Money::add($L[30], $net($of($adjustments, VatTreatment::ZERO)));
        $L[20] = Money::add($L[20], $net($of($adjustments, VatTreatment::EXEMPT)), $net($of($adjustments, VatTreatment::ZERO)));
        $L[35] = Money::add($net($of($adjustments, VatTreatment::STANDARD)), $net($of($adjustments, null)));
        $L[40] = Money::add(Money::subtract($L[20], $L[25], $L[30]), $L[35]);
        $L[10] = Money::add($L[20], $L[35]);
        $L[45] = round($ledger['totalOutputTax'], 2);

        $L[15] = $net($inScope($purchases));
        $L[50] = $net($of($purchases, VatTreatment::STANDARD));
        $L[55] = $net($of($purchases, VatTreatment::ZERO));
        $L[60] = Money::add($L[50], $L[55]);
        $L[65] = Money::round($manual['imports'] ?? 0);
        $L[70] = Money::add($L[60], $L[65]);
        $L[75] = round($ledger['totalInputTax'], 2);

        $L[80] = Money::subtract($L[45], $L[75]);
        $L[85] = Money::round($manual['vat_withheld'] ?? 0);
        $L[90] = Money::round($manual['auto_vat_paid'] ?? 0);
        $L[95] = Money::subtract($L[80], $L[85], $L[90]);
        $L[100] = Money::round(max(0, $creditBroughtForward));
        if ($L[95] >= 0) {
            $L[105] = $L[100];
            $L[110] = min($L[100], $L[95]);
            $L[115] = Money::subtract($L[100], $L[110]);
            $L[120] = Money::subtract($L[95], $L[110]);
        } else {
            $L[105] = Money::add($L[100], -$L[95]);
            $L[110] = 0.0;
            $L[115] = $L[105];
            $L[120] = 0.0;
        }
        ksort($L);

        $docOutput = $vat($sales);
        $docInput = $vat($purchases);
        $unclassified = $sales->filter(fn ($r) => $r->treatment === null)->values();
        $exemptShare = ($L[20] > 0) ? round($L[25] / $L[20], 4) : 0.0;

        return [
            'month' => $start->format('Y-m'),
            'dueDate' => self::dueDate($start->format('Y-m')),
            'from' => $from,
            'to' => $to,
            'lines' => $L,
            'importVat' => Money::round($manual['import_vat'] ?? 0),
            'standardRate' => $standardRate,
            // Line 45 should be line 40 at the standard rate; a gap means a
            // line charged another rate or VAT was posted by hand.
            'expectedOutputVat' => Money::percent($L[40], $standardRate),
            'outOfScope' => $net($of($sales, VatTreatment::OUT_OF_SCOPE)),
            'salesSchedule' => $supplies->values(),
            'adjustmentsSchedule' => $adjustments->values(),
            'purchasesSchedule' => $purchases->values(),
            'unclassified' => $unclassified,
            'exemptShare' => $exemptShare,
            'reconciliation' => [
                'output' => $this->reconcile($docOutput, $L[45], $ledger['outputLines'], self::SALES),
                'input' => $this->reconcile($docInput, $L[75], $ledger['inputLines'], self::PURCHASES),
            ],
            'ledger' => $ledger,
            'journalIds' => $journals->pluck('id')->merge($ledger['outputLines']->pluck('journal_id'))
                ->merge($ledger['inputLines']->pluck('journal_id'))->unique()->sort()->values()->all(),
        ];
    }

    public static function dueDate(string $month): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay()->addMonthNoOverflow()->day(self::DUE_DAY);
    }

    /**
     * Document journals dated in the month (settlements left out). A
     * cancelled document's original journal and its reversal both count.
     *
     * @return Collection<int, \stdClass>
     */
    private function journals(int $tenantId, string $from, string $to): Collection
    {
        return DB::table('journals')
            ->where('tenant_id', $tenantId)
            ->where('is_posted', true)
            ->whereNull('deleted_at')
            ->whereIn('reference_type', array_merge(self::SALES, self::PURCHASES))
            ->where('journal_date', '>=', $from)
            ->where('journal_date', '<', Carbon::parse($to)->addDay()->toDateString())
            ->where(fn ($q) => $q->whereNull('journal_type')->orWhere('journal_type', '!=', VatReturn::SETTLEMENT))
            ->orderBy('journal_date')->orderBy('id')
            ->get(['id', 'journal_date', 'journal_number', 'reference', 'reference_type', 'reference_id']);
    }

    /** @return Collection<string, mixed> */
    private function documents(Collection $journals): Collection
    {
        $with = [
            Invoice::class => ['customer', 'items'],
            SalesReceipt::class => ['customer', 'items'],
            CreditNote::class => ['customer', 'items', 'invoice'],
            InvoiceRefund::class => ['customer', 'invoice.items'],
            Bill::class => ['vendor', 'items'],
            Expense::class => ['vendor'],
            VendorCredit::class => ['vendor', 'items'],
        ];

        return $journals->groupBy('reference_type')->flatMap(function ($group, $type) use ($with) {
            if (! is_string($type) || ! class_exists($type)) {
                return [];
            }
            $query = $type::withoutGlobalScopes()->with($with[$type]);
            if (in_array(SoftDeletes::class, class_uses_recursive($type), true)) {
                $query->withTrashed();
            }

            return $query->whereIn('id', $group->pluck('reference_id')->unique())->get()
                ->mapWithKeys(fn ($doc) => [$type.'#'.$doc->id => $doc]);
        });
    }

    /**
     * One schedule row per document line, signed by its effect on the month.
     *
     * @return list<object>
     */
    private function rowsFor(object $journal, $doc, string $from): array
    {
        $type = $journal->reference_type;
        $reversal = str_starts_with((string) $journal->reference, 'REV-');

        // +1 adds to supplies/purchases, -1 takes away.
        $sign = in_array($type, [CreditNote::class, InvoiceRefund::class, VendorCredit::class], true) ? -1 : 1;
        if ($reversal) {
            $sign = -$sign;
        }
        $category = 'supply';
        if (in_array($type, [CreditNote::class, InvoiceRefund::class], true)) {
            $category = 'adjustment';
        } elseif ($reversal && in_array($type, self::SALES, true)) {
            // Cancelling a sale from an earlier month is a sales adjustment.
            $original = DB::table('journals')->where('reference_type', $type)->where('reference_id', $journal->reference_id)
                ->where('reference', 'not like', 'REV-%')->value('journal_date');
            $category = $original && $original < $from ? 'adjustment' : 'supply';
        }

        $party = match ($type) {
            Bill::class, Expense::class, VendorCredit::class => $doc->vendor,
            default => $doc->customer,
        };
        $base = [
            'journal_id' => $journal->id,
            'date' => Carbon::parse($journal->journal_date),
            'number' => match ($type) {
                Invoice::class => $doc->invoice_number,
                SalesReceipt::class => $doc->receipt_number,
                CreditNote::class => $doc->credit_note_number,
                InvoiceRefund::class => $doc->refund_number,
                Bill::class => $doc->vendor_bill_number ?: $doc->bill_number,
                Expense::class => $doc->expense_number,
                VendorCredit::class => $doc->vendor_reference ?: $doc->vendor_credit_number,
                default => null,
            } ?: $journal->reference,
            'document' => self::DOCUMENT_LABELS[$type] ?? class_basename($type),
            'document_id' => $doc->id,
            'party' => $party->name ?? ($type === SalesReceipt::class ? 'Walk-in customer' : null),
            'tin' => $this->tin($party),
            'category' => $category,
            'reversal' => $reversal,
        ];

        $lines = $this->documentLines($type, $doc);

        return array_map(fn ($l) => (object) ($base + [
            'line_type' => $l['line_type'],
            'line_id' => $l['line_id'],
            'description' => $l['description'],
            'treatment' => $l['treatment'],
            'net' => Money::round($sign * $l['net']),
            'vat' => Money::round($sign * $l['vat']),
        ]), $lines);
    }

    /**
     * Each line's value after all discounts, and its VAT.
     *
     * @return list<array{line_type: ?string, line_id: ?int, description: ?string, treatment: ?string, net: float, vat: float}>
     */
    private function documentLines(string $type, $doc): array
    {
        if ($type === Expense::class) {
            $hasVat = (float) $doc->tax_amount > 0;

            return [[
                'line_type' => null, 'line_id' => null, 'description' => $doc->name,
                // An expense has no lines: with VAT it is a standard-rated purchase.
                'treatment' => $hasVat ? VatTreatment::STANDARD : null,
                'net' => (float) $doc->amount, 'vat' => (float) $doc->tax_amount,
            ]];
        }

        if ($type === InvoiceRefund::class) {
            $invoice = $doc->invoice;
            if (! $invoice) {
                return [];
            }
            // A refund takes back its share of every line of the invoice.
            $share = (float) $doc->amount / max((float) $invoice->total, 0.01);

            return array_map(fn ($l) => ['net' => $l['net'] * $share, 'vat' => $l['vat'] * $share] + $l,
                $this->documentLines(Invoice::class, $invoice));
        }

        $lineType = match ($type) {
            Invoice::class => 'invoice',
            SalesReceipt::class => 'sales_receipt',
            CreditNote::class => 'credit_note',
            Bill::class => 'bill',
            VendorCredit::class => 'vendor_credit',
            default => null,
        };
        $items = $doc->items->sortBy('id')->values();
        $nets = $items->map(fn ($i) => Money::subtract($i->total, $i->tax_amount))->all();

        // Invoices and cash sales keep the document discount off the lines
        // (bills already have it in each line's total; supplier credits have
        // none): share it out.
        $discount = in_array($type, [Invoice::class, SalesReceipt::class], true) ? (float) $doc->discount_amount : 0.0;
        $shares = ($discount > 0 && $nets) ? Money::allocate($discount, $nets) : array_fill(0, count($nets), 0.0);

        return $items->map(fn ($i, $k) => [
            'line_type' => $lineType,
            'line_id' => $i->id,
            'description' => $i->description,
            'treatment' => $i->vat_treatment,
            'net' => Money::subtract($nets[$k], $shares[$k]),
            'vat' => (float) $i->tax_amount,
        ])->all();
    }

    /**
     * The lines' VAT should add up to what the journal posted. A difference
     * of a naira or less (rounding a refund's share) goes on the largest
     * line; anything bigger is left for the reconciliation to show.
     *
     * @param  list<object>  $rows
     * @return list<object>
     */
    private function matchLedgerVat(array $rows, float $ledgerVat): array
    {
        if (! $rows) {
            return $rows;
        }
        $gap = Money::subtract($ledgerVat, Money::sum(array_map(fn ($r) => $r->vat, $rows)));
        if ($gap != 0.0 && abs($gap) <= 1.0) {
            $largest = collect($rows)->sortByDesc(fn ($r) => abs($r->vat))->keys()->first();
            $rows[$largest]->vat = Money::add($rows[$largest]->vat, $gap);
        }

        return $rows;
    }

    /**
     * Schedules against the ledger: VAT from documents, VAT posted without
     * one (manual journals), and any other difference.
     *
     * @param  Collection<int, object>  $ledgerLines
     * @param  list<class-string>  $documentTypes
     * @return array<string, mixed>
     */
    private function reconcile(float $fromDocuments, float $ledgerTotal, Collection $ledgerLines, array $documentTypes): array
    {
        $other = $ledgerLines->filter(fn ($l) => ! $l->document || ! in_array($l->document::class, $documentTypes, true))->values();
        $otherVat = Money::sum($other->pluck('vat'));
        $unexplained = Money::subtract($ledgerTotal, $fromDocuments, $otherVat);

        return [
            'documents' => $fromDocuments,
            'other' => $otherVat,
            'otherLines' => $other,
            'unexplained' => $unexplained,
            'ledger' => $ledgerTotal,
            'return' => $ledgerTotal,
            'difference' => 0.0,
        ];
    }

    private function tin($party): string
    {
        if (! $party) {
            return '';
        }
        try {
            return trim((string) $party->tax_number);
        } catch (\Throwable) {
            return ''; // unreadable (e.g. encrypted with an old key)
        }
    }

    private function standardRate(int $tenantId): float
    {
        $rate = TaxRate::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('is_active', true)
            ->where('vat_treatment', VatTreatment::STANDARD)->orderByRaw("CASE WHEN code = 'VAT-STD' THEN 0 ELSE 1 END")
            ->value('rate');

        return $rate !== null ? (float) $rate : 7.5;
    }
}
