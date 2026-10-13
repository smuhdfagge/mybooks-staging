<?php

namespace App\Livewire\SalesReceipts;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\Customer;
use App\Models\SalesReceipt;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * Sales receipts list (tables plan T2: the shared list design, see InvoicesTable).
 */
class SalesReceiptsTable extends Component
{
    use ChecksPermissions, ListTable;

    public string $period = '';

    public string $customer = '';

    public string $method = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'period' => ['except' => ''],
        'customer' => ['except' => ''],
        'method' => ['except' => ''],
    ];

    protected function sortable(): array
    {
        return ['receipt_date', 'receipt_number', 'total'];
    }

    protected function filterProperties(): array
    {
        return ['period', 'customer', 'method'];
    }

    protected function rowRelations(): array
    {
        return ['customer:id,name'];
    }

    protected function baseQuery(): Builder
    {
        $query = SalesReceipt::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('receipt_number', 'like', "%{$term}%")
                ->orWhere('reference', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%")->orWhere('company_name', 'like', "%{$term}%")));
        }
        if ($this->customer !== '' && ctype_digit($this->customer)) {
            $query->where('customer_id', (int) $this->customer);
        }
        if ($this->method !== '') {
            $query->where('payment_method', $this->method);
        }

        return $this->applyPeriod($query, 'receipt_date', $this->period);
    }

    /**
     * Permission required for each bulk action (see ChecksPermissions).
     */
    protected function bulkActionPermissions(): array
    {
        return [
            'delete' => 'delete sales-receipts',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Please select at least one receipt.';

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
                SalesReceipt::whereIn('id', $this->selectedItems)->each(function ($receipt) {
                    $receipt->items()->delete();
                    $receipt->delete();
                });
                $this->successMessage = "Successfully deleted {$count} receipt(s).";
                break;

            default:
                $this->errorMessage = 'Invalid action selected.';

                return;
        }

        $this->selectedItems = [];
        $this->bulkAction = '';
    }

    public function render()
    {
        return view('livewire.sales-receipts.sales-receipts-table', [
            'receipts' => $this->rows(),
            'totals' => $this->filteredQuery()->toBase()->selectRaw('COUNT(*) as n, COALESCE(SUM(total), 0) as total')->first(),
            'customers' => Customer::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'methods' => SalesReceipt::query()->whereNotNull('payment_method')->distinct()->orderBy('payment_method')->pluck('payment_method'),
            'periods' => self::periodOptions(),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
