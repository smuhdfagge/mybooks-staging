<?php

namespace App\Http\Controllers\WithholdingTax;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\ChartOfAccount;
use App\Models\PaymentMade;
use App\Models\StatutoryRemittance;
use App\Models\Tenant;
use App\Services\AccountCodeService;
use App\Services\Accounting\WhtSchedule;
use App\Services\Accounting\WithholdingTax;
use App\Services\ActivityLogService;
use App\Services\BankService;
use App\Services\JournalService;
use App\Services\ReportExportService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * WHT payable: the monthly schedule of WHT we deducted from vendors (per
 * authority and vendor, with TINs; CSV and PDF), and recording paying it
 * over to the NRS or a state IRS (Dr WHT Payable, Cr bank).
 */
class WhtPayableController extends Controller
{
    public const METHODS = ['bank_transfer' => 'Bank transfer', 'cheque' => 'Cheque', 'cash' => 'Cash'];

    public function index(Request $request, WhtSchedule $schedules)
    {
        $tenantId = auth()->user()->tenant_id;
        $schedule = $schedules->build($tenantId, $this->month($request));

        return view('withholding-tax.schedule', $schedule + [
            'balance' => $this->balance($tenantId),
            'banks' => Bank::where('is_active', true)->orderBy('name')->get(),
            'methods' => self::METHODS,
        ]);
    }

    public function export(Request $request, WhtSchedule $schedules, ReportExportService $export)
    {
        $request->validate(['format' => ['nullable', 'in:csv,pdf']]);
        $tenantId = auth()->user()->tenant_id;
        $schedule = $schedules->build($tenantId, $this->month($request));
        $month = $schedule['month'];
        $title = 'WHT deducted '.$month->format('F Y');
        $export->setTitle($title)->setFilters(['Month' => $month->format('F Y')]);
        ActivityLogService::logExport('wht-schedule', ['month' => $month->format('Y-m')]);

        if ($request->query('format') === 'csv') {
            $rows = [];
            foreach ($schedule['groups'] as $group) {
                foreach ($group['rows'] as $p) {
                    /** @var PaymentMade $p */
                    $rows[] = [
                        $group['label'], $p->vendor?->name, $p->vendor?->tax_number, $p->vendor?->payee_type === 'individual' ? 'Individual' : 'Company',
                        collect([$p->vendor?->address, $p->vendor?->city, $p->vendor?->state])->filter()->implode(', '),
                        $p->whtCategory?->name, $p->payment_date->format('Y-m-d'), $p->payment_number, $p->bill?->bill_number,
                        number_format((float) $p->wht_base, 2, '.', ''), number_format((float) $p->wht_rate, 2, '.', ''),
                        number_format((float) $p->wht_amount, 2, '.', ''),
                    ];
                }
            }

            return $export->exportToCsv($rows, [
                'Pay to', 'Vendor', 'Vendor TIN', 'Vendor type', 'Address', 'Transaction type', 'Payment date', 'Payment no',
                'Bill no', 'Amount before VAT', 'Rate %', 'WHT deducted',
            ]);
        }

        return $export->setOrientation('landscape')->exportToPdf('withholding-tax.pdf.schedule', $schedule + [
            'payer' => Tenant::find($tenantId),
        ]);
    }

    public function remit(Request $request, JournalService $journals, BankService $banks)
    {
        $tenantId = auth()->user()->tenant_id;
        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'authority' => ['required', Rule::in([WithholdingTax::AUTHORITY_FEDERAL, WithholdingTax::AUTHORITY_STATE])],
            'state' => ['nullable', 'required_if:authority,'.WithholdingTax::AUTHORITY_STATE, 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', Rule::in(array_keys(self::METHODS))],
            'bank_id' => ['nullable', Rule::exists('banks', 'id')->where('tenant_id', $tenantId)],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $owed = $this->balance($tenantId);
        if ((float) $validated['amount'] - $owed > 0.005) {
            throw ValidationException::withMessages(['amount' => 'That is more than the WHT owed ('.Money::format($owed).').']);
        }

        $month = Carbon::createFromFormat('Y-m-d', $validated['period'].'-01')->startOfMonth();
        $state = $validated['authority'] === WithholdingTax::AUTHORITY_STATE ? trim((string) $validated['state']) : null;

        $remittance = DB::transaction(function () use ($tenantId, $validated, $month, $state, $journals, $banks) {
            $remittance = StatutoryRemittance::create([
                'tenant_id' => $tenantId,
                'body' => StatutoryRemittance::BODY_WHT,
                'account_code' => AccountCodeService::resolve($tenantId, 'wht_payable'),
                'period_start' => $month->toDateString(),
                'period_end' => $month->copy()->endOfMonth()->toDateString(),
                'paid_to' => WithholdingTax::authorityLabel($validated['authority'], $state),
                'wht_authority' => $validated['authority'],
                'wht_state' => $state,
                'amount' => round((float) $validated['amount'], 2),
                'paid_on' => $validated['paid_on'],
                'payment_method' => $validated['payment_method'],
                'bank_id' => $validated['bank_id'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'created_by' => auth()->id(),
            ]);
            $journal = $journals->createWhtRemittanceJournal($remittance);
            $remittance->update(['journal_id' => $journal->id]);
            $banks->debit($remittance->bank_id, (float) $remittance->amount, "WHT remitted to {$remittance->paid_to}");

            return $remittance;
        });

        return redirect()->route('withholding-tax.schedule', ['month' => $month->format('Y-m')])
            ->with('success', 'WHT payment of '.Money::format((float) $remittance->amount)." to {$remittance->paid_to} recorded.");
    }

    /** Undo a WHT remittance recorded in error: its journal is reversed and the bank gets the money back. */
    public function destroyRemittance(StatutoryRemittance $remittance, JournalService $journals, BankService $banks)
    {
        abort_unless($remittance->body === StatutoryRemittance::BODY_WHT, 404);

        DB::transaction(function () use ($remittance, $journals, $banks) {
            $journals->reverseDocumentJournal(StatutoryRemittance::class, $remittance->id, 'WHT remittance deleted');
            $banks->credit($remittance->bank_id, (float) $remittance->amount, "WHT remittance to {$remittance->paid_to} deleted");
            $remittance->delete();
        });

        return redirect()->route('withholding-tax.schedule', ['month' => $remittance->period_start->format('Y-m')])
            ->with('success', 'WHT payment deleted and its journal reversed.');
    }

    /** WHT payable now (what has been deducted and not yet paid over). */
    private function balance(int $tenantId): float
    {
        return round((float) ChartOfAccount::where('tenant_id', $tenantId)
            ->where('account_code', AccountCodeService::resolve($tenantId, 'wht_payable'))
            ->value('current_balance'), 2);
    }

    private function month(Request $request): Carbon
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $value = $request->query('month');

        // WHT is paid over for last month, by the 21st.
        return $value
            ? Carbon::createFromFormat('Y-m-d', $value.'-01')->startOfMonth()
            : now()->startOfMonth()->subMonthNoOverflow();
    }
}
