<?php

namespace App\Http\Controllers;

use App\Actions\Payments\DeletePaymentMade;
use App\Actions\Payments\RecordPaymentMade;
use App\Http\Requests\StorePaymentMadeRequest;
use App\Models\Bank;
use App\Models\Bill;
use App\Models\PaymentMade;
use App\Models\Vendor;
use App\Services\BankService;
use App\Services\PaymentValidation;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        $paymentNumber = PaymentMade::previewNumber(auth()->user()->tenant_id);
        $banks = Bank::where('is_active', true)->orderBy('name')->get();

        return view('payments-made.create', compact('vendors', 'bill', 'paymentNumber', 'banks'));
    }

    public function store(StorePaymentMadeRequest $request, RecordPaymentMade $record)
    {
        // Same rules as the API (R3).
        $payment = $record->handle(auth()->user()->tenant_id, $request->validated(), auth()->id());

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

        // A payment that used an advance is undone by deleting it; an advance
        // that has been used can't change its amount.
        if ($paymentMade->payment_method === PaymentMade::METHOD_ADVANCE) {
            throw ValidationException::withMessages(['amount' => 'This payment used a supplier advance. Delete it and apply the advance again instead.']);
        }
        if ($paymentMade->is_advance && $paymentMade->advanceApplications()->exists()
            && ! Money::equals($validated['amount'], $paymentMade->amount)) {
            throw ValidationException::withMessages(['amount' => 'This advance has been used against bills, so its amount can\'t change.']);
        }

        // Not more than the bill still owes, counting this payment's current
        // amount as available again (M5)
        if ($paymentMade->bill && ($errors = PaymentValidation::forBill(
            $paymentMade->bill, $paymentMade->vendor_id, (float) $validated['amount'], (float) $paymentMade->amount
        ))) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($paymentMade, $validated) {
            $this->bankService->adjustOnUpdate(
                $paymentMade->bank_id,
                $paymentMade->amount,
                $validated['bank_id'] ?? null,
                $validated['amount'],
                'outgoing',
                "Payment made #{$paymentMade->payment_number} updated"
            );

            if ($paymentMade->is_advance && ! $paymentMade->advanceApplications()->exists()) {
                $validated['unused_amount'] = $validated['amount'];
            }
            $paymentMade->update($validated);
        });

        return redirect()->route('payments-made.show', $paymentMade)->with('success', 'Payment updated.');
    }

    public function destroy(PaymentMade $paymentMade, DeletePaymentMade $delete)
    {
        $delete->handle($paymentMade);

        return redirect()->route('payments-made.index')->with('success', 'Payment deleted.');
    }
}
