<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\BankFeedLine;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Vendor;
use App\Services\BankFeeds\BankFeedException;
use App\Services\BankFeeds\LinePoster;
use App\Services\BankFeeds\Matcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * One bank line, and recording something from it (session 17). Matching is
 * done on the lines list; this page is for a line that matches nothing.
 * Nothing is recorded until a person submits one of the forms.
 */
class BankFeedLineController extends Controller
{
    public function __construct(private LinePoster $poster) {}

    public function show(Request $request, BankFeedLine $line, Matcher $matcher)
    {
        $line->load(['connection', 'bank', 'matched']);
        $suggestion = $line->isNew() ? ($matcher->suggest([$line])[$line->id] ?? null) : null;

        $customer = $request->filled('customer_id') ? Customer::find($request->integer('customer_id')) : null;
        $invoices = $customer
            ? Invoice::where('customer_id', $customer->id)->whereNotIn('status', ['draft', 'cancelled', 'void', 'voided', 'paid'])->where('balance_due', '>', 0)
                ->orderBy('due_date')->get(['id', 'invoice_number', 'invoice_date', 'due_date', 'total', 'balance_due'])
            : collect();

        return view('bank-feeds.line', [
            'line' => $line,
            'suggestion' => $suggestion,
            'customer' => $customer,
            'customerOptions' => $customer ? [['id' => (string) $customer->id, 'name' => $customer->name.($customer->company_name ? " ({$customer->company_name})" : '')]] : [],
            'invoices' => $invoices,
            'expenseAccounts' => ChartOfAccount::where('type', 'expense')->where('is_active', true)->orderBy('account_code')->get(['id', 'account_code', 'name']),
            'incomeAccounts' => ChartOfAccount::where('type', 'income')->where('is_active', true)->orderBy('account_code')->get(['id', 'account_code', 'name']),
            'vendors' => Vendor::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'otherBanks' => Bank::where('is_active', true)->where('id', '!=', $line->bank_id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function paymentReceived(Request $request, BankFeedLine $line): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            // an invoice id, or "deposit" to keep the money as a customer deposit
            'invoice_id' => ['nullable', 'regex:/^(\d+|deposit)$/'],
        ]);
        $deposit = ($data['invoice_id'] ?? 'deposit') === 'deposit';

        return $this->run($line, fn () => $this->poster->paymentReceived($line, $request->user(), (int) $data['customer_id'], $deposit ? null : (int) $data['invoice_id'], $deposit), 'Payment recorded.');
    }

    public function expense(Request $request, BankFeedLine $line): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'expense_account_id' => ['required', 'integer'],
            'vendor_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        if (! empty($data['vendor_id']) && ! Vendor::whereKey($data['vendor_id'])->exists()) {
            return back()->withInput()->with('error', 'That supplier was not found.');
        }

        return $this->run($line, fn () => $this->poster->expense($line, $request->user(), $data),
            'Expense saved as a draft. Submit it for approval and mark it paid as usual; it counts as cleared once it is paid.');
    }

    public function otherIncome(Request $request, BankFeedLine $line): RedirectResponse
    {
        $data = $request->validate(['income_account_id' => ['required', 'integer'], 'description' => ['nullable', 'string', 'max:150']]);

        return $this->run($line, fn () => $this->poster->otherIncome($line, $request->user(), (int) $data['income_account_id'], $data['description'] ?? null), 'Income recorded.');
    }

    public function bankCharge(Request $request, BankFeedLine $line): RedirectResponse
    {
        $data = $request->validate(['expense_account_id' => ['required', 'integer']]);

        return $this->run($line, fn () => $this->poster->bankCharge($line, $request->user(), (int) $data['expense_account_id']), 'Bank charge recorded.');
    }

    public function transfer(Request $request, BankFeedLine $line): RedirectResponse
    {
        $data = $request->validate(['other_bank_id' => ['required', 'integer']]);

        return $this->run($line, fn () => $this->poster->transfer($line, $request->user(), (int) $data['other_bank_id']), 'Transfer recorded. When the other account\'s own line arrives, match it to this transfer.');
    }

    /** Runs the posting; a refusal (locked period, wrong customer...) comes back as a message and the line is left as it was. */
    private function run(BankFeedLine $line, callable $do, string $success): RedirectResponse
    {
        try {
            $do();
        } catch (BankFeedException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('bank-feeds.lines')->with('success', $success);
    }
}
