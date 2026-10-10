<?php

namespace App\Livewire\PurchaseOrders;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Purchase orders list (tables plan T3: the shared list design, see InvoicesTable).
 */
class PurchaseOrdersTable extends Component
{
    use ChecksPermissions, ListTable;

    public string $period = '';

    public string $vendor = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'status'],
        'period' => ['except' => ''],
        'vendor' => ['except' => ''],
    ];

    /** Status => tab label, in tab order. */
    public const LABELS = [
        'draft' => 'Draft',
        'confirmed' => 'Confirmed',
        'partially_received' => 'Part received',
        'received' => 'Received',
        'billed' => 'Billed',
        'cancelled' => 'Cancelled',
    ];

    protected function sortable(): array
    {
        return ['order_date', 'order_number', 'expected_date', 'total'];
    }

    protected function rowRelations(): array
    {
        return ['vendor:id,name'];
    }

    protected function filterProperties(): array
    {
        return ['period', 'vendor'];
    }

    protected function baseQuery(): Builder
    {
        $query = PurchaseOrder::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('order_number', 'like', "%{$term}%")
                ->orWhere('reference', 'like', "%{$term}%")
                ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$term}%")->orWhere('company_name', 'like', "%{$term}%")));
        }
        if ($this->vendor !== '' && ctype_digit($this->vendor)) {
            $query->where('vendor_id', (int) $this->vendor);
        }

        return $this->applyPeriod($query, 'order_date', $this->period);
    }

    /**
     * Permission required for each bulk action (see ChecksPermissions).
     */
    protected function bulkActionPermissions(): array
    {
        return [
            'confirm' => 'edit purchase-orders',
            'cancel' => 'edit purchase-orders',
            'delete' => 'delete purchase-orders',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Tick at least one order first.';

            return;
        }

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'confirm':
                $n = PurchaseOrder::whereIn('id', $this->selectedItems)->where('status', 'draft')->update(['status' => 'confirmed']);
                $this->successMessage = "Confirmed {$n} order(s). Only drafts change.";
                break;

            case 'cancel':
                $n = PurchaseOrder::whereIn('id', $this->selectedItems)->whereIn('status', ['draft', 'confirmed'])->update(['status' => 'cancelled']);
                $this->successMessage = "Cancelled {$n} order(s). Only draft and confirmed orders change.";
                break;

            case 'delete':
                $deleted = 0;
                $skipped = 0;
                foreach (PurchaseOrder::whereIn('id', $this->selectedItems)->get() as $order) {
                    if ($order->bills()->exists()) {
                        $skipped++;

                        continue;
                    }
                    $this->deleteOrder($order);
                    $deleted++;
                }
                if ($deleted > 0) {
                    $this->successMessage = "Deleted {$deleted} order(s).".($skipped ? " Skipped {$skipped} with bills." : '');
                } else {
                    $this->errorMessage = 'None deleted: every ticked order has bills.';
                }
                break;

            default:
                $this->errorMessage = 'Invalid action selected.';

                return;
        }

        $this->selectedItems = [];
        $this->bulkAction = '';
    }

    /** Delete one order from its row menu (orders with bills are kept). */
    public function deleteOne(int $id): void
    {
        $this->requirePermission('delete purchase-orders');
        $order = PurchaseOrder::findOrFail($id);
        if ($order->bills()->exists()) {
            $this->errorMessage = "{$order->order_number} can't be deleted: it has bills.";

            return;
        }
        $this->deleteOrder($order);
        $this->successMessage = "Deleted {$order->order_number}.";
        $this->selectedItems = array_values(array_diff($this->selectedItems, [(string) $id]));
    }

    private function deleteOrder(PurchaseOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $order->items()->delete();
            $order->delete();
        });
    }

    public function render()
    {
        return view('livewire.purchase-orders.purchase-orders-table', [
            'orders' => $this->rows(),
            'tabs' => $this->statusTabs(self::LABELS, [], 'status', hideEmpty: true),
            'totals' => $this->filteredQuery()->toBase()->selectRaw('COUNT(*) as n, COALESCE(SUM(total), 0) as total')->first(),
            'vendors' => Vendor::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'periods' => self::periodOptions(),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
