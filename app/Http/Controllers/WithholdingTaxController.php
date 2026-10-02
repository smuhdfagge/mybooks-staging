<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\PaymentMade;
use App\Models\StatutoryRemittance;
use App\Models\Tenant;
use App\Models\WhtCredit;
use App\Models\WhtRate;
use App\Services\AccountCodeService;
use App\Services\JournalService;
use App\Services\ReportExportService;
use App\Support\Money;
use App\Support\WithholdingTax;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Withholding tax, "deduction at source" (tax pack 2):
 * - WHT deducted from vendors, per month, with a schedule to file and a
 *   way to record paying it to the tax office;
 * - WHT customers deducted from us, with their certificates (credit notes);
 * - the rates and business settings.
 * Deducting or recording WHT happens on the payment forms.
 */
class WithholdingTaxController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        WhtRate::ensureDefaults($tenantId);
        $month = $this->month($request->query('month'));
        $tab = in_array($request->query('tab'), ['deducted', 'credits', 'rates'], true) ? $request->query('tab') : 'deducted';
        $status = in_array($request->query('status'), [WhtCredit::STATUS_AWAITING, WhtCredit::STATUS_RECEIVED], true) ? $request->query('status') : null;

        $deducted = $this->deductions($tenantId, $month);
        $payable = ChartOfAccount::where('tenant_id', $tenantId)->where('account_code', AccountCodeService::resolve($tenantId, 'wht_payable'))->first();

        return view('withholding-tax.index', [
            'tab' => $tab,
            'month' => $month,
            'byVendor' => $deducted->groupBy('vendor_id')->map(fn ($rows) => (object) [
                'vendor' => $rows->first()->vendor,
                'count' => $rows->count(),
                'amount' => Money::sum($rows->pluck('amount')),
                'wht' => Money::sum($rows->pluck('wht_amount')),
            ])->sortBy(fn ($v) => $v->vendor->name ?? '')->values(),
            'totalDeducted' => Money::sum($deducted->pluck('wht_amount')),
            'remitted' => Money::round(StatutoryRemittance::where('tenant_id', $tenantId)->where('body', 'wht')
                ->where('period_start', '>=', $month->toDateString())
                ->where('period_start', '<', $month->copy()->addMonthNoOverflow()->toDateString())->sum('amount')),
            'payableBalance' => (float) ($payable->current_balance ?? 0),
            'dueDate' => $month->copy()->addMonthNoOverflow()->day(21),
            'remittances' => StatutoryRemittance::where('tenant_id', $tenantId)->where('body', 'wht')->latest('paid_on')->latest('id')->limit(10)->get(),
            'credits' => WhtCredit::where('tenant_id', $tenantId)->with(['customer', 'payment'])
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest('deducted_on')->latest('id')->paginate(25)->withQueryString(),
            'creditTotals' => $this->creditTotals($tenantId),
            'status' => $status,
            'rates' => WhtRate::where('tenant_id', $tenantId)->orderBy('sort_order')->orderBy('id')->get(),
            'settings' => WithholdingTax::settings(Tenant::find($tenantId)),
            'methods' => ['bank_transfer' => 'Bank transfer', 'cheque' => 'Cheque', 'cash' => 'Cash'],
        ]);
    }

    /** Schedule of WHT deducted from vendors in a month, CSV or PDF. */
    public function schedule(Request $request, ReportExportService $export)
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'format' => ['nullable', 'in:csv,pdf']]);
        $tenantId = auth()->user()->tenant_id;
        $month = $this->month($request->query('month'));
        $rows = $this->deductions($tenantId, $month)->map(fn (PaymentMade $p) => [
            'vendor' => $p->vendor?->name,
            'tin' => $p->vendor?->tax_number,
            'type' => ucfirst($p->vendor?->entity_type ?: 'company'),
            'address' => trim(implode(', ', array_filter([$p->vendor?->address, $p->vendor?->city, $p->vendor?->state])), ', '),
            'date' => $p->payment_date->format('Y-m-d'),
            'payment' => $p->payment_number,
            'transaction' => $p->whtRate->name ?? '',
            'amount' => (float) $p->amount,
            'rate' => (float) $p->wht_rate,
            'wht' => (float) $p->wht_amount,
        ])->sortBy(fn ($r) => $r['vendor'].'|'.$r['date'])->values();

        $tenant = Tenant::find($tenantId);
        $export->setTitle('WHT schedule '.$month->format('F Y'))
            ->setFilters(['Period' => $month->format('F Y'), 'Deductor TIN' => $tenant?->tax_number ?: 'not set']);

        if ($request->query('format') === 'csv') {
            return $export->exportToCsv(
                $rows->map(fn ($r) => [$r['vendor'], $r['tin'], $r['type'], $r['address'], $r['date'], $r['payment'], $r['transaction'],
                    number_format($r['amount'], 2, '.', ''), number_format($r['rate'], 2, '.', ''), number_format($r['wht'], 2, '.', '')])->all(),
                ['Beneficiary', 'Beneficiary TIN', 'Type', 'Address', 'Payment date', 'Payment no.', 'Nature of transaction', 'Amount', 'Rate %', 'WHT deducted']
            );
        }

        return $export->setOrientation('landscape')->exportToPdf('withholding-tax.pdf.schedule', [
            'groups' => $rows->groupBy('vendor'),
            'total' => Money::sum($rows->pluck('wht')),
            'dueDate' => $month->copy()->addMonthNoOverflow()->day(21),
        ]);
    }

    /** WHT credits from customers, CSV or PDF. */
    public function creditsReport(Request $request, ReportExportService $export)
    {
        $request->validate(['format' => ['nullable', 'in:csv,pdf']]);
        $tenantId = auth()->user()->tenant_id;
        $credits = WhtCredit::where('tenant_id', $tenantId)->with(['customer', 'payment'])->orderBy('deducted_on')->get();
        $export->setTitle('WHT credits');

        if ($request->query('format') === 'csv') {
            return $export->exportToCsv($credits->map(fn (WhtCredit $c) => [
                $c->customer?->name, $c->customer?->tax_number, $c->deducted_on->format('Y-m-d'), $c->payment?->payment_number,
                number_format((float) $c->amount, 2, '.', ''), $c->status === WhtCredit::STATUS_RECEIVED ? 'Certificate received' : 'Awaiting certificate',
                $c->certificate_number, $c->certificate_date?->format('Y-m-d'),
            ])->all(), ['Customer', 'Customer TIN', 'Date deducted', 'Payment no.', 'WHT', 'Status', 'Certificate no.', 'Certificate date']);
        }

        return $export->setOrientation('landscape')->exportToPdf('withholding-tax.pdf.credits', [
            'credits' => $credits,
            'totals' => $this->creditTotals($tenantId),
        ]);
    }

    /** Paying WHT to the tax office: Dr WHT Payable, Cr bank. */
    public function remit(Request $request, JournalService $journals)
    {
        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', 'in:bank_transfer,cheque,cash'],
            'paid_to' => ['nullable', 'string', 'max:150'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);
        $tenantId = auth()->user()->tenant_id;
        $code = AccountCodeService::resolve($tenantId, 'wht_payable');
        $owed = (float) ChartOfAccount::where('tenant_id', $tenantId)->where('account_code', $code)->value('current_balance');
        if ((float) $validated['amount'] - $owed > 0.005) {
            throw ValidationException::withMessages(['amount' => 'That is more than is owed ('.number_format($owed, 2).').']);
        }
        $month = $this->month($validated['period']);

        $journal = DB::transaction(function () use ($tenantId, $code, $validated, $journals, $month) {
            $journal = $journals->createPayrollRemittanceJournal($tenantId, $code, (float) $validated['amount'], $validated['date'],
                $validated['payment_method'], $validated['reference'] ?? null, JournalService::TAX_REMITTANCE);
            StatutoryRemittance::create([
                'tenant_id' => $tenantId, 'body' => 'wht', 'account_code' => $code,
                'period_start' => $month->toDateString(), 'period_end' => $month->copy()->endOfMonth()->toDateString(),
                'paid_to' => $validated['paid_to'] ?? null, 'amount' => $validated['amount'], 'paid_on' => $validated['date'],
                'payment_method' => $validated['payment_method'], 'reference' => $validated['reference'] ?? null,
                'journal_id' => $journal->id, 'created_by' => auth()->id(),
            ]);

            return $journal;
        });

        return redirect()->route('withholding-tax.index', ['month' => $month->format('Y-m')])
            ->with('success', "WHT payment recorded (journal {$journal->journal_number}).");
    }

    /** Record the certificate (credit note) a customer sent for WHT. */
    public function updateCredit(Request $request, WhtCredit $whtCredit)
    {
        abort_unless($whtCredit->tenant_id === auth()->user()->tenant_id, 404);
        $validated = $request->validate([
            'certificate_number' => ['nullable', 'string', 'max:100', 'required_with:certificate_date'],
            'certificate_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $validated['status'] = filled($validated['certificate_number'] ?? null) ? WhtCredit::STATUS_RECEIVED : WhtCredit::STATUS_AWAITING;
        $whtCredit->update($validated);

        return redirect()->route('withholding-tax.index', ['tab' => 'credits'])->with('success', 'WHT credit updated.');
    }

    /** Save the rate table; a row with a new name adds a rate. */
    public function updateRates(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $validated = $request->validate([
            'rates' => ['array'],
            'rates.*.name' => ['required', 'string', 'max:150'],
            'rates.*.rate_company' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rates.*.rate_individual' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rates.*.is_active' => ['nullable', 'boolean'],
            'new.name' => ['nullable', 'string', 'max:150'],
            'new.rate_company' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'new.rate_individual' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        DB::transaction(function () use ($tenantId, $validated) {
            foreach ($validated['rates'] ?? [] as $id => $row) {
                WhtRate::where('tenant_id', $tenantId)->whereKey($id)->update([
                    'name' => $row['name'],
                    'rate_company' => $row['rate_company'] ?? null,
                    'rate_individual' => $row['rate_individual'] ?? null,
                    'is_active' => (bool) ($row['is_active'] ?? false),
                ]);
            }
            if (filled($validated['new']['name'] ?? null)) {
                WhtRate::create([
                    'tenant_id' => $tenantId,
                    'code' => 'custom_'.substr(md5($validated['new']['name'].microtime()), 0, 10),
                    'name' => $validated['new']['name'],
                    'rate_company' => $validated['new']['rate_company'] ?? null,
                    'rate_individual' => $validated['new']['rate_individual'] ?? null,
                    'is_active' => true,
                    'sort_order' => (int) WhtRate::where('tenant_id', $tenantId)->max('sort_order') + 1,
                ]);
            }
        });

        return redirect()->route('withholding-tax.index', ['tab' => 'rates'])->with('success', 'WHT rates saved.');
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'small_company' => ['boolean'],
            'small_company_threshold' => ['required', 'numeric', 'min:0'],
            'double_without_tin' => ['boolean'],
        ]);
        $tenant = Tenant::findOrFail(auth()->user()->tenant_id);
        $settings = $tenant->settings ?? [];
        $settings['wht'] = [
            'small_company' => $request->boolean('small_company'),
            'small_company_threshold' => (float) $validated['small_company_threshold'],
            'double_without_tin' => $request->boolean('double_without_tin'),
        ];
        $tenant->settings = $settings;
        $tenant->save();

        return redirect()->route('withholding-tax.index', ['tab' => 'rates'])->with('success', 'WHT settings saved.');
    }

    /** @return Collection<int, PaymentMade> */
    private function deductions(int $tenantId, Carbon $month): Collection
    {
        return PaymentMade::where('tenant_id', $tenantId)
            ->where('wht_amount', '>', 0)
            ->where('payment_date', '>=', $month->toDateString())
            ->where('payment_date', '<', $month->copy()->addMonthNoOverflow()->toDateString())
            ->with(['vendor', 'whtRate'])
            ->orderBy('payment_date')->orderBy('id')
            ->get();
    }

    /** @return array{awaiting: float, received: float, total: float} */
    private function creditTotals(int $tenantId): array
    {
        $sum = fn (string $status) => Money::round(WhtCredit::where('tenant_id', $tenantId)->where('status', $status)->sum('amount'));
        $awaiting = $sum(WhtCredit::STATUS_AWAITING);
        $received = $sum(WhtCredit::STATUS_RECEIVED);

        return ['awaiting' => $awaiting, 'received' => $received, 'total' => Money::add($awaiting, $received)];
    }

    private function month(?string $value): Carbon
    {
        if ($value && preg_match('/^\d{4}-\d{2}$/', $value)) {
            return Carbon::createFromFormat('Y-m-d', $value.'-01')->startOfDay();
        }

        return now()->startOfMonth()->subMonthNoOverflow();
    }
}
