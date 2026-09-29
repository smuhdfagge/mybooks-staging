<?php

namespace App\Livewire\PaymentsReceived;

use App\Livewire\Concerns\ChecksPermissions;
use App\Models\Customer;
use App\Models\PaymentReceived;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class PaymentsReceivedTable extends Component
{
    use ChecksPermissions, WithPagination;

    public $search = '';

    public $customer = '';

    public $paymentMethod = '';

    public $paymentType = ''; // '', 'regular', 'deposit'

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
        'customer' => ['except' => ''],
        'paymentMethod' => ['except' => ''],
        'paymentType' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingCustomer()
    {
        $this->resetPage();
    }

    public function updatingPaymentMethod()
    {
        $this->resetPage();
    }

    public function updatingPaymentType()
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
        return PaymentReceived::query()
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('payment_number', 'like', "%{$this->search}%")
                    ->orWhere('reference', 'like', "%{$this->search}%")
                    ->orWhereHas('customer', fn ($q2) => $q2->where('name', 'like', "%{$this->search}%"));
            }))
            ->when($this->customer, fn ($q) => $q->where('customer_id', $this->customer))
            ->when($this->paymentMethod, fn ($q) => $q->where('payment_method', $this->paymentMethod))
            ->when($this->paymentType === 'deposit', fn ($q) => $q->where('is_deposit', true))
            ->when($this->paymentType === 'regular', fn ($q) => $q->where('is_deposit', false))
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
            'delete' => 'delete payments-received',
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
                $skippedCount = 0;

                DB::transaction(function () use (&$deletedCount, &$skippedCount) {
                    foreach ($this->selectedItems as $paymentId) {
                        $payment = PaymentReceived::find($paymentId);
                        if (! $payment) {
                            continue;
                        }

                        // Same rules as the web and API delete (R3): bank balance
                        // back, journal reversed, invoice balances recalculated.
                        $delete = app(\App\Actions\Payments\DeletePaymentReceived::class);
                        if ($delete->blockedBecause($payment)) {
                            $skippedCount++;

                            continue;
                        }

                        $delete->handle($payment);
                        $deletedCount++;
                    }
                });

                if ($deletedCount > 0 && $skippedCount > 0) {
                    $this->successMessage = "Deleted {$deletedCount} payment(s). Skipped {$skippedCount} deposit(s) with applied amounts. Journal entries and chart of account balances have been updated.";
                } elseif ($deletedCount > 0) {
                    $this->successMessage = "Successfully deleted {$deletedCount} payment(s). Journal entries and chart of account balances have been updated.";
                } else {
                    $this->errorMessage = 'Could not delete any payments. Selected deposits have been applied to invoices.';
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

    public function render()
    {
        $payments = PaymentReceived::query()
            ->with(['customer', 'invoice'])
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('payment_number', 'like', "%{$this->search}%")
                    ->orWhere('reference', 'like', "%{$this->search}%")
                    ->orWhereHas('customer', fn ($q2) => $q2->where('name', 'like', "%{$this->search}%"));
            }))
            ->when($this->customer, fn ($q) => $q->where('customer_id', $this->customer))
            ->when($this->paymentMethod, fn ($q) => $q->where('payment_method', $this->paymentMethod))
            ->when($this->paymentType === 'deposit', fn ($q) => $q->where('is_deposit', true))
            ->when($this->paymentType === 'regular', fn ($q) => $q->where('is_deposit', false))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('payment_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('payment_date', '<=', $this->dateTo))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        $customers = Customer::where('is_active', true)->get();

        $paymentMethods = PaymentReceived::distinct()->pluck('payment_method')->filter();

        return view('livewire.payments-received.payments-received-table', [
            'payments' => $payments,
            'customers' => $customers,
            'paymentMethods' => $paymentMethods,
        ]);
    }
}
