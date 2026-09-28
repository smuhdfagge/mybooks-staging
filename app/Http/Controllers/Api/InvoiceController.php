<?php

namespace App\Http\Controllers\Api;

use App\Models\Invoice;
use App\Models\Inventory;
use App\Models\InventoryHistory;
use App\Models\ActivityLog;
use App\Http\Resources\InvoiceResource;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends BaseApiController
{
    /**
     * Get all invoices
     */
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with(['customer', 'items.item']);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by customer
        if ($customerId = $request->input('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        // Filter by date range
        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('invoice_date', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('invoice_date', '<=', $toDate);
        }

        // Filter overdue invoices
        if ($request->boolean('overdue')) {
            $query->where('status', '!=', 'paid')
                ->whereDate('due_date', '<', now());
        }

        // Sorting
        [$sortBy, $sortOrder] = $this->validateSortParameters(
            $request,
            ['invoice_number', 'invoice_date', 'due_date', 'status', 'total', 'balance_due', 'created_at', 'updated_at'],
            'created_at'
        );
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $invoices = $query->paginate($this->validatedPerPage($request));

        return $this->paginated($invoices->through(fn ($invoice) => new InvoiceResource($invoice)));
    }

    /**
     * Get a specific invoice
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['customer', 'items.item', 'payments', 'createdBy']);
        return $this->success(new InvoiceResource($invoice));
    }

    /**
     * Create a new invoice
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'sales_order_id' => ['nullable', Rule::exists('sales_orders', 'id')->where('tenant_id', $tenantId)],
            'reference' => 'nullable|string|max:100',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'status' => 'sometimes|in:draft,unpaid,partial,paid,overdue,cancelled',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:fixed,percentage',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'nullable|in:fixed,percentage',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        // Generate invoice number
        $validated['invoice_number'] = Invoice::generateNumber($tenantId);
        $validated['tenant_id'] = $tenantId;
        $validated['created_by'] = auth()->id();

        // Calculate totals
        $subtotal = 0;
        $taxAmount = 0;

        foreach ($validated['items'] as &$item) {
            $itemSubtotal = $item['quantity'] * $item['unit_price'];

            // Apply item discount
            if (!empty($item['discount'])) {
                if (($item['discount_type'] ?? 'fixed') === 'percentage') {
                    $itemSubtotal -= $itemSubtotal * ($item['discount'] / 100);
                } else {
                    $itemSubtotal -= $item['discount'];
                }
            }

            // Calculate tax
            $itemTax = 0;
            if (!empty($item['tax_rate'])) {
                $itemTax = $itemSubtotal * ($item['tax_rate'] / 100);
            }

            $item['tax_amount'] = $itemTax;
            $item['total'] = $itemSubtotal + $itemTax;

            $subtotal += $itemSubtotal;
            $taxAmount += $itemTax;
        }

        // Apply invoice-level discount
        $discountAmount = 0;
        if (!empty($validated['discount_amount'])) {
            if (($validated['discount_type'] ?? 'fixed') === 'percentage') {
                $discountAmount = $subtotal * ($validated['discount_amount'] / 100);
            } else {
                $discountAmount = $validated['discount_amount'];
            }
        }

        $validated['subtotal'] = $subtotal;
        $validated['tax_amount'] = $taxAmount;
        $validated['discount_amount'] = $discountAmount;
        $validated['total'] = $subtotal + $taxAmount - $discountAmount;
        $validated['balance_due'] = $validated['total'];
        $validated['amount_paid'] = 0;

        // Invoice and its lines are saved together (M4)
        $invoice = DB::transaction(function () use ($validated) {
            $invoice = Invoice::create(collect($validated)->except('items')->toArray());

            foreach ($validated['items'] as $item) {
                $invoice->items()->create($item);
            }

            return $invoice;
        });

        $invoice->load(['customer', 'items.item']);

        return $this->created(new InvoiceResource($invoice), 'Invoice created successfully');
    }

    /**
     * Update an invoice
     */
    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        // Only allow updates on draft invoices
        if ($invoice->status !== 'draft' && !$request->user()->can('edit invoices')) {
            return $this->forbidden('Cannot modify a non-draft invoice');
        }

        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'customer_id' => ['sometimes', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'reference' => 'nullable|string|max:100',
            'invoice_date' => 'sometimes|date',
            'due_date' => 'sometimes|date|after_or_equal:invoice_date',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:fixed,percentage',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'items' => 'sometimes|array|min:1',
            'items.*.id' => ['nullable', Rule::exists('invoice_items', 'id')->where('invoice_id', $invoice->id)],
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'nullable|in:fixed,percentage',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        if (isset($validated['items'])) {
            // Recalculate totals
            $subtotal = 0;
            $taxAmount = 0;

            foreach ($validated['items'] as &$item) {
                $itemSubtotal = $item['quantity'] * $item['unit_price'];

                if (!empty($item['discount'])) {
                    if (($item['discount_type'] ?? 'fixed') === 'percentage') {
                        $itemSubtotal -= $itemSubtotal * ($item['discount'] / 100);
                    } else {
                        $itemSubtotal -= $item['discount'];
                    }
                }

                $itemTax = 0;
                if (!empty($item['tax_rate'])) {
                    $itemTax = $itemSubtotal * ($item['tax_rate'] / 100);
                }

                $item['tax_amount'] = $itemTax;
                $item['total'] = $itemSubtotal + $itemTax;

                $subtotal += $itemSubtotal;
                $taxAmount += $itemTax;
            }

            $discountAmount = 0;
            if (!empty($validated['discount_amount'])) {
                if (($validated['discount_type'] ?? 'fixed') === 'percentage') {
                    $discountAmount = $subtotal * ($validated['discount_amount'] / 100);
                } else {
                    $discountAmount = $validated['discount_amount'];
                }
            }

            $validated['subtotal'] = $subtotal;
            $validated['tax_amount'] = $taxAmount;
            $validated['discount_amount'] = $discountAmount;
            $validated['total'] = $subtotal + $taxAmount - $discountAmount;
            $validated['balance_due'] = $validated['total'] - $invoice->amount_paid;

            // Update items
            $invoice->items()->delete();
            foreach ($validated['items'] as $item) {
                $invoice->items()->create($item);
            }
        }

        $invoice->update(collect($validated)->except('items')->toArray());
        $invoice->load(['customer', 'items.item']);

        return $this->success(new InvoiceResource($invoice), 'Invoice updated successfully');
    }

    /**
     * Delete an invoice
     */
    public function destroy(Invoice $invoice): JsonResponse
    {
        if ($invoice->payments()->exists()) {
            return $this->error('Cannot delete invoice with payments', 422);
        }

        $invoice->items()->delete();
        $invoice->delete();

        return $this->success(null, 'Invoice deleted successfully');
    }

    /**
     * Get invoice summary/statistics
     */
    public function summary(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $totalInvoices = Invoice::where('tenant_id', $tenantId)->count();
        $totalAmount = Invoice::where('tenant_id', $tenantId)->sum('total');
        $totalPaid = Invoice::where('tenant_id', $tenantId)->sum('amount_paid');
        $totalOutstanding = Invoice::where('tenant_id', $tenantId)
            ->whereIn('status', ['unpaid', 'partial', 'overdue'])
            ->sum('balance_due');
        $overdueCount = Invoice::where('tenant_id', $tenantId)
            ->where('status', '!=', 'paid')
            ->whereDate('due_date', '<', now())
            ->count();

        return $this->success([
            'total_invoices' => $totalInvoices,
            'total_amount' => (float) $totalAmount,
            'total_paid' => (float) $totalPaid,
            'total_outstanding' => (float) $totalOutstanding,
            'overdue_count' => $overdueCount,
            'by_status' => [
                'draft' => Invoice::where('tenant_id', $tenantId)->where('status', 'draft')->count(),
                'unpaid' => Invoice::where('tenant_id', $tenantId)->where('status', 'unpaid')->count(),
                'partial' => Invoice::where('tenant_id', $tenantId)->where('status', 'partial')->count(),
                'paid' => Invoice::where('tenant_id', $tenantId)->where('status', 'paid')->count(),
                'overdue' => Invoice::where('tenant_id', $tenantId)->where('status', 'overdue')->count(),
            ],
        ]);
    }

    /**
     * Send invoice via email
     */
    public function send(Invoice $invoice, NotificationService $notificationService): JsonResponse
    {
        if (!$invoice->customer || !$invoice->customer->email) {
            return $this->error('Customer does not have an email address.', 422);
        }

        $sent = $notificationService->sendInvoice($invoice);

        if ($sent) {
            if ($invoice->status === 'draft') {
                $invoice->update(['status' => 'unpaid']);
            }

            $invoice->logCustomActivity(ActivityLog::ACTION_SENT, "Invoice '{$invoice->invoice_number}' was sent to {$invoice->customer->email}");

            return $this->success([
                'invoice' => new InvoiceResource($invoice->fresh(['customer', 'items.item'])),
                'sent_to' => $invoice->customer->email,
            ], "Invoice sent to {$invoice->customer->email}");
        }

        return $this->error('Failed to send invoice. Please check the customer email address.', 500);
    }

    /**
     * Release invoice (deduct inventory)
     */
    public function release(Invoice $invoice): JsonResponse
    {
        if ($invoice->isReleased()) {
            return $this->error('Invoice has already been released.', 422);
        }

        if (!$invoice->canBeReleased()) {
            return $this->error('Only paid invoices can be released.', 422);
        }

        DB::beginTransaction();
        try {
            $waybillNumber = Invoice::generateWaybillNumber($invoice->tenant_id);

            foreach ($invoice->items as $invoiceItem) {
                if ($invoiceItem->item_id) {
                    $inventory = Inventory::where('item_id', $invoiceItem->item_id)
                        ->where('tenant_id', $invoice->tenant_id)
                        ->lockForUpdate()
                        ->first();

                    if ($inventory) {
                        // Refuse rather than silently clamping stock at zero (M4)
                        if ((float) $inventory->quantity < (float) $invoiceItem->quantity) {
                            throw new \RuntimeException("Not enough stock to release {$invoiceItem->description}: {$inventory->quantity} on hand, {$invoiceItem->quantity} needed.");
                        }
                        $inventory->quantity = $inventory->quantity - $invoiceItem->quantity;
                        $inventory->reserved_quantity = max(0, $inventory->reserved_quantity - $invoiceItem->quantity);
                        $inventory->save();

                        InventoryHistory::create([
                            'tenant_id' => $invoice->tenant_id,
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

            $invoice->update([
                'released_at' => now(),
                'waybill_number' => $waybillNumber,
            ]);

            $invoice->logCustomActivity(ActivityLog::ACTION_RELEASED, "Invoice '{$invoice->invoice_number}' was released with waybill #{$waybillNumber}");

            DB::commit();

            return $this->success([
                'invoice' => new InvoiceResource($invoice->fresh(['customer', 'items.item'])),
                'waybill_number' => $waybillNumber,
            ], 'Invoice released successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to release invoice: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Download invoice as PDF
     */
    public function pdf(Invoice $invoice): \Illuminate\Http\Response
    {
        $invoice->load(['customer', 'items.item', 'tenant']);
        $tenant = $invoice->tenant ?? auth()->user()->tenant;

        // Same layout as the web print page ('invoices.pdf' never existed, N9)
        $pdf = Pdf::loadView('invoices.print', compact('invoice', 'tenant'));

        return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
    }

    /**
     * Mark invoice status
     */
    public function updateStatus(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:draft,unpaid,partial,paid,overdue,cancelled',
        ]);

        $oldStatus = $invoice->status;
        $invoice->update(['status' => $validated['status']]);

        $invoice->logCustomActivity(
            ActivityLog::ACTION_UPDATED,
            "Invoice '{$invoice->invoice_number}' status changed from {$oldStatus} to {$validated['status']}"
        );

        return $this->success(
            new InvoiceResource($invoice->fresh(['customer', 'items.item'])),
            'Invoice status updated successfully'
        );
    }
}
