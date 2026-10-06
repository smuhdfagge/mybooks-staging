<?php

namespace App\Livewire\FixedAssets;

use App\Actions\FixedAssets\WriteOffAssets;
use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\LimitsPageSize;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use Livewire\Component;
use Livewire\WithPagination;

class FixedAssetsTable extends Component
{
    use ChecksPermissions, LimitsPageSize, WithPagination;

    public $search = '';

    public $categoryFilter = '';

    public $statusFilter = '';

    public $sortField = 'asset_number';

    public $sortDirection = 'asc';

    public $perPage = 10;

    // Bulk operation properties
    public $selectedItems = [];

    public $selectAll = false;

    public $bulkAction = '';

    public $successMessage = '';

    public $errorMessage = '';

    // Bulk write-off (F2)
    public bool $showDisposeModal = false;

    public $disposalDate = '';

    public $disposalMethod = 'scrapped';

    public $disposalReason = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'categoryFilter' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'sortField' => ['except' => 'asset_number'],
        'sortDirection' => ['except' => 'asc'],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
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

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedItems = $this->getFilteredAssetIds();
        } else {
            $this->selectedItems = [];
        }
    }

    public function updatedSelectedItems()
    {
        $this->selectAll = count($this->selectedItems) === count($this->getFilteredAssetIds());
    }

    private function getFilteredAssetIds()
    {
        $tenantId = auth()->user()->tenant_id;

        return FixedAsset::where('tenant_id', $tenantId)
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('asset_number', 'like', '%'.$this->search.'%')
                        ->orWhere('name', 'like', '%'.$this->search.'%')
                        ->orWhere('serial_number', 'like', '%'.$this->search.'%')
                        ->orWhere('location', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->categoryFilter, fn ($q) => $q->where('category_id', $this->categoryFilter))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
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
            'activate' => 'edit fixed-assets',
            'dispose' => 'edit fixed-assets',
            'delete' => 'delete fixed-assets',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Please select at least one asset.';

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
                // A disposed or sold asset is out of the books; making it
                // active again here would leave its disposal journal standing (F2).
                $activated = FixedAsset::whereIn('id', $this->selectedItems)
                    ->whereNotIn('status', [FixedAsset::STATUS_DISPOSED, FixedAsset::STATUS_SOLD])
                    ->update(['status' => 'active']);
                $this->successMessage = "Successfully activated {$activated} asset(s).".($activated < $count ? ' Disposed or sold assets were left as they are.' : '');
                break;

            case 'dispose':
                // Ask for the date and reason first; disposeSelected() does it (F2).
                $this->disposalDate = $this->disposalDate ?: now()->toDateString();
                $this->showDisposeModal = true;

                return;

            case 'delete':
                $deletedCount = 0;
                $skippedCount = 0;

                foreach ($this->selectedItems as $assetId) {
                    $asset = FixedAsset::find($assetId);
                    if (! $asset) {
                        continue;
                    }

                    // Check if asset has depreciation records
                    if ($asset->depreciations()->exists()) {
                        $skippedCount++;

                        continue;
                    }

                    $asset->delete();
                    $deletedCount++;
                }

                if ($deletedCount > 0 && $skippedCount > 0) {
                    $this->successMessage = "Deleted {$deletedCount} asset(s). Skipped {$skippedCount} asset(s) with depreciation records.";
                } elseif ($deletedCount > 0) {
                    $this->successMessage = "Successfully deleted {$deletedCount} asset(s).";
                } else {
                    $this->errorMessage = 'Could not delete any assets. All selected assets have depreciation records.';
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

    /**
     * Write off the selected assets (F2): each through the normal disposal,
     * with no money received, so each posts its disposal journal.
     */
    public function disposeSelected(WriteOffAssets $writeOff): void
    {
        $this->bulkAction = 'dispose';
        $this->authorizeBulkAction();
        $this->validate([
            'disposalDate' => 'required|date|before_or_equal:today',
            'disposalMethod' => 'required|in:'.implode(',', WriteOffAssets::METHODS),
            'disposalReason' => 'nullable|string|max:500',
        ], [], ['disposalDate' => 'disposal date', 'disposalMethod' => 'how they were disposed of']);

        $result = $writeOff->handle($this->selectedItems, $this->disposalDate, $this->disposalMethod, $this->disposalReason);

        $this->successMessage = "Disposed of {$result['disposed']} asset(s), each with its disposal journal."
            .($result['skipped'] ? " Skipped {$result['skipped']} already disposed or sold." : '');
        $this->errorMessage = $result['failed']
            ? count($result['failed']).' could not be disposed: '.collect($result['failed'])->map(fn ($why, $name) => "{$name} ({$why})")->implode('; ').'.'
            : '';

        $this->showDisposeModal = false;
        $this->disposalReason = '';
        $this->selectedItems = [];
        $this->selectAll = false;
        $this->bulkAction = '';
    }

    public function closeDisposeModal(): void
    {
        $this->showDisposeModal = false;
    }

    public function render()
    {
        $tenantId = auth()->user()->tenant_id;

        $query = FixedAsset::where('tenant_id', $tenantId)
            ->with(['category', 'vendor']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('asset_number', 'like', '%'.$this->search.'%')
                    ->orWhere('name', 'like', '%'.$this->search.'%')
                    ->orWhere('serial_number', 'like', '%'.$this->search.'%')
                    ->orWhere('location', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->categoryFilter) {
            $query->where('category_id', $this->categoryFilter);
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $assets = $query->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->pageSize());

        $categories = FixedAssetCategory::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();

        $statuses = FixedAsset::getStatuses();

        // Summary stats
        $totalAssets = FixedAsset::where('tenant_id', $tenantId)->count();
        $activeAssets = FixedAsset::where('tenant_id', $tenantId)->where('status', 'active')->count();
        $totalValue = FixedAsset::where('tenant_id', $tenantId)->where('status', 'active')->sum('book_value');
        $totalCost = FixedAsset::where('tenant_id', $tenantId)->sum('purchase_cost');

        return view('livewire.fixed-assets.fixed-assets-table', [
            'assets' => $assets,
            'categories' => $categories,
            'statuses' => $statuses,
            'totalAssets' => $totalAssets,
            'activeAssets' => $activeAssets,
            'totalValue' => $totalValue,
            'totalCost' => $totalCost,
        ]);
    }
}
