<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Inventory;
use App\Models\InventoryHistory;
use App\Models\ActivityLog;
use App\Http\Requests\StoreInvoiceRequest;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function index()
    {
        return view('invoices.index');
    }

    public function create()
    {
        $customers = Customer::where('is_active', true)->get();
        // Get items that either are services/don't track inventory OR have available stock
        $items = Item::where('is_active', true)
            ->with(['taxRate', 'taxGroup.taxRates', 'inventory'])
            ->where(function ($query) {
                $query->where('track_inventory', false)
                    ->orWhere('type', 'service')
                    ->orWhereHas('inventory', function ($q) {
                        $q->whereRaw('quantity - COALESCE(reserved_quantity, 0) > 0');
                    });
            })
            ->get();
        $invoiceNumber = Invoice::generateNumber(auth()->user()->tenant_id);
        
        return view('invoices.create', compact('customers', 'items', 'invoiceNumber'));
    }

    public function store(StoreInvoiceRequest $request)
    {
        // One transaction: invoice, lines, totals and stock reservations are
        // saved together, and stock rows stay locked from the availability
        // check until they are reserved (M4).
        return DB::transaction(fn () => $this->storeInvoice($request));
    }

    private function storeInvoice(StoreInvoiceRequest $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validated();

        // Check stock availability for each item with inventory tracking
        $stockErrors = [];
        $stockErrorDetails = [];
        foreach ($validated['items'] as $index => $itemData) {
            if (!empty($itemData['item_id'])) {
                $item = Item::with('inventory')->find($itemData['item_id']);
                if ($item && $item->track_inventory) {
                    $inventory = Inventory::where('item_id', $item->id)
                        ->where('tenant_id', $tenantId)
                        ->lockForUpdate()
                        ->first();
                    
                    $availableQty = $inventory ? $inventory->available_quantity : 0;
                    
                    if ($itemData['quantity'] > $availableQty) {
                        $stockErrors["items.{$index}.quantity"] = "Insufficient stock for '{$item->name}'. Available: {$availableQty}, Requested: {$itemData['quantity']}";
                        $stockErrorDetails[] = "{$item->name} (Available: {$availableQty})";
                    }
                }
            }
        }

        if (!empty($stockErrors)) {
            $errorMessage = 'Insufficient stock for: ' . implode(', ', $stockErrorDetails);
            return redirect()->back()
                ->withErrors($stockErrors)
                ->withInput()
                ->with('error', $errorMessage);
        }
        
        $invoice = Invoice::create([
            'tenant_id' => $tenantId,
            'customer_id' => $validated['customer_id'],
            'invoice_number' => Invoice::generateNumber($tenantId),
            'invoice_date' => $validated['invoice_date'],
            'due_date' => $validated['due_date'],
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'terms' => $validated['terms'] ?? null,
            'discount_type' => $validated['discount_type'] ?? null,
            'discount_amount' => $validated['discount_amount'] ?? 0,
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        $subtotal = 0;
        $totalTax = 0;

        foreach ($validated['items'] as $itemData) {
            $taxRate = $itemData['tax_rate'] ?? 0;
            $lineTotal = $itemData['quantity'] * $itemData['unit_price'];
            $taxAmount = $lineTotal * ($taxRate / 100);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'item_id' => $itemData['item_id'] ?? null,
                'description' => $itemData['description'],
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['unit_price'],
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total' => $lineTotal + $taxAmount,
            ]);

            $subtotal += $lineTotal;
            $totalTax += $taxAmount;
        }

        $discountAmount = $validated['discount_amount'] ?? 0;
        if (($validated['discount_type'] ?? null) === 'percentage') {
            $discountAmount = $subtotal * ($discountAmount / 100);
        }

        $total = $subtotal + $totalTax - $discountAmount;

        $invoice->update([
            'subtotal' => $subtotal,
            'tax_amount' => $totalTax,
            'discount_amount' => $discountAmount,
            'total' => $total,
            'balance_due' => $total,
        ]);

        // Reserve inventory for each item
        $this->reserveInventoryForInvoice($invoice);

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice created successfully.');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['customer', 'items.item', 'payments.createdBy', 'createdBy', 'journal.entries.account', 'refunds']);
        return view('invoices.show', compact('invoice'));
    }

    public function print(Invoice $invoice)
    {
        $invoice->load(['customer', 'items.item', 'tenant']);
        $tenant = $invoice->tenant ?? auth()->user()->tenant;
        
        // Load the active invoice template settings
        $template = $tenant->invoiceTemplate;
        if (!$template) {
            $template = \App\Models\InvoiceTemplate::where('tenant_id', $tenant->id)
                ->where('is_default', true)
                ->first();
        }

        if ($template) {
            $templateSettings = array_merge(
                \App\Models\InvoiceTemplate::getDefaultSettings(),
                $template->settings ?? []
            );
            return view('invoices.templates.print-templated', compact('invoice', 'tenant', 'templateSettings'));
        }

        return view('invoices.print', compact('invoice', 'tenant'));
    }

    public function pdf(Invoice $invoice)
    {
        // Redirect to print view for now - can be enhanced with PDF generation later
        return $this->print($invoice);
    }

    public function edit(Invoice $invoice)
    {
        $customers = Customer::where('is_active', true)->get();
        $items = Item::where('is_active', true)
            ->with(['taxRate', 'taxGroup.taxRates'])
            ->get();
        $invoice->load('items');
        
        return view('invoices.edit', compact('invoice', 'customers', 'items'));
    }

    public function update(StoreInvoiceRequest $request, Invoice $invoice)
    {
        return DB::transaction(fn () => $this->updateInvoice($request, $invoice));
    }

    private function updateInvoice(StoreInvoiceRequest $request, Invoice $invoice)
    {
        if ($invoice->status === 'paid') {
            return redirect()->back()->with('error', 'Cannot edit a paid invoice.');
        }

        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validated();

        // Check stock availability for each item (only if not released)
        if (!$invoice->isReleased()) {
            // Get currently reserved quantities from this invoice (which will be released)
            $currentReservations = [];
            foreach ($invoice->items as $existingItem) {
                if ($existingItem->item_id) {
                    $currentReservations[$existingItem->item_id] = ($currentReservations[$existingItem->item_id] ?? 0) + $existingItem->quantity;
                }
            }

            $stockErrors = [];
            $stockErrorDetails = [];
            foreach ($validated['items'] as $index => $itemData) {
                if (!empty($itemData['item_id'])) {
                    $item = Item::with('inventory')->find($itemData['item_id']);
                    if ($item && $item->track_inventory) {
                        $inventory = Inventory::where('item_id', $item->id)
                            ->where('tenant_id', $tenantId)
                            ->lockForUpdate()
                            ->first();
                        
                        // Available = current available + what will be released from this invoice
                        $currentlyReservedForThisInvoice = $currentReservations[$item->id] ?? 0;
                        $availableQty = ($inventory ? $inventory->available_quantity : 0) + $currentlyReservedForThisInvoice;
                        
                        if ($itemData['quantity'] > $availableQty) {
                            $stockErrors["items.{$index}.quantity"] = "Insufficient stock for '{$item->name}'. Available: {$availableQty}, Requested: {$itemData['quantity']}";
                            $stockErrorDetails[] = "{$item->name} (Available: {$availableQty})";
                        }
                    }
                }
            }

            if (!empty($stockErrors)) {
                $errorMessage = 'Insufficient stock for: ' . implode(', ', $stockErrorDetails);
                return redirect()->back()
                    ->withErrors($stockErrors)
                    ->withInput()
                    ->with('error', $errorMessage);
            }
        }

        // Load items fresh to ensure we have the current items for releasing reservations
        $invoice->load('items');
        
        // Release existing inventory reservations before updating
        $invoice->releaseInventoryReservation();

        $invoice->update([
            'customer_id' => $validated['customer_id'],
            'invoice_date' => $validated['invoice_date'],
            'due_date' => $validated['due_date'],
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'terms' => $validated['terms'] ?? null,
            'discount_type' => $validated['discount_type'] ?? null,
        ]);

        // Delete existing items and recreate
        $invoice->items()->delete();

        $subtotal = 0;
        $totalTax = 0;

        foreach ($validated['items'] as $itemData) {
            $taxRate = $itemData['tax_rate'] ?? 0;
            $lineTotal = $itemData['quantity'] * $itemData['unit_price'];
            $taxAmount = $lineTotal * ($taxRate / 100);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'item_id' => $itemData['item_id'] ?? null,
                'description' => $itemData['description'],
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['unit_price'],
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total' => $lineTotal + $taxAmount,
            ]);

            $subtotal += $lineTotal;
            $totalTax += $taxAmount;
        }

        $discountAmount = $validated['discount_amount'] ?? 0;
        if (($validated['discount_type'] ?? null) === 'percentage') {
            $discountAmount = $subtotal * ($discountAmount / 100);
        }

        $total = $subtotal + $totalTax - $discountAmount;

        $invoice->update([
            'subtotal' => $subtotal,
            'tax_amount' => $totalTax,
            'discount_amount' => $discountAmount,
            'total' => $total,
            'balance_due' => $total - $invoice->amount_paid,
        ]);

        // Refresh items relationship to get newly created items for reserving
        $invoice->load('items');
        
        // Reserve inventory for updated items (only if not released)
        if (!$invoice->isReleased()) {
            $this->reserveInventoryForInvoice($invoice);
        }

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice updated successfully.');
    }

    public function destroy(Invoice $invoice)
    {
        if ($invoice->amount_paid > 0) {
            return redirect()->back()->with('error', 'Cannot delete an invoice with payments.');
        }

        // Load items to ensure we can release their reservations
        $invoice->load('items');
        
        // Release inventory reservations before deleting
        $invoice->releaseInventoryReservation();

        $invoice->items()->delete();
        $invoice->delete();

        return redirect()->route('invoices.index')->with('success', 'Invoice deleted successfully.');
    }

    public function send(Invoice $invoice, NotificationService $notificationService)
    {
        // Check if customer has email
        if (!$invoice->customer || !$invoice->customer->email) {
            return redirect()->back()->with('error', 'Customer does not have an email address.');
        }

        // Send the invoice email
        $sent = $notificationService->sendInvoice($invoice);
        
        if ($sent) {
            $invoice->update(['status' => 'sent']);
            
            // Log the activity
            $invoice->logCustomActivity(ActivityLog::ACTION_SENT, "Invoice '{$invoice->invoice_number}' was sent to {$invoice->customer->email}");
            
            return redirect()->back()->with('success', "Invoice sent to {$invoice->customer->email}");
        }

        return redirect()->back()->with('error', 'Failed to send invoice. Please check the customer email address.');
    }

    public function release(Invoice $invoice)
    {
        // Check if invoice can be released
        if (!$invoice->canBeReleased()) {
            if ($invoice->isReleased()) {
                return redirect()->back()->with('error', 'Invoice has already been released.');
            }
            return redirect()->back()->with('error', 'Only paid invoices can be released.');
        }

        DB::beginTransaction();
        try {
            // Generate waybill number
            $waybillNumber = Invoice::generateWaybillNumber(auth()->user()->tenant_id);
            
            // Deduct inventory for each item (move from reserved to sold)
            foreach ($invoice->items as $invoiceItem) {
                if ($invoiceItem->item_id) {
                    $inventory = Inventory::where('item_id', $invoiceItem->item_id)
                        ->where('tenant_id', auth()->user()->tenant_id)
                        ->lockForUpdate()
                        ->first();
                    
                    if ($inventory) {
                        // Deduct from both quantity and reserved_quantity
                        $previousQty = $inventory->quantity;
                        // Refuse rather than silently clamping stock at zero (M4)
                        if ((float) $inventory->quantity < (float) $invoiceItem->quantity) {
                            throw new \RuntimeException("Not enough stock to release {$invoiceItem->description}: {$inventory->quantity} on hand, {$invoiceItem->quantity} needed.");
                        }
                        $inventory->quantity = $inventory->quantity - $invoiceItem->quantity;
                        $inventory->reserved_quantity = max(0, $inventory->reserved_quantity - $invoiceItem->quantity);
                        $inventory->save();
                        
                        // Record inventory history
                        InventoryHistory::create([
                            'tenant_id' => auth()->user()->tenant_id,
                            'item_id' => $invoiceItem->item_id,
                            'type' => 'out',
                            'quantity' => -$invoiceItem->quantity,
                            'reference_type' => 'invoice',
                            'reference_id' => $invoice->id,
                            'notes' => "Released via Invoice #{$invoice->invoice_number}, Waybill #{$waybillNumber}",
                            'created_by' => auth()->id(),
                        ]);
                    }
                }
            }
            
            // Update invoice with release info
            $invoice->update([
                'released_at' => now(),
                'waybill_number' => $waybillNumber,
            ]);
            
            // Log the activity
            $invoice->logCustomActivity(ActivityLog::ACTION_RELEASED, "Invoice '{$invoice->invoice_number}' was released with Waybill #{$waybillNumber}");
            
            DB::commit();
            
            return redirect()->back()->with('success', "Invoice released successfully. Waybill Number: {$waybillNumber}");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to release invoice: ' . $e->getMessage());
        }
    }

    public function waybill(Invoice $invoice)
    {
        if (!$invoice->isReleased()) {
            return redirect()->back()->with('error', 'Invoice has not been released yet.');
        }
        
        $invoice->load(['customer', 'items.item', 'tenant']);
        $tenant = $invoice->tenant ?? auth()->user()->tenant;
        
        return view('invoices.waybill', compact('invoice', 'tenant'));
    }

    /**
     * Reserve inventory for invoice items
     * This moves quantity from available to reserved
     * Only reserves for items that track inventory (products, not services)
     */
    private function reserveInventoryForInvoice(Invoice $invoice): void
    {
        $tenantId = auth()->user()->tenant_id;
        
        foreach ($invoice->items as $invoiceItem) {
            if ($invoiceItem->item_id) {
                // Get the item to check if it tracks inventory
                $item = Item::find($invoiceItem->item_id);
                
                // Skip reservation for services or items that don't track inventory
                if (!$item || !$item->track_inventory || $item->type === 'service') {
                    continue;
                }
                
                $inventory = Inventory::where('item_id', $invoiceItem->item_id)
                    ->where('tenant_id', $tenantId)
                    ->first();
                
                if ($inventory) {
                    $inventory->reserved_quantity = ($inventory->reserved_quantity ?? 0) + $invoiceItem->quantity;
                    $inventory->save();
                    
                    // Record inventory history
                    InventoryHistory::create([
                        'tenant_id' => $tenantId,
                        'item_id' => $invoiceItem->item_id,
                        'type' => 'reserved',
                        'quantity' => $invoiceItem->quantity,
                        'reference_type' => 'invoice',
                        'reference_id' => $invoice->id,
                        'notes' => "Reserved for Invoice #{$invoice->invoice_number}",
                        'created_by' => auth()->id(),
                    ]);
                }
            }
        }
    }
}
