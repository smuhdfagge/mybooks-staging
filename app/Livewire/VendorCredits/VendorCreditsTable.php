<?php

namespace App\Livewire\VendorCredits;

use App\Actions\VendorCredits\DeleteVendorCredit;
use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\Vendor;
use App\Models\VendorCredit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Supplier credits list (tables plan T3): goods sent back and credit notes
 * vendors have given you. Mirrors the customer Credit notes list.
 */
class VendorCreditsTable extends Component
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

    public const LABELS = ['draft' => 'Draft', 'open' => 'Open', 'closed' => 'Used up', 'void' => 'Void'];

    protected function sortable(): array
    {
        return ['credit_date', 'vendor_credit_number', 'total', 'balance'];
    }

    protected function rowRelations(): array
    {
        return ['vendor:id,name', 'bill:id,bill_number'];
    }

    protected function filterProperties(): array
    {
        return ['period', 'vendor'];
    }

    protected function baseQuery(): Builder
    {
        $query = VendorCredit::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('vendor_credit_number', 'like', "%{$term}%")
                ->orWhere('vendor_reference', 'like', "%{$term}%")
                ->orWhereHas('bill', fn ($b) => $b->where('bill_number', 'like', "%{$term}%"))
                ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$term}%")));
        }
        if ($this->vendor !== '' && ctype_digit($this->vendor)) {
            $query->where('vendor_id', (int) $this->vendor);
        }

        return $this->applyPeriod($query, 'credit_date', $this->period);
    }

    /** Delete a draft or void credit from its row menu. */
    public function deleteOne(int $id, DeleteVendorCredit $delete): void
    {
        $this->requirePermission('delete bills');
        $credit = VendorCredit::findOrFail($id);
        try {
            $delete->handle($credit);
        } catch (ValidationException $e) {
            $this->errorMessage = $e->validator->errors()->first();

            return;
        }
        $this->successMessage = "Deleted {$credit->vendor_credit_number}.";
    }

    public function render()
    {
        return view('livewire.vendor-credits.vendor-credits-table', [
            'credits' => $this->rows(),
            'tabs' => $this->statusTabs(self::LABELS, [], 'status', hideEmpty: true),
            'totals' => $this->filteredQuery()->toBase()->selectRaw("COUNT(*) as n, COALESCE(SUM(total), 0) as total, COALESCE(SUM(CASE WHEN status = 'open' THEN balance ELSE 0 END), 0) as balance")->first(),
            'vendors' => Vendor::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'periods' => self::periodOptions(),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
