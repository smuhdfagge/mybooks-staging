<?php

namespace App\Http\Controllers\Reports;

use App\Models\BillItem;
use App\Models\CreditNoteItem;
use App\Models\InvoiceItem;
use App\Models\SalesReceiptItem;
use App\Services\Accounting\VatReturnForm;
use App\Services\Accounting\VatTreatment;
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

        $return = app(VatReturnForm::class)->build($tenantId, $month);

        return view('reports.vat-return', $return + [
            'treatments' => VatTreatment::labels(),
        ]);
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
