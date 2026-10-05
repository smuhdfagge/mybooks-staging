<?php

namespace App\Http\Controllers\Reports;

use App\Actions\VatReturns\FileVatReturn;
use App\Actions\VatReturns\ReopenVatReturn;
use App\Models\BillItem;
use App\Models\CreditNoteItem;
use App\Models\InvoiceItem;
use App\Models\SalesReceiptItem;
use App\Models\VatReturnFiling;
use App\Services\Accounting\VatReturnForm;
use App\Services\Accounting\VatTreatment;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The monthly VAT return in the NRS VAT Form 002 layout (see
 * App\Services\Accounting\VatReturnForm).
 */
class VatReturnController extends ReportController
{
    /** Lines entered by hand: 65 imports (and the VAT on them), 85, 90. */
    private const MANUAL_LINES = ['imports', 'import_vat', 'vat_withheld', 'auto_vat_paid'];

    /** Line types the return can classify, and the parent each belongs to. */
    private const LINE_TYPES = [
        'invoice' => [InvoiceItem::class, 'invoice'],
        'sales_receipt' => [SalesReceiptItem::class, 'salesReceipt'],
        'credit_note' => [CreditNoteItem::class, 'creditNote'],
        'bill' => [BillItem::class, 'bill'],
    ];

    public function show(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $month = $this->month($request);

        $return = $this->returnFor($request, $tenantId, $month);

        return view('reports.vat-return', $return + [
            'treatments' => VatTreatment::labels(),
        ]);
    }

    /**
     * PDF laid out like Form 002 with its schedules, or a CSV of the form
     * or one schedule. "sales-upload" is the sales schedule in the column
     * order of the TaxPro-Max sales template: customer name, customer TIN,
     * item/service sold, cost/price, description, VAT status (0 VATable,
     * 1 zero-rated, 2 exempt), one row per line, "0" where the TIN isn't
     * known (taken from the NRS template guidance; check it against the
     * template downloaded from TaxPro-Max before uploading).
     *
     * Checked 3 October 2026: NRS replaced TaxPro-Max with Rev360 on 30 April
     * 2026; VAT is still filed by downloading an Excel template, filling the
     * sales and purchases sheets and uploading it. The codes above are the
     * TaxPro-Max ones (taxaide.com.ng, April 2023). One unofficial Rev360
     * guide (nrsportal.ng, May 2026) lists 1 as exempt and 2 as zero-rated,
     * so the page tells users to check the codes against the template.
     */
    public function export(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $month = $this->month($request);
        $return = $this->returnFor($request, $tenantId, $month);
        $title = "VAT return {$month}";

        if ($request->query('format') !== 'csv') {
            return Pdf::loadView('reports.pdf.vat-return', $return + ['tenant' => auth()->user()->tenant])
                ->setPaper('a4')
                ->download("vat-return-{$month}.pdf");
        }

        $schedule = (string) $request->query('schedule', 'form');
        [$headers, $rows] = match ($schedule) {
            'sales-upload' => $this->taxProMaxSales($return['salesSchedule']),
            'sales' => $this->scheduleRows('Customer', $return['salesSchedule']),
            'adjustments' => $this->scheduleRows('Customer', $return['adjustmentsSchedule']),
            'purchases' => $this->scheduleRows('Supplier', $return['purchasesSchedule']),
            default => $this->formRows($return),
        };

        return $this->exportService->setTitle($title.' '.($schedule === 'form' ? 'form' : $schedule))->exportToCsv($rows, $headers);
    }

    /** @return array{0: list<string>, 1: list<list<mixed>>} */
    private function formRows(array $return): array
    {
        $rows = [];
        foreach (VatReturnForm::SECTIONS as $letter => [$title, $lines]) {
            foreach ($lines as $no => $label) {
                $rows[] = [$letter, $no, $label, $this->amount($return['lines'][$no])];
            }
        }

        return [['Section', 'Line', 'Description', 'Amount (NGN)'], $rows];
    }

    /** @return array{0: list<string>, 1: list<list<mixed>>} */
    private function scheduleRows(string $party, $rows): array
    {
        return [
            [$party, $party.' TIN', 'Invoice number', 'Date', 'Description', 'VAT status', 'Amount excl. VAT (NGN)', 'VAT (NGN)', 'Document'],
            $rows->map(fn ($r) => [
                $r->party, $r->tin, $r->number, $r->date->format('d/m/Y'), $r->description, VatTreatment::label($r->treatment),
                $this->amount($r->net), $this->amount($r->vat), $r->document,
            ])->values()->all(),
        ];
    }

    /**
     * One row per line, net of any cancellation in the same month; lines
     * outside the scope of VAT are left out.
     *
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    private function taxProMaxSales($rows): array
    {
        $lines = $rows->filter(fn ($r) => $r->treatment !== VatTreatment::OUT_OF_SCOPE)
            ->groupBy(fn ($r) => $r->document.'#'.$r->document_id.'#'.($r->line_id ?? $r->description))
            ->map(fn ($g) => [$g->first(), round($g->sum('net'), 2)])
            ->filter(fn ($pair) => abs($pair[1]) >= 0.005);

        return [
            ['Customer Name', 'Customer TIN', 'Item/Service Sold', 'Cost/Price', 'Description', 'VAT Status'],
            $lines->map(fn ($pair) => [
                $pair[0]->party ?? '',
                $pair[0]->tin !== '' ? $pair[0]->tin : '0',
                $pair[0]->description,
                $this->amount($pair[1]),
                "{$pair[0]->document} {$pair[0]->number} of {$pair[0]->date->format('d/m/Y')}",
                VatTreatment::taxProMaxStatus($pair[0]->treatment),
            ])->values()->all(),
        ];
    }

    /** Plain number for spreadsheets: no separators, two decimals. */
    private function amount(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    /**
     * Set the treatment of 0% lines the return couldn't classify. Only
     * lines without VAT, and only to zero-rated, exempt or out of scope
     * (a line with VAT is always standard-rated).
     */
    public function classify(Request $request)
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'lines' => ['required', 'array'],
            'lines.*.type' => ['required', Rule::in(array_keys(self::LINE_TYPES))],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.treatment' => ['nullable', Rule::in(VatTreatment::NO_VAT)],
        ]);

        $tenantId = auth()->user()->tenant_id;
        $updated = 0;
        foreach ($validated['lines'] as $line) {
            if (empty($line['treatment'])) {
                continue;
            }
            [$class, $parent] = self::LINE_TYPES[$line['type']];
            /** @var Model|null $item */
            $item = $class::query()->whereKey($line['id'])
                ->whereHas($parent, fn ($q) => $q->withoutGlobalScopes()->where('tenant_id', $tenantId))
                ->first();
            if (! $item || (float) $item->getAttribute('tax_rate') > 0) {
                continue; // another business's line, or one that charged VAT
            }
            $item->forceFill(['vat_treatment' => $line['treatment']])->saveQuietly();
            $updated++;
        }

        return redirect()->route('reports.vat-return', ['month' => $validated['month']])
            ->with('success', $updated === 1 ? '1 line classified.' : "{$updated} lines classified.");
    }

    /**
     * The month's return. A filed month uses the figures entered when it was
     * filed; otherwise the hand-entered lines come from the query string
     * (the page's "Update" button) and line 100 from the last filed return.
     *
     * @return array<string, mixed>
     */
    private function returnFor(Request $request, int $tenantId, string $month): array
    {
        $filing = VatReturnFiling::where('tenant_id', $tenantId)->where('month', $month)->with(['settlementJournal', 'filedBy'])->first();
        if ($filing) {
            $manual = $filing->manual();
            $broughtForward = (float) $filing->credit_brought_forward;
        } else {
            $manual = [];
            foreach (self::MANUAL_LINES as $key) {
                $value = $request->query($key);
                $manual[$key] = is_numeric($value) ? Money::round(max(0, (float) $value)) : 0.0;
            }
            $broughtForward = VatReturnFiling::creditBroughtForward($tenantId, $month);
        }

        $return = app(VatReturnForm::class)->build($tenantId, $month, $manual, $broughtForward);
        $L = $return['lines'];

        return $return + [
            'filing' => $filing,
            'manual' => $manual,
            // Documents dated in a filed month after it was filed.
            'changedSinceFiling' => $filing && (abs($L[45] - (float) $filing->output_vat) >= 0.005 || abs($L[75] - (float) $filing->input_vat) >= 0.005),
        ];
    }

    /**
     * Mark the month as filed: settle its VAT into VAT Payable and keep the
     * figures as filed (FileVatReturn).
     */
    public function file(Request $request, FileVatReturn $file)
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'imports' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'import_vat' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'vat_withheld' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'auto_vat_paid' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $filing = $file->handle(auth()->user()->tenant_id, $validated['month'], $validated, auth()->id(), $validated['reference'] ?? null);
        $label = Carbon::createFromFormat('Y-m-d', $filing->month.'-01')->format('F Y');
        $due = VatReturnForm::dueDate($filing->month)->format('j F Y');
        $message = (float) $filing->vat_payable > 0
            ? "VAT return for {$label} filed. Pay ₦".number_format((float) $filing->vat_payable, 2)." to NRS by {$due}."
            : "VAT return for {$label} filed. No VAT to pay; ₦".number_format((float) $filing->credit_carried_forward, 2).' credit carried forward.';

        return redirect()->route('reports.vat-return', ['month' => $filing->month])->with('success', $message);
    }

    /**
     * Reopen a filed month with a reason (session 11): its settlement is
     * reversed and it can be filed again (ReopenVatReturn).
     */
    public function reopen(Request $request, ReopenVatReturn $reopen)
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'reason.required' => 'Say why the return is being reopened. It is kept in the history.',
        ]);

        $reopen->handle(auth()->user()->tenant_id, $validated['month'], $validated['reason'], auth()->user());
        $label = Carbon::createFromFormat('Y-m-d', $validated['month'].'-01')->format('F Y');

        return redirect()->route('reports.vat-return', ['month' => $validated['month']])
            ->with('success', "VAT return for {$label} reopened. Its settlement journal was reversed; file it again when it is ready.");
    }

    /** YYYY-MM from the request; last month by default (the one usually being filed). */
    protected function month(Request $request): string
    {
        $month = (string) $request->query('month', '');
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return $month;
        }

        return Carbon::now()->subMonthNoOverflow()->format('Y-m');
    }
}
