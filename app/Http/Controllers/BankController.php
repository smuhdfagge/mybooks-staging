<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\ChartOfAccount;
use App\Models\PaymentReceived;
use App\Models\PaymentMade;
use App\Models\Expense;
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

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'account_type' => 'required|in:checking,savings,credit_card,cash,other',
            'currency' => 'required|string|size:3',
            'routing_number' => 'nullable|string|max:50',
            'swift_code' => 'nullable|string|max:20',
            'iban' => 'nullable|string|max:50',
            'branch_name' => 'nullable|string|max:255',
            'branch_address' => 'nullable|string|max:500',
            'opening_balance' => 'nullable|numeric',
            'opening_balance_date' => 'nullable|date',
            'chart_of_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'description' => 'nullable|string',
            'is_primary' => 'boolean',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['current_balance'] = $validated['opening_balance'] ?? 0;

        // If setting as primary, unset other primary banks
        if (!empty($validated['is_primary'])) {
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
        
        return view('banks.show', compact('bank', 'recentTransactions'));
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

    public function update(Request $request, Bank $bank)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'account_type' => 'required|in:checking,savings,credit_card,cash,other',
            'currency' => 'required|string|size:3',
            'routing_number' => 'nullable|string|max:50',
            'swift_code' => 'nullable|string|max:20',
            'iban' => 'nullable|string|max:50',
            'branch_name' => 'nullable|string|max:255',
            'branch_address' => 'nullable|string|max:500',
            'chart_of_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'description' => 'nullable|string',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // If setting as primary, unset other primary banks
        if (!empty($validated['is_primary']) && !$bank->is_primary) {
            Bank::where('tenant_id', auth()->user()->tenant_id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        $bank->update($validated);

        return redirect()->route('banks.index')->with('success', 'Bank account updated successfully.');
    }

    public function destroy(Bank $bank)
    {
        if ($bank->transactions()->count() > 0) {
            return redirect()->back()->with('error', 'Cannot delete bank account with transactions.');
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
}
