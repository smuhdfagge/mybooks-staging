<?php

namespace App\Livewire\SupplierAdvances;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\PaymentMade;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * Supplier advances list (tables plan T3): money paid before the bill.
 * The same payments show on Payments made under "Advances".
 */
class SupplierAdvancesTable extends Component
{
    use ChecksPermissions, ListTable;

    public string $period = '';

    public string $vendor = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'show'],
        'period' => ['except' => ''],
        'vendor' => ['except' => ''],
    ];

    /** Old links used ?unused=1. */
    public function mount(): void
    {
        if (request()->boolean('unused')) {
            $this->tab = 'unused';
        }
    }

    protected function sortable(): array
    {
        return ['payment_date', 'payment_number', 'amount', 'unused_amount'];
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
        $query = PaymentMade::query()->where('is_advance', true);
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('payment_number', 'like', "%{$term}%")
                ->orWhere('reference', 'like', "%{$term}%")
                ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$term}%")));
        }
        if ($this->vendor !== '' && ctype_digit($this->vendor)) {
            $query->where('vendor_id', (int) $this->vendor);
        }

        return $this->applyPeriod($query, 'payment_date', $this->period);
    }

    protected function applyTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'unused' => $query->where('unused_amount', '>', 0),
            'used' => $query->where(fn ($q) => $q->where('unused_amount', '<=', 0)->orWhereNull('unused_amount')),
            default => $query,
        };
    }

    /** @return array<string, array{label: string, count: int, alert: bool}> */
    private function tabs(): array
    {
        $row = $this->baseQuery()->toBase()->selectRaw('COUNT(*) as all_rows, SUM(CASE WHEN unused_amount > 0 THEN 1 ELSE 0 END) as unused')->first();

        return [
            '' => ['label' => 'All', 'count' => (int) $row->all_rows, 'alert' => false],
            'unused' => ['label' => 'Not yet used', 'count' => (int) $row->unused, 'alert' => false],
            'used' => ['label' => 'Used up', 'count' => (int) $row->all_rows - (int) $row->unused, 'alert' => false],
        ];
    }

    public function render()
    {
        return view('livewire.supplier-advances.supplier-advances-table', [
            'advances' => $this->rows(),
            'tabs' => $this->tabs(),
            'totals' => $this->filteredQuery()->toBase()->selectRaw('COUNT(*) as n, COALESCE(SUM(amount), 0) as amount, COALESCE(SUM(unused_amount), 0) as unused')->first(),
            'vendors' => Vendor::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'periods' => self::periodOptions(),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
