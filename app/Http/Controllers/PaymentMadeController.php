<?php

namespace App\Http\Controllers;

use App\Models\PaymentMade;
use App\Models\Bill;
use App\Models\Vendor;
use App\Models\Bank;
use App\Services\BankService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentMadeController extends Controller
{
    public function __construct(
        protected BankService $bankService
    ) {}
    public function index()
    {
        return view('payments-made.index');
    }

    public function create(Request $request)
    {
        $vendors = Vendor::where('is_active', true)->get();
        $billId = $request->get('bill_id');
        $bill = $billId ? Bill::find($billId) : null;
        $paymentNumber = PaymentMade::generateNumber(auth()->user()->tenant_id);
        $banks = Bank::where('is_active', true)->orderBy('name')->get();
        
        return view('payments-made.create', compact('vendors', 'bill', 'paymentNumber', 'banks'));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'vendor_id' => ['required', Rule::exists('vendors', 'id')->where('tenant_id', $tenantId)],
            'bill_id' => ['nullable', Rule::exists('bills', 'id')->where('tenant_id', $tenantId)],
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50',
            'bank_id' => ['nullable', Rule::exists('banks', 'id')->where('tenant_id', $tenantId)],
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);
        
        $payment = DB::transaction(function () use ($tenantId, $validated) {
            $payment = PaymentMade::create([
                'tenant_id' => $tenantId,
                'vendor_id' => $validated['vendor_id'],
                'bill_id' => $validated['bill_id'] ?? null,
                'payment_number' => PaymentMade::generateNumber($tenantId),
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'bank_id' => $validated['bank_id'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->bankService->debit(
                $validated['bank_id'] ?? null,
                $validated['amount'],
                "Payment made #{$payment->payment_number}"
            );

            return $payment;
        });

        return redirect()->route('payments-made.show', $payment)->with('success', 'Payment recorded.');
    }

    public function show(PaymentMade $paymentMade)
    {
        $paymentMade->load(['vendor', 'bill', 'journal.entries.account']);
        return view('payments-made.show', compact('paymentMade'));
    }

    public function edit(PaymentMade $paymentMade)
    {
        $vendors = Vendor::where('is_active', true)->get();
        $bills = Bill::where('vendor_id', $paymentMade->vendor_id)
            ->whereIn('status', ['unpaid', 'partial'])
            ->get();
        $banks = Bank::where('is_active', true)->orderBy('name')->get();
        return view('payments-made.edit', compact('paymentMade', 'vendors', 'bills', 'banks'));
    }

    public function update(Request $request, PaymentMade $paymentMade)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50',
            'bank_id' => ['nullable', Rule::exists('banks', 'id')->where('tenant_id', $tenantId)],
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($paymentMade, $validated) {
            $this->bankService->adjustOnUpdate(
                $paymentMade->bank_id,
                $paymentMade->amount,
                $validated['bank_id'] ?? null,
                $validated['amount'],
                'outgoing',
                "Payment made #{$paymentMade->payment_number} updated"
            );

            $paymentMade->update($validated);
        });

        return redirect()->route('payments-made.show', $paymentMade)->with('success', 'Payment updated.');
    }

    public function destroy(PaymentMade $paymentMade)
    {
        DB::transaction(function () use ($paymentMade) {
            $this->bankService->credit(
                $paymentMade->bank_id,
                $paymentMade->amount,
                "Payment made #{$paymentMade->payment_number} deleted"
            );

            $paymentMade->delete();
        });

        return redirect()->route('payments-made.index')->with('success', 'Payment deleted.');
    }
}
