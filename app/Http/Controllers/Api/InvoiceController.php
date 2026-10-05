<?php

namespace App\Http\Controllers\Api;

use App\Actions\Invoices\DeleteInvoice;
use App\Actions\Invoices\SaveInvoice;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\UnbalancedJournalException;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\ActivityLog;
use App\Models\Inventory;
use App\Models\Invoice;
use App\Services\NotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

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
    public function store(StoreInvoiceRequest $request, SaveInvoice $save): JsonResponse
    {
        // Same rules and the same code as the web form (R3, Q5).
        $invoice = $save->create($this->getTenantId(), $request->validated(), auth()->id());
        $invoice->load(['customer', 'items.item']);

        return $this->created(new InvoiceResource($invoice), 'Invoice created successfully');
    }

    /**
     * Update an invoice
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice, SaveInvoice $save): JsonResponse
    {
        $invoice = $save->update($invoice, $request->validated());
        $invoice->load(['customer', 'items.item']);

        return $this->success(new InvoiceResource($invoice), 'Invoice updated successfully');
    }

    /**
     * Delete an invoice
     */
    public function destroy(Invoice $invoice, DeleteInvoice $delete): JsonResponse
    {
        // Same rules as the web (R3).
        if ($reason = $delete->blockedBecause($invoice)) {
            return $this->error($reason, 422);
        }
        $delete->handle($invoice);

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
        if (! $invoice->customer || ! $invoice->customer->email) {
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

        if (! $invoice->canBeReleased()) {
            return $this->error('Only paid invoices can be released.', 422);
        }

        try {
            $waybillNumber = DB::transaction(function () use ($invoice) {
                // Out of the invoice's warehouse (session 12).
                $waybillNumber = $invoice->releaseStock();

                $invoice->logCustomActivity(ActivityLog::ACTION_RELEASED, "Invoice '{$invoice->invoice_number}' was released with waybill #{$waybillNumber}");

                return $waybillNumber;
            });

            return $this->success([
                'invoice' => new InvoiceResource($invoice->fresh(['customer', 'items.item'])),
                'waybill_number' => $waybillNumber,
            ], 'Invoice released successfully');
        } catch (BusinessRuleException|UnbalancedJournalException $e) {
            // A broken rule is the client's to fix (422); anything else goes to the
            // API error handler, which reports it without showing internals (I6).
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Download invoice as PDF
     */
    public function pdf(Invoice $invoice): Response
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
        // Only real transitions (finding I3). Paid, partial and overdue come
        // from payments and due dates, never from a status field.
        $validated = $request->validate([
            'status' => 'required|in:unpaid,cancelled',
        ]);

        $oldStatus = $invoice->status;
        $newStatus = $validated['status'];

        if ($newStatus === 'unpaid' && $oldStatus !== 'draft') {
            return $this->error('Only a draft invoice can be issued', 422);
        }

        if ($newStatus === 'cancelled') {
            if (! in_array($oldStatus, ['draft', 'sent', 'unpaid', 'overdue'], true)) {
                return $this->error("A {$oldStatus} invoice can't be cancelled", 422);
            }
            if ((float) $invoice->amount_paid > 0 || $invoice->creditNoteApplications()->exists()) {
                return $this->error('Remove or refund the payments and credits before cancelling this invoice', 422);
            }
        }

        DB::transaction(function () use ($invoice, $newStatus) {
            if ($newStatus === 'cancelled') {
                $invoice->releaseInventoryReservation();
            }
            // Saving fires InvoiceSaved: issuing posts the journal, cancelling reverses it.
            $invoice->update(['status' => $newStatus]);
        });

        $invoice->logCustomActivity(
            ActivityLog::ACTION_UPDATED,
            "Invoice '{$invoice->invoice_number}' status changed from {$oldStatus} to {$newStatus}"
        );

        return $this->success(
            new InvoiceResource($invoice->fresh(['customer', 'items.item'])),
            'Invoice status updated successfully'
        );
    }
}
