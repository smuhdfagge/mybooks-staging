<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVendorRequest;
use App\Http\Requests\UpdateVendorRequest;
use App\Models\Country;
use App\Models\State;
use App\Models\Vendor;
use App\Services\Statements\StatementBuilder;

class VendorController extends Controller
{
    public function index()
    {
        return view('vendors.index');
    }

    public function create()
    {
        $countries = Country::orderBy('name')->get();
        $states = State::orderBy('name')->get();

        return view('vendors.create', compact('countries', 'states'));
    }

    public function store(StoreVendorRequest $request)
    {
        $validated = $request->validated();

        $validated['tenant_id'] = auth()->user()->tenant_id;
        Vendor::create($validated);

        return redirect()->route('vendors.index')->with('success', 'Vendor created successfully.');
    }

    public function show(Vendor $vendor)
    {
        // Only what the page shows: counts plus the latest few (P7).
        $vendor->loadCount('bills')->load([
            'bills' => fn ($q) => $q->latest('bill_date')->latest('id')->limit(5),
            'expenses' => fn ($q) => $q->latest('expense_date')->latest('id')->limit(5),
        ]);

        // Supplier credits and advances not yet used.
        $openCredits = $vendor->vendorCredits()->where('status', 'open')->where('balance', '>', 0)->latest('credit_date')->get();
        $openAdvances = $vendor->advances()->where('unused_amount', '>', 0)->latest('payment_date')->get();

        // Unpaid bills less unused credits and advances: the statement's
        // closing balance (session 10).
        $balanceOwed = app(StatementBuilder::class)->balance($vendor);

        return view('vendors.show', compact('vendor', 'openCredits', 'openAdvances', 'balanceOwed'));
    }

    public function edit(Vendor $vendor)
    {
        $countries = Country::orderBy('name')->get();
        $states = State::orderBy('name')->get();

        return view('vendors.edit', compact('vendor', 'countries', 'states'));
    }

    public function update(UpdateVendorRequest $request, Vendor $vendor)
    {
        $validated = $request->validated();

        $vendor->update($validated);

        return redirect()->route('vendors.index')->with('success', 'Vendor updated successfully.');
    }

    public function destroy(Vendor $vendor)
    {
        $vendor->delete();

        return redirect()->route('vendors.index')->with('success', 'Vendor deleted successfully.');
    }
}
