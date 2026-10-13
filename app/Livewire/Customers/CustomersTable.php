<?php

namespace App\Livewire\Customers;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * Customers list (tables plan T2: the shared list design, see InvoicesTable).
 */
class CustomersTable extends Component
{
    use ChecksPermissions, ListTable;

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'status'],
    ];

    protected function sortable(): array
    {
        return ['name', 'outstanding_balance', 'created_at'];
    }

    protected function rowRelations(): array
    {
        return [];
    }

    protected function baseQuery(): Builder
    {
        $query = Customer::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                ->orWhere('company_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%"));
        }

        return $query;
    }

    /** Tabs: active, owing (an unpaid balance), inactive. */
    protected function applyTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            'owing' => $query->whereHas('invoices', fn ($i) => $i->whereNotIn('status', Customer::NOT_OWED_STATUSES)->where('balance_due', '>', 0)),
            default => $query,
        };
    }

    /** Customers sort by name A–Z first. */
    public function mountListTable(): void
    {
        if (! in_array($this->sortField, $this->sortable(), true)) {
            $this->sortField = 'name';
            $this->sortDirection = 'asc';
        }
    }

    /** @return array<string, array{label: string, count: int, alert: bool}> */
    private function tabs(): array
    {
        $row = $this->baseQuery()->toBase()->selectRaw('COUNT(*) as all_rows, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active')->first();
        $owing = $this->applyTab($this->baseQuery(), 'owing')->count();

        return [
            '' => ['label' => 'All', 'count' => (int) $row->all_rows, 'alert' => false],
            'active' => ['label' => 'Active', 'count' => (int) $row->active, 'alert' => false],
            'owing' => ['label' => 'Owe you', 'count' => $owing, 'alert' => false],
            'inactive' => ['label' => 'Inactive', 'count' => (int) $row->all_rows - (int) $row->active, 'alert' => false],
        ];
    }

    /**
     * Permission required for each bulk action (see ChecksPermissions).
     */
    protected function bulkActionPermissions(): array
    {
        return [
            'activate' => 'edit customers',
            'deactivate' => 'edit customers',
            'delete' => 'delete customers',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Please select at least one customer.';

            return;
        }

        if (empty($this->bulkAction)) {
            $this->errorMessage = 'Please select an action.';

            return;
        }

        $count = count($this->selectedItems);

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'activate':
                Customer::whereIn('id', $this->selectedItems)->update(['is_active' => true]);
                $this->successMessage = "Successfully activated {$count} customer(s).";
                break;

            case 'deactivate':
                Customer::whereIn('id', $this->selectedItems)->update(['is_active' => false]);
                $this->successMessage = "Successfully deactivated {$count} customer(s).";
                break;

            case 'delete':
                $deletedCount = 0;
                $skippedCount = 0;

                foreach ($this->selectedItems as $customerId) {
                    $customer = Customer::find($customerId);
                    if (! $customer) {
                        continue;
                    }

                    // Check if customer has related records
                    if ($customer->invoices()->exists() ||
                        $customer->salesOrders()->exists() ||
                        $customer->salesReceipts()->exists() ||
                        $customer->payments()->exists()) {
                        $skippedCount++;

                        continue;
                    }

                    $customer->delete();
                    $deletedCount++;
                }

                if ($deletedCount > 0 && $skippedCount > 0) {
                    $this->successMessage = "Deleted {$deletedCount} customer(s). Skipped {$skippedCount} customer(s) with existing records.";
                } elseif ($deletedCount > 0) {
                    $this->successMessage = "Successfully deleted {$deletedCount} customer(s).";
                } else {
                    $this->errorMessage = 'Could not delete any customers. All selected customers have existing records.';
                }
                break;

            default:
                $this->errorMessage = 'Invalid action selected.';

                return;
        }

        $this->selectedItems = [];
        $this->bulkAction = '';
    }

    public function deleteCustomer($customerId)
    {
        $this->requirePermission('delete customers');

        $this->successMessage = '';
        $this->errorMessage = '';

        $customer = Customer::findOrFail($customerId);

        // Check if customer has related records that prevent deletion
        if ($customer->invoices()->exists()) {
            $this->errorMessage = 'Cannot delete customer with existing invoices.';

            return;
        }

        if ($customer->salesOrders()->exists()) {
            $this->errorMessage = 'Cannot delete customer with existing sales orders.';

            return;
        }

        if ($customer->salesReceipts()->exists()) {
            $this->errorMessage = 'Cannot delete customer with existing sales receipts.';

            return;
        }

        if ($customer->payments()->exists()) {
            $this->errorMessage = 'Cannot delete customer with existing payments.';

            return;
        }

        $customer->delete();

        $this->successMessage = 'Customer deleted successfully.';
    }

    public function render()
    {
        // Balances in the same query (P4: no query per row).
        $customers = $this->filteredQuery()->scopes(['withBalances'])
            ->orderBy($this->sortColumn(), $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->orderBy('customers.id')
            ->paginate($this->pageSize());

        $owed = Invoice::query()
            ->whereIn('customer_id', $this->filteredQuery()->select('customers.id'))
            ->whereNotIn('status', Customer::NOT_OWED_STATUSES)
            ->sum('balance_due');

        return view('livewire.customers.customers-table', [
            'customers' => $customers,
            'tabs' => $this->tabs(),
            'totals' => (object) ['n' => $customers->total(), 'owed' => (float) $owed],
            'filtered' => $this->isFiltered(),
        ]);
    }
}
