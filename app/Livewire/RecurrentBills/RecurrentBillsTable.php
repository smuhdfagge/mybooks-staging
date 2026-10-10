<?php

namespace App\Livewire\RecurrentBills;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\RecurrentBill;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Recurrent bills list (tables plan T3): bills MyBooks makes on a schedule.
 */
class RecurrentBillsTable extends Component
{
    use ChecksPermissions, ListTable;

    public string $vendor = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'status'],
        'vendor' => ['except' => ''],
    ];

    public const LABELS = ['active' => 'Active', 'paused' => 'Paused', 'stopped' => 'Stopped'];

    /** What an active profile costs a month, whatever its frequency. */
    public const PER_MONTH = "CASE frequency WHEN 'weekly' THEN total * 52 / 12 WHEN 'monthly' THEN total WHEN 'quarterly' THEN total / 3 WHEN 'yearly' THEN total / 12 ELSE 0 END";

    protected function sortable(): array
    {
        return ['next_bill_date', 'profile_name', 'total'];
    }

    /** Soonest next bill first. */
    public function mountListTable(): void
    {
        if (! in_array($this->sortField, $this->sortable(), true)) {
            $this->sortField = 'next_bill_date';
            $this->sortDirection = 'asc';
        }
    }

    protected function rowRelations(): array
    {
        return ['vendor:id,name'];
    }

    protected function filterProperties(): array
    {
        return ['vendor'];
    }

    protected function baseQuery(): Builder
    {
        $query = RecurrentBill::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('profile_name', 'like', "%{$term}%")
                ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$term}%")));
        }
        if ($this->vendor !== '' && ctype_digit($this->vendor)) {
            $query->where('vendor_id', (int) $this->vendor);
        }

        return $query;
    }

    /** Pause an active profile, or start a paused one again. */
    public function toggleOne(int $id): void
    {
        $this->requirePermission('edit recurrent-bills');
        $profile = RecurrentBill::findOrFail($id);
        if ($profile->status === 'stopped') {
            $this->errorMessage = "{$profile->profile_name} has stopped. Edit it to set a new end date.";

            return;
        }
        $profile->update(['status' => $profile->status === 'active' ? 'paused' : 'active']);
        $this->successMessage = $profile->status === 'active' ? "{$profile->profile_name} started again." : "{$profile->profile_name} paused.";
    }

    public function deleteOne(int $id): void
    {
        $this->requirePermission('delete recurrent-bills');
        $profile = RecurrentBill::findOrFail($id);
        DB::transaction(function () use ($profile) {
            $profile->items()->delete();
            $profile->delete();
        });
        $this->successMessage = "Deleted {$profile->profile_name}. Bills it already made are kept.";
    }

    public function render()
    {
        return view('livewire.recurrent-bills.recurrent-bills-table', [
            'profiles' => $this->rows(),
            'tabs' => $this->statusTabs(self::LABELS, [], 'status', hideEmpty: true),
            'totals' => $this->filteredQuery()->toBase()->selectRaw('COUNT(*) as n, COALESCE(SUM(CASE WHEN status = \'active\' THEN '.self::PER_MONTH.' ELSE 0 END), 0) as per_month')->first(),
            'vendors' => Vendor::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
