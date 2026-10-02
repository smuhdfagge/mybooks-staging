<?php

namespace App\Http\Controllers\WithholdingTax;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PaymentReceived;
use App\Services\Accounting\WhtCredits;
use App\Services\ActivityLogService;
use App\Services\ReportExportService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * WHT credit notes receivable: WHT our customers deducted, by customer and
 * status (outstanding, received, utilised); recording credit notes as they
 * arrive and using them against income tax.
 */
class WhtReceivableController extends Controller
{
    public function index(Request $request)
    {
        $data = $this->report($request);

        return view('withholding-tax.receivable', $data + [
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request, ReportExportService $export)
    {
        $data = $this->report($request);
        $title = 'WHT Credit Notes Receivable';
        ActivityLogService::logExport('wht-receivable', $data['filters']);

        if ($request->get('format') === 'csv') {
            $rows = $data['payments']->map(fn (PaymentReceived $p) => [
                $p->customer->name, $p->customer->tax_number, $p->payment_date->format('Y-m-d'), $p->payment_number,
                $p->invoice?->invoice_number, $p->whtCategory?->name, number_format((float) $p->wht_base, 2, '.', ''),
                number_format((float) $p->wht_rate, 2, '.', ''), number_format((float) $p->wht_amount, 2, '.', ''),
                PaymentReceived::WHT_STATUS_LABELS[$p->whtStatus()] ?? '', $p->wht_credit_note_number, $p->wht_credit_note_date?->format('Y-m-d'),
            ])->all();

            return $export->setTitle($title)->setFilters($data['filters'])->exportToCsv($rows, [
                'Customer', 'Customer TIN', 'Payment date', 'Payment no', 'Invoice', 'Transaction type', 'Amount before VAT',
                'Rate %', 'WHT amount', 'Status', 'Credit note no', 'Credit note date',
            ]);
        }

        return $export->setTitle($title)->setFilters($data['filters'])->setOrientation('landscape')
            ->exportToPdf('withholding-tax.pdf.receivable', $data);
    }

    public function recordCreditNote(Request $request, PaymentReceived $paymentReceived, WhtCredits $credits)
    {
        $validated = $request->validate([
            'wht_credit_note_number' => ['required', 'string', 'max:100'],
            'wht_credit_note_date' => ['required', 'date'],
        ]);

        $credits->recordCreditNote($paymentReceived, $validated['wht_credit_note_number'], $validated['wht_credit_note_date']);

        return back()->with('success', "WHT credit note recorded for payment {$paymentReceived->payment_number}.");
    }

    public function utilise(Request $request, WhtCredits $credits)
    {
        $tenantId = auth()->user()->tenant_id;
        $validated = $request->validate([
            'payment_ids' => ['required', 'array', 'min:1'],
            'payment_ids.*' => ['integer', Rule::exists('payments_received', 'id')->where('tenant_id', $tenantId)],
            'utilisation_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $utilisation = $credits->utilise($tenantId, $validated['payment_ids'], $validated['utilisation_date'],
            $validated['reference'] ?? null, $validated['notes'] ?? null, auth()->id());

        return back()->with('success', 'WHT credits of '.Money::format((float) $utilisation->amount).' used against income tax.');
    }

    /**
     * @return array{payments: EloquentCollection<int, PaymentReceived>,
     *               byCustomer: Collection<int, array{customer: mixed, tin: mixed, outstanding: float, received: float, utilised: float, total: float}>,
     *               totals: array{outstanding: float, received: float, utilised: float, total: float},
     *               filters: array{start_date: mixed, end_date: mixed, customer_id: mixed, status: mixed}}
     */
    protected function report(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'customer_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in([PaymentReceived::WHT_OUTSTANDING, PaymentReceived::WHT_RECEIVED, PaymentReceived::WHT_UTILISED])],
        ]);
        $filters = [
            'start_date' => $validated['start_date'] ?? now()->startOfYear()->toDateString(),
            'end_date' => $validated['end_date'] ?? now()->toDateString(),
            'customer_id' => $validated['customer_id'] ?? null,
            'status' => $validated['status'] ?? null,
        ];

        // A deleted customer's WHT still counts.
        $payments = PaymentReceived::with(['customer' => fn ($q) => $q->withTrashed(), 'invoice', 'whtCategory'])
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('wht_amount', '>', 0)
            ->whereBetween('payment_date', [$filters['start_date'], $filters['end_date']])
            ->when($filters['customer_id'], fn ($q, $id) => $q->where('customer_id', $id))
            ->orderBy('payment_date')->orderBy('id')
            ->get()
            ->when($filters['status'], fn ($c, $status) => $c->filter(fn (PaymentReceived $p) => $p->whtStatus() === $status)->values());

        $sum = fn (Collection $rows, string $status) => round((float) $rows->filter(fn (PaymentReceived $p) => $p->whtStatus() === $status)->sum('wht_amount'), 2);

        $byCustomer = $payments->groupBy('customer_id')->map(fn (Collection $rows) => [
            'customer' => $rows->first()->customer->name,
            'tin' => $rows->first()->customer->tax_number,
            PaymentReceived::WHT_OUTSTANDING => $sum($rows, PaymentReceived::WHT_OUTSTANDING),
            PaymentReceived::WHT_RECEIVED => $sum($rows, PaymentReceived::WHT_RECEIVED),
            PaymentReceived::WHT_UTILISED => $sum($rows, PaymentReceived::WHT_UTILISED),
            'total' => round((float) $rows->sum('wht_amount'), 2),
        ])->sortBy('customer')->values();

        $totals = [
            PaymentReceived::WHT_OUTSTANDING => $sum($payments, PaymentReceived::WHT_OUTSTANDING),
            PaymentReceived::WHT_RECEIVED => $sum($payments, PaymentReceived::WHT_RECEIVED),
            PaymentReceived::WHT_UTILISED => $sum($payments, PaymentReceived::WHT_UTILISED),
            'total' => round((float) $payments->sum('wht_amount'), 2),
        ];

        return compact('payments', 'byCustomer', 'totals', 'filters');
    }
}
