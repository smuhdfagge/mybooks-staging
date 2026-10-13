<?php

namespace App\Livewire\BillsOfMaterials;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\BillOfMaterial;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/** Bills of materials list (tables plan T4): what goes into each thing you make. */
class BillsOfMaterialsTable extends Component
{
    use ChecksPermissions, ListTable;

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'show'],
    ];

    protected function sortable(): array
    {
        return ['name', 'assembly_orders_count'];
    }

    public function mountListTable(): void
    {
        if (! in_array($this->sortField, $this->sortable(), true)) {
            $this->sortField = 'name';
            $this->sortDirection = 'asc';
        }
    }

    protected function rowRelations(): array
    {
        return ['item:id,name,unit'];
    }

    protected function decorateRows(Builder $query): Builder
    {
        return $query->withCount(['components', 'assemblyOrders']);
    }

    protected function baseQuery(): Builder
    {
        $query = BillOfMaterial::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                ->orWhereHas('item', fn ($i) => $i->where('name', 'like', "%{$term}%")));
        }

        return $query;
    }

    protected function applyTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default => $query,
        };
    }

    /** @return array<string, array{label: string, count: int, alert: bool}> */
    private function tabs(): array
    {
        $row = $this->baseQuery()->toBase()->selectRaw('COUNT(*) as all_rows, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active')->first();

        return [
            '' => ['label' => 'All', 'count' => (int) $row->all_rows, 'alert' => false],
            'active' => ['label' => 'In use', 'count' => (int) $row->active, 'alert' => false],
            'inactive' => ['label' => 'Not in use', 'count' => (int) $row->all_rows - (int) $row->active, 'alert' => false],
        ];
    }

    public function render()
    {
        return view('livewire.bills-of-materials.bills-of-materials-table', [
            'boms' => $this->rows(),
            'tabs' => $this->tabs(),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
