<?php

namespace App\Http\Controllers;

use App\Enums\CreditNoteStatus;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Country;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\State;

class CustomerController extends Controller
{
    public function index()
    {
        return view('customers.index');
    }

    public function create()
    {
        $countries = Country::orderBy('name')->get();
        $states = State::orderBy('name')->get();

        return view('customers.create', compact('countries', 'states'));
    }

    public function store(StoreCustomerRequest $request)
    {
        $validated = $request->validated();

        $validated['tenant_id'] = auth()->user()->tenant_id;
        Customer::create($validated);

        return redirect()->route('customers.index')->with('success', 'Customer created successfully.');
    }

    public function show(Customer $customer)
    {
        // Only what the page shows: counts plus the latest few (P7).
        $customer->loadCount('invoices')->load([
            'invoices' => fn ($q) => $q->latest('invoice_date')->latest('id')->limit(10),
            'payments' => fn ($q) => $q->with('invoice')->latest('payment_date')->latest('id')->limit(5),
        ]);
        // Posted credit notes with credit left to use.
        $openCredits = CreditNote::where('customer_id', $customer->id)->where('status', CreditNoteStatus::Open->value)
            ->where('balance', '>', 0)->latest('credit_note_date')->latest('id')->get();

        return view('customers.show', compact('customer', 'openCredits'));
    }

    public function edit(Customer $customer)
    {
        $countries = Country::orderBy('name')->get();
        $states = State::orderBy('name')->get();

        return view('customers.edit', compact('customer', 'countries', 'states'));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $validated = $request->validated();

        $customer->update($validated);

        return redirect()->route('customers.index')->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'Customer deleted successfully.');
    }
}
