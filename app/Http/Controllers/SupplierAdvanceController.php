<?php

namespace App\Http\Controllers;

use App\Actions\Payments\ApplySupplierAdvance;
use App\Actions\Payments\RecordPaymentMade;
use App\Models\Bank;
use App\Models\Bill;
use App\Models\PaymentMade;
use App\Models\Vendor;
use App\Models\WhtCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Supplier advances: money paid to a supplier before their bill, kept as
 * an asset (Supplier Advances) until it is used against a bill. Recorded
 * as a payment made with is_advance, like customer deposits.
 */
class SupplierAdvanceController extends Controller
{
    public function index(Request $request)
    {
        $advances = PaymentMade::with('vendor')
            ->where('is_advance', true)
            ->when($request->boolean('unused'), fn ($q) => $q->where('unused_amount', '>', 0))
            ->latest('payment_date')->latest('id')
            ->paginate(25)->withQueryString();

        return view('supplier-advances.index', compact('advances'));
    }

    public function create(Request $request)
    {
        $vendors = Vendor::where('is_active', true)->orderBy('name')->pluck('name', 'id');
        $banks = Bank::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $vendorId = $request->get('vendor_id');

        $whtCategories = WhtCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);

        return view('supplier-advances.create', compact('vendors', 'banks', 'vendorId', 'whtCategories'));
    }

    public function store(Request $request, RecordPaymentMade $record)
    {
        $tenantId = auth()->user()->tenant_id;
        $validated = $request->validate([
            'vendor_id' => ['required', Rule::exists('vendors', 'id')->where('tenant_id', $tenantId)],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in(['cash', 'bank_transfer', 'cheque', 'mobile_money', 'debit_card', 'other'])],
            'bank_id' => ['nullable', Rule::exists('banks', 'id')->where('tenant_id', $tenantId)],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'wht_category_id' => ['nullable', Rule::exists('wht_categories', 'id')->where('tenant_id', $tenantId)],
            'wht_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $advance = $record->handle($tenantId, $validated + ['is_advance' => true], auth()->id());

        return redirect()->route('supplier-advances.show', $advance)->with('success', 'Advance to the supplier recorded. Use it against their bill when it arrives.');
    }

    public function show(PaymentMade $advance)
    {
        abort_unless($advance->is_advance, 404);
        $advance->load(['vendor', 'bank', 'advanceApplications.bill', 'advanceApplications.appliedPayment', 'journal.entries.account']);
        $openBills = Bill::where('vendor_id', $advance->vendor_id)->where('balance_due', '>', 0)
            ->whereIn('status', ['unpaid', 'partial', 'overdue'])->orderBy('due_date')->get();

        return view('supplier-advances.show', compact('advance', 'openBills'));
    }

    public function apply(Request $request, PaymentMade $advance, ApplySupplierAdvance $apply)
    {
        abort_unless($advance->is_advance, 404);
        $validated = $request->validate([
            'bill_id' => ['required', Rule::exists('bills', 'id')->where('tenant_id', auth()->user()->tenant_id)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'application_date' => ['required', 'date'],
        ]);

        $bill = Bill::findOrFail($validated['bill_id']);
        $apply->handle($advance, $bill, (float) $validated['amount'], $validated['application_date']);

        return back()->with('success', "Advance used against bill {$bill->bill_number}.");
    }
}
