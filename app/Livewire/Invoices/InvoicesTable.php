<?php

namespace App\Livewire\Invoices;

use App\Models\Invoice;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use App\Livewire\Concerns\ChecksPermissions;

class InvoicesTable extends Component
{
    use ChecksPermissions, WithPagination;

    public $search = '';
    public $status = '';
    public $customer = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $sortField = 'invoice_date';
    public $sortDirection = 'desc';
    public $perPage = 10;

    // Bulk operation properties
    public $selectedItems = [];
    public $selectAll = false;
    public $bulkAction = '';

    // Flash message properties
    public $successMessage = '';
    public $errorMessage = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'customer' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatus()
    {
        $this->resetPage();
    }

    public function updatingCustomer()
    {
        $this->resetPage();
    }

    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedItems = $this->getFilteredInvoiceIds();
        } else {
            $this->selectedItems = [];
        }
    }

    public function updatedSelectedItems()
    {
        $this->selectAll = count($this->selectedItems) === count($this->getFilteredInvoiceIds());
    }

    private function getFilteredInvoiceIds()
    {
        return Invoice::query()
            ->when($this->search, fn($q) => $q->where(function($query) {
                $query->where('invoice_number', 'like', "%{$this->search}%")
                    ->orWhereHas('customer', fn($q2) => $q2->where('name', 'like', "%{$this->search}%"));
            }))
            ->when($this->status, fn($q) => $q->where('status', $this->status))
            ->when($this->customer, fn($q) => $q->where('customer_id', $this->customer))
            ->when($this->dateFrom, fn($q) => $q->whereDate('invoice_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->whereDate('invoice_date', '<=', $this->dateTo))
            ->pluck('id')
            ->map(fn($id) => (string) $id)
            ->toArray();
    }

    /**
     * Permission required for each bulk action (see ChecksPermissions).
     */
    protected function bulkActionPermissions(): array
    {
        return [
            'mark_sent' => 'send invoices',
            'mark_cancelled' => 'edit invoices',
            'delete' => 'delete invoices',
        ];
    }

    public function applyBulkAction()
    {
        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Please select at least one invoice.';
            return;
        }

        if (empty($this->bulkAction)) {
            $this->errorMessage = 'Please select an action.';
            return;
        }

        $count = count($this->selectedItems);

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'mark_sent':
                // Save each invoice so its journal is created and the closed-
                // period check runs (C5). A query-builder update skipped both.
                $sent = 0;
                $failed = [];
                foreach (Invoice::whereIn('id', $this->selectedItems)->where('status', 'draft')->get() as $invoice) {
                    try {
                        DB::transaction(fn () => $invoice->update(['status' => 'sent']));
                        $sent++;
                    } catch (\Throwable $e) {
                        report($e);
                        $failed[] = $invoice->invoice_number;
                    }
                }
                $this->successMessage = "Marked {$sent} invoice(s) as sent.";
                if ($failed) {
                    $this->errorMessage = 'Not changed (closed period or invalid totals): ' . implode(', ', $failed) . '.';
                }
                break;

            case 'mark_cancelled':
                // Cancelling a posted invoice reverses its journal and releases
                // any stock it reserved. Invoices with payments are skipped.
                $cancelled = 0;
                $skipped = 0;
                $failed = [];
                $invoices = Invoice::with('items')->whereIn('id', $this->selectedItems)
                    ->whereIn('status', ['draft', 'sent', 'unpaid', 'overdue'])
                    ->get();
                foreach ($invoices as $invoice) {
                    if ((float) $invoice->amount_paid > 0) {
                        $skipped++;
                        continue;
                    }
                    try {
                        DB::transaction(function () use ($invoice) {
                            $invoice->releaseInventoryReservation();
                            $invoice->update(['status' => 'cancelled']);
                        });
                        $cancelled++;
                    } catch (\Throwable $e) {
                        report($e);
                        $failed[] = $invoice->invoice_number;
                    }
                }
                $this->successMessage = "Cancelled {$cancelled} invoice(s)." . ($skipped ? " Skipped {$skipped} with payments." : '');
                if ($failed) {
                    $this->errorMessage = 'Not cancelled (closed period or invalid totals): ' . implode(', ', $failed) . '.';
                }
                break;

            case 'delete':
                $deletedCount = 0;
                DB::transaction(function () use (&$deletedCount) {
                    foreach ($this->selectedItems as $invoiceId) {
                        $invoice = Invoice::with('items')->find($invoiceId);
                        if ($invoice) {
                            // Skip invoices with payments
                            if ($invoice->amount_paid > 0) {
                                continue;
                            }
                            // Release inventory reservations before deleting
                            $invoice->releaseInventoryReservation();
                            // Delete invoice items
                            $invoice->items()->delete();
                            // Delete invoice - this triggers the deleting event which cleans up journals
                            $invoice->delete();
                            $deletedCount++;
                        }
                    }
                });
                $this->successMessage = "Successfully deleted {$deletedCount} invoice(s). Journal entries, chart of account balances, and inventory reservations have been updated.";
                break;

            default:
                $this->errorMessage = 'Invalid action selected.';
                return;
        }

        $this->selectedItems = [];
        $this->selectAll = false;
        $this->bulkAction = '';
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'desc';
        }
        $this->sortField = $field;
    }

    public function render()
    {
        $invoices = Invoice::query()
            ->with('customer')
            ->when($this->search, fn($q) => $q->where(function($query) {
                $query->where('invoice_number', 'like', "%{$this->search}%")
                    ->orWhereHas('customer', fn($q2) => $q2->where('name', 'like', "%{$this->search}%"));
            }))
            ->when($this->status, fn($q) => $q->where('status', $this->status))
            ->when($this->customer, fn($q) => $q->where('customer_id', $this->customer))
            ->when($this->dateFrom, fn($q) => $q->whereDate('invoice_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->whereDate('invoice_date', '<=', $this->dateTo))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        $customers = Customer::where('is_active', true)->get();

        return view('livewire.invoices.invoices-table', [
            'invoices' => $invoices,
            'customers' => $customers,
        ]);
    }
}
