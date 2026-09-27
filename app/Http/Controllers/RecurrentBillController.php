<?php

namespace App\Http\Controllers;

use App\Models\RecurrentBill;
use App\Models\RecurrentBillItem;
use App\Models\Vendor;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RecurrentBillController extends Controller
{
    public function index()
    {
        return view('recurrent-bills.index');
    }

    public function create()
    {
        $vendors = Vendor::where('is_active', true)->get();
        $items = Item::where('is_active', true)->get();
        return view('recurrent-bills.create', compact('vendors', 'items'));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'vendor_id' => ['required', Rule::exists('vendors', 'id')->where('tenant_id', $tenantId)],
            'profile_name' => 'required|string|max:255',
            'frequency' => 'required|in:weekly,monthly,quarterly,yearly',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        DB::beginTransaction();

        try {
            $subtotal = 0;
            $totalTax = 0;

            foreach ($validated['items'] as $item) {
                $itemTotal = $item['quantity'] * $item['unit_price'];
                $tax = $itemTotal * (($item['tax_rate'] ?? 0) / 100);
                $subtotal += $itemTotal;
                $totalTax += $tax;
            }

            $recurrentBill = RecurrentBill::create([
                'tenant_id' => $tenantId,
                'vendor_id' => $validated['vendor_id'],
                'profile_name' => $validated['profile_name'],
                'frequency' => $validated['frequency'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? null,
                'next_bill_date' => $validated['start_date'],
                'subtotal' => $subtotal,
                'tax_amount' => $totalTax,
                'total' => $subtotal + $totalTax,
                'notes' => $validated['notes'] ?? null,
                'status' => 'active',
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $itemTotal = $item['quantity'] * $item['unit_price'];
                $tax = $itemTotal * (($item['tax_rate'] ?? 0) / 100);

                RecurrentBillItem::create([
                    'recurrent_bill_id' => $recurrentBill->id,
                    'item_id' => $item['item_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'] ?? 0,
                    'tax_amount' => $tax,
                    'total' => $itemTotal + $tax,
                ]);
            }

            DB::commit();

            return redirect()->route('recurrent-bills.show', $recurrentBill)->with('success', 'Recurrent bill profile created.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(['error' => 'Failed to create recurrent bill profile.']);
        }
    }

    public function show(RecurrentBill $recurrentBill)
    {
        $recurrentBill->load(['vendor', 'items.item', 'bills']);
        return view('recurrent-bills.show', compact('recurrentBill'));
    }

    public function edit(RecurrentBill $recurrentBill)
    {
        $recurrentBill->load('items');
        $vendors = Vendor::where('is_active', true)->get();
        $items = Item::where('is_active', true)->get();
        return view('recurrent-bills.edit', compact('recurrentBill', 'vendors', 'items'));
    }

    public function update(Request $request, RecurrentBill $recurrentBill)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'profile_name' => 'required|string|max:255',
            'frequency' => 'required|in:weekly,monthly,quarterly,yearly',
            'end_date' => 'nullable|date|after:start_date',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:active,paused,stopped',
            'items' => 'required|array|min:1',
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        DB::beginTransaction();

        try {
            $subtotal = 0;
            $totalTax = 0;

            foreach ($validated['items'] as $item) {
                $itemTotal = $item['quantity'] * $item['unit_price'];
                $tax = $itemTotal * (($item['tax_rate'] ?? 0) / 100);
                $subtotal += $itemTotal;
                $totalTax += $tax;
            }

            $recurrentBill->update([
                'profile_name' => $validated['profile_name'],
                'frequency' => $validated['frequency'],
                'end_date' => $validated['end_date'] ?? null,
                'subtotal' => $subtotal,
                'tax_amount' => $totalTax,
                'total' => $subtotal + $totalTax,
                'notes' => $validated['notes'] ?? null,
                'status' => $validated['status'] ?? $recurrentBill->status,
            ]);

            $recurrentBill->items()->delete();

            foreach ($validated['items'] as $item) {
                $itemTotal = $item['quantity'] * $item['unit_price'];
                $tax = $itemTotal * (($item['tax_rate'] ?? 0) / 100);

                RecurrentBillItem::create([
                    'recurrent_bill_id' => $recurrentBill->id,
                    'item_id' => $item['item_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'] ?? 0,
                    'tax_amount' => $tax,
                    'total' => $itemTotal + $tax,
                ]);
            }

            DB::commit();

            return redirect()->route('recurrent-bills.show', $recurrentBill)->with('success', 'Recurrent bill profile updated.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(['error' => 'Failed to update recurrent bill profile.']);
        }
    }

    public function destroy(RecurrentBill $recurrentBill)
    {
        $recurrentBill->items()->delete();
        $recurrentBill->delete();
        return redirect()->route('recurrent-bills.index')->with('success', 'Recurrent bill profile deleted.');
    }

    public function toggleStatus(RecurrentBill $recurrentBill)
    {
        $newStatus = $recurrentBill->status === 'active' ? 'paused' : 'active';
        $recurrentBill->update(['status' => $newStatus]);
        $label = $newStatus === 'active' ? 'activated' : 'paused';
        return redirect()->route('recurrent-bills.show', $recurrentBill)->with('success', "Profile {$label}.");
    }
}
