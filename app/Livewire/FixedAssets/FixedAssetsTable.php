<?php

namespace App\Livewire\FixedAssets;

use App\Actions\FixedAssets\WriteOffAssets;
use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/** Fixed assets list (tables plan T4), with the bulk write-off (F2). */
class FixedAssetsTable extends Component
{
    use ChecksPermissions, ListTable;

    public string $category = '';

    // Bulk write-off (F2)
    public bool $showDisposeModal = false;

    public $disposalDate = '';

    public $disposalMethod = 'scrapped';

    public $disposalReason = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'status'],
        'category' => ['except' => ''],
    ];

    public const LABELS = [
        FixedAsset::STATUS_ACTIVE => 'In use',
        FixedAsset::STATUS_UNDER_MAINTENANCE => 'Being repaired',
        FixedAsset::STATUS_IDLE => 'Not in use',
        FixedAsset::STATUS_FULLY_DEPRECIATED => 'Fully written down',
        FixedAsset::STATUS_DISPOSED => 'Disposed of',
        FixedAsset::STATUS_SOLD => 'Sold',
    ];

    protected function sortable(): array
    {
        return ['asset_number', 'name', 'purchase_date', 'purchase_cost', 'book_value'];
    }

    public function mountListTable(): void
    {
        if (! in_array($this->sortField, $this->sortable(), true)) {
            $this->sortField = 'asset_number';
            $this->sortDirection = 'asc';
        }
    }

    protected function rowRelations(): array
    {
        return ['category:id,name'];
    }

    protected function filterProperties(): array
    {
        return ['category'];
    }

    protected function baseQuery(): Builder
    {
        $query = FixedAsset::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('asset_number', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('serial_number', 'like', "%{$term}%")
                ->orWhere('location', 'like', "%{$term}%"));
        }
        if ($this->category !== '' && ctype_digit($this->category)) {
            $query->where('category_id', (int) $this->category);
        }

        return $query;
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
            $this->errorMessage = 'Tick at least one asset first.';

            return;
        }

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'activate':
                // A disposed or sold asset is out of the books; making it
                // active again here would leave its disposal journal standing (F2).
                $n = FixedAsset::whereIn('id', $this->selectedItems)
                    ->whereNotIn('status', [FixedAsset::STATUS_DISPOSED, FixedAsset::STATUS_SOLD])
                    ->update(['status' => FixedAsset::STATUS_ACTIVE]);
                $this->successMessage = "Put {$n} asset(s) back in use. Disposed or sold assets stay as they are.";
                break;

            case 'dispose':
                // Ask for the date and reason first; disposeSelected() does it (F2).
                $this->disposalDate = $this->disposalDate ?: now()->toDateString();
                $this->showDisposeModal = true;

                return;

            case 'delete':
                $deleted = 0;
                $skipped = 0;
                foreach (FixedAsset::whereIn('id', $this->selectedItems)->get() as $asset) {
                    if ($asset->depreciations()->exists()) {
                        $skipped++;

                        continue;
                    }
                    $asset->delete();
                    $deleted++;
                }
                if ($deleted > 0) {
                    $this->successMessage = "Deleted {$deleted} asset(s).".($skipped ? " Skipped {$skipped} with depreciation posted." : '');
                } else {
                    $this->errorMessage = 'None deleted: every ticked asset has depreciation posted. Dispose of them instead.';
                }
                break;

            default:
                $this->errorMessage = 'Invalid action selected.';

                return;
        }

        $this->selectedItems = [];
        $this->bulkAction = '';
    }

    /** Delete one asset with no depreciation from its row menu. */
    public function deleteOne(int $id): void
    {
        $this->requirePermission('delete fixed-assets');
        $asset = FixedAsset::findOrFail($id);
        if ($asset->depreciations()->exists()) {
            $this->errorMessage = "{$asset->name} has depreciation posted, so it can't be deleted. Dispose of it instead.";

            return;
        }
        $asset->delete();
        $this->successMessage = "Deleted {$asset->name}.";
        $this->selectedItems = array_values(array_diff($this->selectedItems, [(string) $id]));
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
            .($result['skipped'] ? " Skipped {$result['skipped']} already disposed of or sold." : '');
        $this->errorMessage = $result['failed']
            ? count($result['failed']).' could not be disposed of: '.collect($result['failed'])->map(fn ($why, $name) => "{$name} ({$why})")->implode('; ').'.'
            : '';

        $this->showDisposeModal = false;
        $this->disposalReason = '';
        $this->selectedItems = [];
        $this->bulkAction = '';
    }

    public function closeDisposeModal(): void
    {
        $this->showDisposeModal = false;
    }

    public function render()
    {
        return view('livewire.fixed-assets.fixed-assets-table', [
            'assets' => $this->rows(),
            'tabs' => $this->statusTabs(self::LABELS, [], 'status', hideEmpty: true),
            'totals' => $this->filteredQuery()->toBase()->selectRaw('COUNT(*) as n, COALESCE(SUM(purchase_cost), 0) as cost, COALESCE(SUM(accumulated_depreciation), 0) as depreciation, COALESCE(SUM(book_value), 0) as book_value')->first(),
            'categories' => FixedAssetCategory::orderBy('name')->pluck('name', 'id'),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
