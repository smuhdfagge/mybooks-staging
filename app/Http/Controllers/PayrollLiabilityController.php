<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\StatutoryRemittance;
use App\Models\Tenant;
use App\Services\AccountCodeService;
use App\Services\JournalService;
use App\Services\Payroll\StatutorySchedule;
use App\Services\ReportExportService;
use App\Support\PayrollStatutory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * What the business owes the tax office and funds from payroll (PAYE,
 * pension, NHF, health insurance, union dues...) and a way to record paying
 * it (finding A10). Tax pack 1 adds, per month: what is owed to each
 * statutory body, what was paid, the due date, downloadable schedules
 * (PAYE by state, pension by PFA, NHF, NSITF, ITF) and the rates used.
 */
class PayrollLiabilityController extends Controller
{
    private const KEYS = [
        'tax_payable', 'pension_payable', 'nhf_payable', 'nsitf_payable', 'itf_payable', 'insurance_payable',
        'union_dues_payable', 'garnishments_payable', 'payroll_liabilities', 'accrued_salaries',
    ];

    public function index(Request $request, StatutorySchedule $schedules)
    {
        $tenantId = auth()->user()->tenant_id;
        $tenant = Tenant::find($tenantId);
        $month = $this->month($request->query('month'));

        return view('payroll.liabilities', [
            'accounts' => $this->accounts($tenantId),
            'methods' => ['bank_transfer' => 'Bank transfer', 'cheque' => 'Cheque', 'cash' => 'Cash'],
            'month' => $month,
            'summary' => $schedules->summary($tenantId, $month),
            'settings' => PayrollStatutory::settings($tenant),
            'isNigerian' => PayrollStatutory::isNigerian($tenant),
            'payTo' => $this->payTo($tenantId, $month, $schedules),
            'remittances' => StatutoryRemittance::where('tenant_id', $tenantId)
                ->where('body', '!=', 'wht')
                ->latest('paid_on')->latest('id')->limit(15)->get(),
        ]);
    }

    public function remit(Request $request, JournalService $journals, StatutorySchedule $schedules)
    {
        $tenantId = auth()->user()->tenant_id;
        $accounts = $this->accounts($tenantId)->where('account_code', '!=', AccountCodeService::resolve($tenantId, 'accrued_salaries'));

        $validated = $request->validate([
            'account_code' => ['required', Rule::in($accounts->pluck('account_code')->all())],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', 'in:bank_transfer,cheque,cash'],
            'reference' => ['nullable', 'string', 'max:100'],
            'period' => ['nullable', 'date_format:Y-m'],
            'paid_to' => ['nullable', 'string', 'max:150'],
        ]);

        $owed = (float) $accounts->firstWhere('account_code', $validated['account_code'])->current_balance;
        if ((float) $validated['amount'] - $owed > 0.005) {
            throw ValidationException::withMessages(['amount' => 'That is more than is owed ('.number_format($owed, 2).').']);
        }

        // Which statutory body the account belongs to, for the month's totals.
        $body = collect(PayrollStatutory::LIABILITY_KEYS)
            ->search(fn ($key) => AccountCodeService::resolve($tenantId, $key) === $validated['account_code']) ?: 'other';
        [$from, $to] = $schedules->period($body, $this->month($validated['period'] ?? null));
        if ($body === 'itf') {
            $to = $to->copy()->endOfYear(); // ITF is paid for the whole year
        }

        $journal = DB::transaction(function () use ($tenantId, $validated, $journals, $body, $from, $to) {
            $journal = $journals->createPayrollRemittanceJournal(
                $tenantId, $validated['account_code'], (float) $validated['amount'], $validated['date'],
                $validated['payment_method'], $validated['reference'] ?? null
            );
            StatutoryRemittance::create([
                'tenant_id' => $tenantId,
                'body' => $body,
                'account_code' => $validated['account_code'],
                'period_start' => $from->toDateString(),
                'period_end' => $to->toDateString(),
                'paid_to' => $validated['paid_to'] ?? null,
                'amount' => $validated['amount'],
                'paid_on' => $validated['date'],
                'payment_method' => $validated['payment_method'],
                'reference' => $validated['reference'] ?? null,
                'journal_id' => $journal->id,
                'created_by' => auth()->id(),
            ]);

            return $journal;
        });

        return redirect()->route('payroll.liabilities', ['month' => $from->format('Y-m')])
            ->with('success', "Remittance recorded (journal {$journal->journal_number}).");
    }

    /**
     * A body's schedule for the month (ITF: the year to that month), as
     * CSV or PDF.
     */
    public function schedule(Request $request, string $body, StatutorySchedule $schedules, ReportExportService $export)
    {
        abort_unless(in_array($body, PayrollStatutory::BODIES, true), 404);
        $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'format' => ['nullable', 'in:csv,pdf']]);

        $tenantId = auth()->user()->tenant_id;
        $month = $this->month($request->query('month'));
        [$from, $to] = $schedules->period($body, $month);
        $schedule = $schedules->build($tenantId, $body, $from->toDateString(), $to->toDateString());
        $title = strtoupper($body).' schedule '.($body === 'itf' ? $from->format('M').' to '.$to->format('M Y') : $month->format('F Y'));
        $export->setTitle($title)->setFilters(['Period' => $from->format('d M Y').' to '.$to->format('d M Y')]);

        if ($request->query('format') === 'csv') {
            $headers = array_values(array_filter([$schedule['group_label'], ...array_values($schedule['columns'])]));
            $rows = [];
            foreach ($schedule['groups'] as $group) {
                foreach ($group['rows'] as $row) {
                    $rows[] = array_merge(
                        $schedule['group_label'] ? [$row['group']] : [],
                        array_map(fn ($col) => in_array($col, StatutorySchedule::MONEY, true) ? number_format($row[$col], 2, '.', '') : ($row[$col] ?? ''), array_keys($schedule['columns']))
                    );
                }
            }

            return $export->exportToCsv($rows, $headers);
        }

        $tenant = Tenant::find($tenantId);

        return $export->setOrientation('landscape')->exportToPdf('payroll.pdf.statutory-schedule', [
            'schedule' => $schedule,
            'employerTin' => $tenant?->tax_number,
            'dueDate' => PayrollStatutory::dueDate($body, $to->copy()->endOfMonth()),
        ]);
    }

    /** The rates and switches the payroll run uses (tax pack 1). */
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'enabled' => ['boolean'],
            'pension_applies' => ['boolean'],
            'pension_employee_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'pension_employer_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'nhf_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'nsitf_applies' => ['boolean'],
            'nsitf_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'itf_applies' => ['boolean'],
            'itf_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $tenant = Tenant::findOrFail(auth()->user()->tenant_id);
        $settings = $tenant->settings ?? [];
        $settings['statutory'] = [];
        foreach (PayrollStatutory::DEFAULTS as $key => $default) {
            $settings['statutory'][$key] = is_bool($default) ? $request->boolean($key) : (float) $validated[$key];
        }
        $tenant->settings = $settings;
        $tenant->save();

        return redirect()->route('payroll.liabilities')->with('success', 'Payroll contribution settings saved. They apply to payroll runs from now on.');
    }

    private function month(?string $value): Carbon
    {
        if ($value && preg_match('/^\d{4}-\d{2}$/', $value)) {
            return Carbon::createFromFormat('Y-m-d', $value.'-01')->startOfDay();
        }

        // Most remittances are for last month's payroll.
        return now()->startOfMonth()->subMonthNoOverflow();
    }

    /**
     * States and PFAs in the month's payroll, offered as "paid to".
     *
     * @return array<int, string>
     */
    private function payTo(int $tenantId, Carbon $month, StatutorySchedule $schedules): array
    {
        $names = [];
        foreach (['paye' => ' State IRS', 'pension' => ''] as $body => $suffix) {
            [$from, $to] = $schedules->period($body, $month);
            foreach (array_keys($schedules->build($tenantId, $body, $from->toDateString(), $to->toDateString())['groups']) as $group) {
                $names[] = $group.$suffix;
            }
        }

        return array_values(array_unique($names));
    }

    /** @return Collection<int, ChartOfAccount> */
    private function accounts(int $tenantId)
    {
        $codes = collect(self::KEYS)->map(fn ($k) => AccountCodeService::resolve($tenantId, $k))->unique()->values();

        return ChartOfAccount::where('tenant_id', $tenantId)->whereIn('account_code', $codes)->orderBy('account_code')->get();
    }
}
