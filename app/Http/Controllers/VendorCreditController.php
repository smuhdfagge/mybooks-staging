<?php

namespace App\Http\Controllers;

use App\Actions\VendorCredits\ApplyVendorCredit;
use App\Actions\VendorCredits\DeleteVendorCredit;
use App\Actions\VendorCredits\OpenVendorCredit;
use App\Actions\VendorCredits\RefundVendorCredit;
use App\Actions\VendorCredits\SaveVendorCredit;
use App\Actions\VendorCredits\VoidVendorCredit;
use App\Enums\VendorCreditStatus;
use App\Models\Bank;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Item;
use App\Models\Vendor;
use App\Models\VendorCredit;
use App\Models\Warehouse;
use App\Services\Accounting\VatTreatment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Supplier credits: goods sent back to a supplier, or a credit note the
 * supplier gave us. The rules live in App\Actions\VendorCredits.
 */
class VendorCreditController extends Controller
{
    /** The list itself is a Livewire table (tables plan T3). */
    public function index()
    {
        return view('vendor-credits.index');
    }

    public function create(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $bill = $request->filled('bill_id') ? Bill::with('items.item', 'vendor')->findOrFail($request->integer('bill_id')) : null;
        $vendorId = $bill->vendor_id ?? $request->get('vendor_id');

        $vendors = Vendor::where('is_active', true)->orderBy('name')->pluck('name', 'id');
        $items = Item::where('is_active', true)->orderBy('name')->get(['id', 'name', 'cost_price', 'track_inventory', 'tax_rate']);
        $accounts = ChartOfAccount::where('is_active', true)->whereIn('type', ['expense', 'asset'])
            ->orderBy('account_code')->get(['id', 'account_code', 'name']);
        $number = VendorCredit::previewNumber($tenantId);
        $reasons = VendorCredit::REASONS;

        // Lines start from the bill's goods (quantity 0: say how many go back).
        $lines = $bill ? $bill->items->map(fn ($l) => [
            'item_id' => $l->item_id, 'account_id' => null, 'description' => $l->description,
            'quantity' => 0, 'billed' => (float) $l->quantity, 'unit_price' => (float) $l->unit_price, 'tax_rate' => (float) $l->tax_rate,
        ])->values()->all() : [];

        return view('vendor-credits.create', compact('bill', 'vendorId', 'vendors', 'items', 'accounts', 'number', 'reasons', 'lines'));
    }

    public function store(Request $request, SaveVendorCredit $save)
    {
        $tenantId = auth()->user()->tenant_id;
        $validated = $request->validate([
            'vendor_id' => ['required', Rule::exists('vendors', 'id')->where('tenant_id', $tenantId)],
            'bill_id' => ['nullable', Rule::exists('bills', 'id')->where('tenant_id', $tenantId)],
            'credit_date' => ['required', 'date'],
            'vendor_reference' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', Rule::in(array_keys(VendorCredit::REASONS))],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Goods go back from here; empty = the bill's warehouse (session 12).
            'warehouse_id' => Warehouse::rule($tenantId),
            'status' => ['nullable', Rule::in(VendorCreditStatus::startValues())],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'items.*.account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.vat_treatment' => ['nullable', Rule::in(VatTreatment::ALL)],
        ]);

        $credit = $save->create($tenantId, $validated, auth()->id());

        return redirect()->route('vendor-credits.show', $credit)->with('success', $credit->status === 'open'
            ? 'Supplier credit saved and posted. You can now use it against a bill or record a refund.'
            : 'Supplier credit saved as a draft. Open it when you are ready to post it.');
    }

    public function show(VendorCredit $vendorCredit)
    {
        $vendorCredit->load(['vendor', 'bill', 'items.item', 'items.account', 'applications.bill', 'refunds.bank', 'createdBy', 'journals.entries.account']);
        $banks = Bank::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $openBills = $vendorCredit->isOpen()
            ? Bill::where('vendor_id', $vendorCredit->vendor_id)->where('balance_due', '>', 0)
                ->whereIn('status', ['unpaid', 'partial', 'overdue'])->orderBy('due_date')->get()
            : collect();

        return view('vendor-credits.show', ['credit' => $vendorCredit, 'banks' => $banks, 'openBills' => $openBills]);
    }

    public function open(VendorCredit $vendorCredit, OpenVendorCredit $open)
    {
        $open->handle($vendorCredit);

        return back()->with('success', 'Supplier credit opened and posted.');
    }

    public function void(VendorCredit $vendorCredit, VoidVendorCredit $void)
    {
        $void->handle($vendorCredit);

        return back()->with('success', 'Supplier credit voided. Its journal was reversed and any returned goods are back in stock.');
    }

    public function destroy(VendorCredit $vendorCredit, DeleteVendorCredit $delete)
    {
        $delete->handle($vendorCredit);

        return redirect()->route('vendor-credits.index')->with('success', 'Supplier credit deleted.');
    }

    public function apply(Request $request, VendorCredit $vendorCredit, ApplyVendorCredit $apply)
    {
        $validated = $request->validate([
            'bill_id' => ['required', Rule::exists('bills', 'id')->where('tenant_id', auth()->user()->tenant_id)],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $bill = Bill::findOrFail($validated['bill_id']);
        $apply->handle($vendorCredit, $bill, (float) $validated['amount']);

        return back()->with('success', "Credit used against bill {$bill->bill_number}.");
    }

    public function refund(Request $request, VendorCredit $vendorCredit, RefundVendorCredit $refund)
    {
        $tenantId = auth()->user()->tenant_id;
        $validated = $request->validate([
            'refund_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in(['cash', 'bank_transfer', 'cheque', 'mobile_money', 'other'])],
            'bank_id' => ['nullable', Rule::exists('banks', 'id')->where('tenant_id', $tenantId)],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $refund->handle($vendorCredit, $validated, auth()->id());

        return back()->with('success', 'Refund from the supplier recorded.');
    }
}
