<?php

namespace App\Livewire\PaymentsMade;

use App\Actions\Payments\DeletePaymentMade;
use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\LimitsPageSize;
use App\Models\PaymentMade;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class PaymentsMadeTable extends Component
{
    use ChecksPermissions, LimitsPageSize, WithPagination;

    public $search = '';

    public $vendor = '';

    public $paymentMethod = '';

    public $dateFrom = '';

    public $dateTo = '';

    public $sortField = 'payment_date';

    public $sortDirection = 'desc';

    public $perPage = 10;

    // Bulk operation properties
    public $selectedItems = [];

    public $selectAll = false;

    public $bulkAction = '';

    public $successMessage = '';

    public $errorMessage = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'vendor' => ['except' => ''],
        'paymentMethod' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingVendor()
    {
        $this->resetPage();
    }

    public function updatingPaymentMethod()
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
            $this->selectedItems = $this->getFilteredPaymentIds();
        } else {
            $this->selectedItems = [];
        }
    }

    public function updatedSelectedItems()
    {
        $this->selectAll = count($this->selectedItems) === count($this->getFilteredPaymentIds());
    }

    private function getFilteredPaymentIds()
    {
        return PaymentMade::query()
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('payment_number', 'like', "%{$this->search}%")
                    ->orWhere('reference', 'like', "%{$this->search}%")
                    ->orWhereHas('vendor', fn ($q2) => $q2->where('company_name', 'like', "%{$this->search}%")->orWhere('contact_name', 'like', "%{$this->search}%"));
            }))
            ->when($this->vendor, fn ($q) => $q->where('vendor_id', $this->vendor))
            ->when($this->paymentMethod, fn ($q) => $q->where('payment_method', $this->paymentMethod))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('payment_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('payment_date', '<=', $this->dateTo))
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
            'delete' => 'delete payments-made',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Please select at least one payment.';

            return;
        }

        if (empty($this->bulkAction)) {
            $this->errorMessage = 'Please select an action.';

            return;
        }

        $count = count($this->selectedItems);

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'delete':
                $deletedCount = 0;

                DB::transaction(function () use (&$deletedCount) {
                    foreach ($this->selectedItems as $paymentId) {
                        $payment = PaymentMade::find($paymentId);
                        if (! $payment) {
                            continue;
                        }

                        // Same as the web and API delete (R3).
                        app(DeletePaymentMade::class)->handle($payment);
                        $deletedCount++;
                    }
                });

                if ($deletedCount > 0) {
                    $this->successMessage = "Successfully deleted {$deletedCount} payment(s). Journal entries and chart of account balances have been updated.";
                } else {
                    $this->errorMessage = 'Could not delete any payments.';
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

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'desc';
        }
        $this->sortField = $field;
    }

    public function clearFilters()
    {
        $this->reset(['search', 'vendor', 'paymentMethod', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function render()
    {
        $payments = PaymentMade::query()
            ->with(['vendor', 'bill'])
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('payment_number', 'like', "%{$this->search}%")
                    ->orWhere('reference', 'like', "%{$this->search}%")
                    ->orWhereHas('vendor', fn ($q2) => $q2->where('company_name', 'like', "%{$this->search}%")->orWhere('contact_name', 'like', "%{$this->search}%"));
            }))
            ->when($this->vendor, fn ($q) => $q->where('vendor_id', $this->vendor))
            ->when($this->paymentMethod, fn ($q) => $q->where('payment_method', $this->paymentMethod))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('payment_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('payment_date', '<=', $this->dateTo))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->pageSize());

        $vendors = Vendor::where('is_active', true)->orderBy('company_name')->get();

        $paymentMethods = PaymentMade::distinct()->pluck('payment_method')->filter();

        return view('livewire.payments-made.payments-made-table', [
            'payments' => $payments,
            'vendors' => $vendors,
            'paymentMethods' => $paymentMethods,
        ]);
    }
}
