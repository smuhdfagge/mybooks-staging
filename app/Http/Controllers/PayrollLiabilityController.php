<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Services\AccountCodeService;
use App\Services\JournalService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * What the business owes the tax office and funds from payroll (PAYE,
 * pension, NHF, health insurance, union dues...) and a way to record paying
 * it (finding A10).
 */
class PayrollLiabilityController extends Controller
{
    private const KEYS = [
        'tax_payable', 'pension_payable', 'insurance_payable', 'union_dues_payable',
        'garnishments_payable', 'payroll_liabilities', 'accrued_salaries',
    ];

    public function index()
    {
        $tenantId = auth()->user()->tenant_id;

        return view('payroll.liabilities', [
            'accounts' => $this->accounts($tenantId),
            'methods' => ['bank_transfer' => 'Bank transfer', 'cheque' => 'Cheque', 'cash' => 'Cash'],
        ]);
    }

    public function remit(Request $request, JournalService $journals)
    {
        $tenantId = auth()->user()->tenant_id;
        $accounts = $this->accounts($tenantId)->where('account_code', '!=', AccountCodeService::resolve($tenantId, 'accrued_salaries'));

        $validated = $request->validate([
            'account_code' => ['required', Rule::in($accounts->pluck('account_code')->all())],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', 'in:bank_transfer,cheque,cash'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $owed = (float) $accounts->firstWhere('account_code', $validated['account_code'])->current_balance;
        if ((float) $validated['amount'] - $owed > 0.005) {
            throw ValidationException::withMessages(['amount' => 'That is more than is owed ('.number_format($owed, 2).').']);
        }

        $journal = $journals->createPayrollRemittanceJournal(
            $tenantId, $validated['account_code'], (float) $validated['amount'], $validated['date'],
            $validated['payment_method'], $validated['reference'] ?? null
        );

        return redirect()->route('payroll.liabilities')
            ->with('success', "Remittance recorded (journal {$journal->journal_number}).");
    }

    /** @return \Illuminate\Support\Collection<int, ChartOfAccount> */
    private function accounts(int $tenantId)
    {
        $codes = collect(self::KEYS)->map(fn ($k) => AccountCodeService::resolve($tenantId, $k))->unique()->values();

        return ChartOfAccount::where('tenant_id', $tenantId)->whereIn('account_code', $codes)->orderBy('account_code')->get();
    }
}
