<?php

namespace App\Livewire\Bills;

use App\Livewire\Concerns\ChecksPermissions;
use App\Models\Bill;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class BillsTable extends Component
{
    use ChecksPermissions, WithPagination;

    public $search = '';

    public $status = '';

    public $vendor_id = '';

    public $sortField = 'created_at';

    public $sortDirection = 'desc';

    public $perPage = 10;

    public $dateFrom = '';

    public $dateTo = '';

    // Bulk operation properties
    public $selectedItems = [];

    public $selectAll = false;

    public $bulkAction = '';

    public $successMessage = '';

    public $errorMessage = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'vendor_id' => ['except' => ''],
        'sortField' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatus()
    {
        $this->resetPage();
    }

    public function updatingVendorId()
    {
        $this->resetPage();
    }

    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function clearFilters()
    {
        $this->reset(['search', 'status', 'vendor_id', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedItems = $this->getFilteredBillIds();
        } else {
            $this->selectedItems = [];
        }
    }

    public function updatedSelectedItems()
    {
        $this->selectAll = count($this->selectedItems) === count($this->getFilteredBillIds());
    }

    private function getFilteredBillIds()
    {
        return Bill::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('bill_number', 'like', '%'.$this->search.'%')
                        ->orWhere('vendor_bill_number', 'like', '%'.$this->search.'%')
                        ->orWhereHas('vendor', function ($vq) {
                            $vq->where('name', 'like', '%'.$this->search.'%')
                                ->orWhere('company_name', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->vendor_id, fn ($q) => $q->where('vendor_id', $this->vendor_id))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('bill_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('bill_date', '<=', $this->dateTo))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
    }

    /**
     * Permission required for each bulk action (see ChecksPermissions).
     */
    protected function bulkActionPermissions(): array
    {
        return [
            'mark_cancelled' => 'edit bills',
            'delete' => 'delete bills',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Please select at least one bill.';

            return;
        }

        if (empty($this->bulkAction)) {
            $this->errorMessage = 'Please select an action.';

            return;
        }

        $count = count($this->selectedItems);

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'mark_cancelled':
                // Save each bill so its journal is reversed (C5). Bills with
                // payments must have the payments removed first.
                $cancelled = 0;
                $skipped = 0;
                $failed = [];
                $bills = Bill::whereIn('id', $this->selectedItems)
                    ->whereIn('status', ['draft', 'pending', 'unpaid', 'overdue'])
                    ->get();
                foreach ($bills as $bill) {
                    if ((float) $bill->amount_paid > 0) {
                        $skipped++;

                        continue;
                    }
                    try {
                        DB::transaction(fn () => $bill->update(['status' => 'cancelled']));
                        $cancelled++;
                    } catch (\Throwable $e) {
                        report($e);
                        $failed[] = $bill->bill_number;
                    }
                }
                $this->successMessage = "Cancelled {$cancelled} bill(s).".($skipped ? " Skipped {$skipped} with payments." : '');
                if ($failed) {
                    $this->errorMessage = 'Not cancelled (closed period or invalid totals): '.implode(', ', $failed).'.';
                }
                break;

            case 'delete':
                $deletedCount = 0;
                $skippedCount = 0;

                DB::transaction(function () use (&$deletedCount, &$skippedCount) {
                    foreach ($this->selectedItems as $billId) {
                        $bill = Bill::find($billId);
                        if (! $bill) {
                            continue;
                        }

                        // Same rules as the web and API delete (R3).
                        $delete = app(\App\Actions\Bills\DeleteBill::class);
                        if ($delete->blockedBecause($bill)) {
                            $skippedCount++;

                            continue;
                        }

                        $delete->handle($bill);
                        $deletedCount++;
                    }
                });

                if ($deletedCount > 0 && $skippedCount > 0) {
                    $this->successMessage = "Deleted {$deletedCount} bill(s). Skipped {$skippedCount} bill(s) with payments or stock already used. Journal entries and chart of account balances have been updated.";
                } elseif ($deletedCount > 0) {
                    $this->successMessage = "Successfully deleted {$deletedCount} bill(s). Journal entries and chart of account balances have been updated.";
                } else {
                    $this->errorMessage = 'Could not delete any bills. All selected bills have payments or stock already used.';
                }
                break;

            default:
                $this->errorMessage = 'Invalid action selected.';

                return;
        }

        $this->selectedItems = [];
        $this->selectAll = false;
        $this->bulkAction = '';
    }

    public function delete($id)
    {
        $this->requirePermission('delete bills');

        $bill = Bill::findOrFail($id);

        // Check if bill has payments
        if ($bill->amount_paid > 0) {
            session()->flash('error', 'Cannot delete a bill with recorded payments.');

            return;
        }

        $bill->items()->delete();
        $bill->delete();

        session()->flash('success', 'Bill deleted successfully.');
    }

    public function render()
    {
        $bills = Bill::query()
            ->with(['vendor'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('bill_number', 'like', '%'.$this->search.'%')
                        ->orWhere('vendor_bill_number', 'like', '%'.$this->search.'%')
                        ->orWhereHas('vendor', function ($vq) {
                            $vq->where('name', 'like', '%'.$this->search.'%')
                                ->orWhere('company_name', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->when($this->status, function ($query) {
                $query->where('status', $this->status);
            })
            ->when($this->vendor_id, function ($query) {
                $query->where('vendor_id', $this->vendor_id);
            })
            ->when($this->dateFrom, function ($query) {
                $query->whereDate('bill_date', '>=', $this->dateFrom);
            })
            ->when($this->dateTo, function ($query) {
                $query->whereDate('bill_date', '<=', $this->dateTo);
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        $vendors = Vendor::orderBy('name')->get();

        $statuses = [
            'draft' => 'Draft',
            'pending' => 'Pending',
            'partial' => 'Partial',
            'paid' => 'Paid',
            'overdue' => 'Overdue',
            'cancelled' => 'Cancelled',
        ];

        return view('livewire.bills.bills-table', compact('bills', 'vendors', 'statuses'));
    }
}
