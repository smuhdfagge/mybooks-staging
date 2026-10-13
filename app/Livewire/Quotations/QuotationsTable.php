<?php

namespace App\Livewire\Quotations;

use App\Enums\QuotationStatus;
use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\Customer;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * Quotations list (tables plan T2: the shared list design, see InvoicesTable).
 */
class QuotationsTable extends Component
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
        return ['quotation_date', 'quotation_number', 'expiry_date', 'total'];
    }

    protected function filterProperties(): array
    {
        return ['period', 'customer'];
    }

    protected function rowRelations(): array
    {
        return ['customer:id,name'];
    }

    protected function baseQuery(): Builder
    {
        $query = Quotation::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('quotation_number', 'like', "%{$term}%")
                ->orWhere('reference', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%")->orWhere('company_name', 'like', "%{$term}%")));
        }
        if ($this->customer !== '' && ctype_digit($this->customer)) {
            $query->where('customer_id', (int) $this->customer);
        }

        return $this->applyPeriod($query, 'quotation_date', $this->period);
    }

    public function render()
    {
        return view('livewire.quotations.quotations-table', [
            'quotations' => $this->rows(),
            'tabs' => $this->statusTabs(collect(QuotationStatus::cases())->mapWithKeys(fn ($s) => [$s->value => ucfirst($s->value)])->all()),
            'totals' => $this->filteredQuery()->toBase()->selectRaw('COUNT(*) as n, COALESCE(SUM(total), 0) as total')->first(),
            'customers' => Customer::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'periods' => self::periodOptions(),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
