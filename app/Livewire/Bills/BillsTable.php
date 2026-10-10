<?php

namespace App\Livewire\Bills;

use App\Actions\Bills\DeleteBill;
use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\Bill;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Bills list (tables plan T3): the Invoices list's design, for what you owe.
 */
class BillsTable extends Component
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

    /** Bills you still have to pay. */
    private const OPEN = ['unpaid', 'partial', 'overdue'];

    private const TABS = [
        '' => 'All',
        'draft' => 'Draft',
        'unpaid' => 'To pay',
        'overdue' => 'Overdue',
        'paid' => 'Paid',
        'cancelled' => 'Cancelled',
    ];

    protected function sortable(): array
    {
        return ['bill_date', 'bill_number', 'due_date', 'total', 'balance_due'];
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
        $query = Bill::query();
        if (($term = trim($this->search)) !== '') {
            $number = preg_replace('/[^0-9.]/', '', $term);
            $query->where(function ($q) use ($term, $number) {
                $q->where('bill_number', 'like', "%{$term}%")
                    ->orWhere('vendor_bill_number', 'like', "%{$term}%")
                    ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$term}%")->orWhere('company_name', 'like', "%{$term}%"));
                if ($number !== '' && is_numeric($number)) {
                    $q->orWhere('total', (float) $number);
                }
            });
        }
        if ($this->vendor !== '' && ctype_digit($this->vendor)) {
            $query->where('vendor_id', (int) $this->vendor);
        }

        return $this->applyPeriod($query, 'bill_date', $this->period);
    }

    protected function applyTab(Builder $query, string $tab): Builder
    {
        $today = now()->toDateString();

        return match ($tab) {
            'draft' => $query->where('status', 'draft'),
            'unpaid' => $query->whereIn('status', self::OPEN)->where('balance_due', '>', 0),
            'overdue' => $query->whereIn('status', self::OPEN)->where('balance_due', '>', 0)->where('due_date', '<', $today),
            'paid' => $query->where('status', 'paid'),
            'cancelled' => $query->where('status', 'cancelled'),
            default => $query,
        };
    }

    /** @return array<string, array{label: string, count: int, alert: bool}> one grouped query */
    private function tabs(): array
    {
        $open = "status IN ('unpaid','partial','overdue') AND balance_due > 0";
        $row = $this->baseQuery()->toBase()->selectRaw(
            "COUNT(*) as all_rows,
             SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
             SUM(CASE WHEN {$open} THEN 1 ELSE 0 END) as unpaid,
             SUM(CASE WHEN {$open} AND due_date < ? THEN 1 ELSE 0 END) as overdue,
             SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
             SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled",
            [now()->toDateString()]
        )->first();

        $tabs = [];
        foreach (self::TABS as $key => $label) {
            $n = (int) ($key === '' ? $row->all_rows : $row->{$key});
            if ($key === 'cancelled' && $n === 0 && $this->tab !== 'cancelled') {
                continue;
            }
            $tabs[$key] = ['label' => $label, 'count' => $n, 'alert' => $key === 'overdue'];
        }

        return $tabs;
    }

    /**
     * Permission required for each bulk action (see ChecksPermissions).
     */
    protected function bulkActionPermissions(): array
    {
        return [
            'mark_cancelled' => 'edit bills',
            'delete' => 'delete bills',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Tick at least one bill first.';

            return;
        }

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'mark_cancelled':
                // Save each bill so its journal is reversed (C5). Bills with
                // payments must have the payments removed first.
                $cancelled = 0;
                $skipped = 0;
                $failed = [];
                $bills = Bill::whereIn('id', $this->selectedItems)
                    ->whereIn('status', ['draft', 'unpaid', 'overdue'])
                    ->get();
                foreach ($bills as $bill) {
                    if ((float) $bill->amount_paid > 0) {
                        $skipped++;

                        continue;
                    }
                    try {
                        DB::transaction(fn () => $bill->update(['status' => 'cancelled']));
                        $cancelled++;
                    } catch (\Throwable $e) {
                        report($e);
                        $failed[] = $bill->bill_number;
                    }
                }
                $this->successMessage = "Cancelled {$cancelled} bill(s).".($skipped ? " Skipped {$skipped} with payments." : '');
                if ($failed) {
                    $this->errorMessage = 'Not cancelled (closed period or invalid totals): '.implode(', ', $failed).'.';
                }
                break;

            case 'delete':
                $deleted = 0;
                $skipped = 0;
                $delete = app(DeleteBill::class);
                DB::transaction(function () use (&$deleted, &$skipped, $delete) {
                    foreach (Bill::whereIn('id', $this->selectedItems)->get() as $bill) {
                        // Same rules as the web and API delete (R3).
                        if ($delete->blockedBecause($bill)) {
                            $skipped++;

                            continue;
                        }
                        $delete->handle($bill);
                        $deleted++;
                    }
                });
                if ($deleted > 0) {
                    $this->successMessage = "Deleted {$deleted} bill(s).".($skipped ? " Skipped {$skipped} with payments or stock already used." : '');
                } else {
                    $this->errorMessage = 'None deleted: every ticked bill has payments or stock already used.';
                }
                break;

            default:
                $this->errorMessage = 'Invalid action selected.';

                return;
        }

        $this->selectedItems = [];
        $this->bulkAction = '';
    }

    /**
     * Delete one bill from its row menu, by the same rules as deleting it
     * from its page (journal reversed, stock taken back out).
     */
    public function deleteOne(int $id, DeleteBill $deleteBill): void
    {
        $this->requirePermission('delete bills');
        $bill = Bill::findOrFail($id);
        if ($reason = $deleteBill->blockedBecause($bill)) {
            $this->errorMessage = $reason;

            return;
        }
        DB::transaction(fn () => $deleteBill->handle($bill));
        $this->successMessage = "Deleted {$bill->bill_number}.";
        $this->selectedItems = array_values(array_diff($this->selectedItems, [(string) $id]));
    }

    public function render()
    {
        return view('livewire.bills.bills-table', [
            'bills' => $this->rows(),
            'tabs' => $this->tabs(),
            'totals' => $this->filteredQuery()->toBase()->selectRaw("COUNT(*) as n, COALESCE(SUM(total), 0) as total, COALESCE(SUM(CASE WHEN status IN ('unpaid','partial','overdue') THEN balance_due ELSE 0 END), 0) as balance")->first(),
            'vendors' => Vendor::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'periods' => self::periodOptions(),
            'filtered' => $this->isFiltered(),
            'today' => today(),
        ]);
    }
}
