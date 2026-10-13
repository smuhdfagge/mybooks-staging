<?php

namespace App\Livewire\DeliveryNotes;

use App\Enums\DeliveryNoteStatus;
use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\Customer;
use App\Models\DeliveryNote;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * Delivery notes list (tables plan T2: the shared list design, see InvoicesTable).
 */
class DeliveryNotesTable extends Component
{
    use ChecksPermissions, ListTable;

    public string $period = '';

    public string $customer = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'status'],
        'period' => ['except' => ''],
        'customer' => ['except' => ''],
    ];

    protected function sortable(): array
    {
        return ['delivery_date', 'delivery_number'];
    }

    protected function filterProperties(): array
    {
        return ['period', 'customer'];
    }

    protected function rowRelations(): array
    {
        return ['customer:id,name', 'salesOrder:id,order_number'];
    }

    protected function baseQuery(): Builder
    {
        $query = DeliveryNote::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('delivery_number', 'like', "%{$term}%")
                ->orWhere('tracking_number', 'like', "%{$term}%")
                ->orWhereHas('salesOrder', fn ($o) => $o->where('order_number', 'like', "%{$term}%"))
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%")));
        }
        if ($this->customer !== '' && ctype_digit($this->customer)) {
            $query->where('customer_id', (int) $this->customer);
        }

        return $this->applyPeriod($query, 'delivery_date', $this->period);
    }

    public function render()
    {
        $labels = [];
        foreach (DeliveryNoteStatus::cases() as $s) {
            $labels[$s->value] = ucfirst(str_replace('_', ' ', $s->value));
        }

        return view('livewire.delivery-notes.delivery-notes-table', [
            'notes' => $this->rows(),
            'tabs' => $this->statusTabs($labels, [], 'status', hideEmpty: true),
            'customers' => Customer::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'periods' => self::periodOptions(),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
