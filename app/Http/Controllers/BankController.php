<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBankRequest;
use App\Http\Requests\UpdateBankRequest;
use App\Models\Bank;
use App\Models\BankFeedConnection;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\PaymentMade;
use App\Models\PaymentReceived;
use App\Services\BankFeeds\FeedStatement;
use App\Services\BankReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BankController extends Controller
{
    public function index()
    {
        return view('banks.index');
    }

    public function create()
    {
        $chartOfAccounts = ChartOfAccount::where('is_active', true)
            ->where('type', 'asset')
            ->orderBy('account_code')
            ->get();
        $accountTypes = Bank::getAccountTypes();

        return view('banks.create', compact('chartOfAccounts', 'accountTypes'));
    }

    public function store(StoreBankRequest $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validated();

        $validated['tenant_id'] = $tenantId;
        $validated['current_balance'] = $validated['opening_balance'] ?? 0;

        // If setting as primary, unset other primary banks
        if (! empty($validated['is_primary'])) {
            Bank::where('tenant_id', $validated['tenant_id'])
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        Bank::create($validated);

        return redirect()->route('banks.index')->with('success', 'Bank account created successfully.');
    }

    public function show(Bank $bank)
    {
        $bank->load(['chartOfAccount']);

        // Get recent transactions from payments received, payments made, and expenses
        $paymentsReceived = PaymentReceived::where('bank_id', $bank->id)
            ->with('customer')
            ->get()
            ->map(function ($payment) {
                return [
                    'date' => $payment->payment_date,
                    'type' => 'deposit',
                    'reference' => $payment->payment_number,
                    'party' => $payment->customer?->name ?? 'N/A',
                    'amount' => $payment->amount,
                    'route' => route('payments-received.show', $payment),
                ];
            });

        $paymentsMade = PaymentMade::where('bank_id', $bank->id)
            ->with('vendor')
            ->get()
            ->map(function ($payment) {
                return [
                    'date' => $payment->payment_date,
                    'type' => 'withdrawal',
                    'reference' => $payment->payment_number,
                    'party' => $payment->vendor?->name ?? 'N/A',
                    'amount' => $payment->amount,
                    'route' => route('payments-made.show', $payment),
                ];
            });

        $expenses = Expense::where('bank_id', $bank->id)
            ->with('vendor')
            ->get()
            ->map(function ($expense) {
                return [
                    'date' => $expense->expense_date,
                    'type' => 'withdrawal',
                    'reference' => $expense->expense_number,
                    'party' => $expense->vendor?->name ?? $expense->name,
                    'amount' => $expense->total,
                    'route' => route('expenses.show', $expense),
                ];
            });

        $recentTransactions = $paymentsReceived->concat($paymentsMade)->concat($expenses)
            ->sortByDesc('date')
            ->take(10)
            ->values();

        $feed = config('mybooks.features.bank_feeds') ? BankFeedConnection::counted()->where('bank_id', $bank->id)->first() : null;

        return view('banks.show', compact('bank', 'recentTransactions', 'feed'));
    }

    public function edit(Bank $bank)
    {
        $chartOfAccounts = ChartOfAccount::where('is_active', true)
            ->where('type', 'asset')
            ->orderBy('account_code')
            ->get();
        $accountTypes = Bank::getAccountTypes();

        return view('banks.edit', compact('bank', 'chartOfAccounts', 'accountTypes'));
    }

    public function update(UpdateBankRequest $request, Bank $bank)
    {
        $validated = $request->validated();

        // If setting as primary, unset other primary banks
        if (! empty($validated['is_primary']) && ! $bank->is_primary) {
            Bank::where('tenant_id', auth()->user()->tenant_id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        $bank->update($validated);

        return redirect()->route('banks.index')->with('success', 'Bank account updated successfully.');
    }

    public function destroy(Bank $bank)
    {
        if ($reason = $bank->deleteBlockedReason()) {
            return redirect()->back()->with('error', $reason);
        }

        $bank->delete();

        return redirect()->route('banks.index')->with('success', 'Bank account deleted successfully.');
    }

    public function transactions(Bank $bank)
    {
        $paymentsReceived = PaymentReceived::where('bank_id', $bank->id)
            ->with('customer')
            ->get()
            ->map(function ($payment) {
                return [
                    'date' => $payment->payment_date,
                    'type' => 'deposit',
                    'reference' => $payment->payment_number,
                    'extra_ref' => $payment->reference,
                    'party' => $payment->customer?->name ?? 'N/A',
                    'amount' => $payment->amount,
                    'route' => route('payments-received.show', $payment),
                ];
            });

        $paymentsMade = PaymentMade::where('bank_id', $bank->id)
            ->with('vendor')
            ->get()
            ->map(function ($payment) {
                return [
                    'date' => $payment->payment_date,
                    'type' => 'withdrawal',
                    'reference' => $payment->payment_number,
                    'extra_ref' => $payment->reference,
                    'party' => $payment->vendor?->name ?? 'N/A',
                    'amount' => $payment->amount,
                    'route' => route('payments-made.show', $payment),
                ];
            });

        $expenses = Expense::where('bank_id', $bank->id)
            ->with('vendor')
            ->get()
            ->map(function ($expense) {
                return [
                    'date' => $expense->expense_date,
                    'type' => 'withdrawal',
                    'reference' => $expense->expense_number,
                    'extra_ref' => $expense->reference,
                    'party' => $expense->vendor?->name ?? $expense->name,
                    'amount' => $expense->total,
                    'route' => route('expenses.show', $expense),
                ];
            });

        $transactions = $paymentsReceived->concat($paymentsMade)->concat($expenses)
            ->sortByDesc('date')
            ->values();

        return view('banks.transactions', compact('bank', 'transactions'));
    }

    public function reconcile(Request $request, Bank $bank, BankReconciliationService $reconciliationService, FeedStatement $feedStatement)
    {
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        $unreconciledTransactions = $reconciliationService->getUnreconciledTransactions($bank, $fromDate, $toDate);
        $summary = $reconciliationService->getSummary($bank);

        // A linked bank feed is the statement: its balance and lines (session 17)
        $feed = config('mybooks.features.bank_feeds') ? $feedStatement->forBank($bank) : null;

        return view('banks.reconcile', compact('bank', 'unreconciledTransactions', 'summary', 'fromDate', 'toDate', 'feed'));
    }

    public function processReconciliation(Request $request, Bank $bank, BankReconciliationService $reconciliationService)
    {
        $validated = $request->validate([
            'transaction_ids' => 'required|array|min:1',
            'transaction_ids.*' => ['integer', Rule::exists('bank_transactions', 'id')->where('tenant_id', auth()->user()->tenant_id)->where('bank_id', $bank->id)],
            'statement_balance' => 'required|numeric',
            'statement_date' => 'required|date',
        ]);

        $result = $reconciliationService->reconcile(
            $bank,
            $validated['transaction_ids'],
            (float) $validated['statement_balance'],
            $validated['statement_date']
        );

        if ($result['success']) {
            return redirect()->route('banks.reconcile', $bank)->with('success', $result['message']);
        }

        return redirect()->back()
            ->withInput()
            ->with('error', $result['message'])
            ->with('reconciliation_difference', $result['difference']);
    }

    public function unreconcile(Request $request, Bank $bank, BankReconciliationService $reconciliationService)
    {
        $validated = $request->validate([
            'transaction_ids' => 'required|array|min:1',
            'transaction_ids.*' => ['integer', Rule::exists('bank_transactions', 'id')->where('tenant_id', auth()->user()->tenant_id)->where('bank_id', $bank->id)],
        ]);

        $count = $reconciliationService->unreconcile($bank, $validated['transaction_ids']);

        return redirect()->route('banks.reconcile', $bank)
            ->with('success', "Successfully unreconciled {$count} transaction(s).");
    }
}
