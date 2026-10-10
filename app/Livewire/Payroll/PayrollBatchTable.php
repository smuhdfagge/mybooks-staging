<?php

namespace App\Livewire\Payroll;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\PayrollBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Component;

/** Payroll runs (tables plan T5): one row per month's run, with the money. */
class PayrollBatchTable extends Component
{
    use ChecksPermissions, ListTable;

    public string $year = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'status'],
        'year' => ['except' => ''],
    ];

    public const LABELS = [
        PayrollBatch::STATUS_DRAFT => 'Draft',
        PayrollBatch::STATUS_APPROVED => 'Approved',
        PayrollBatch::STATUS_PROCESSING => 'Paying',
        PayrollBatch::STATUS_FAILED => 'Failed',
        PayrollBatch::STATUS_PAID => 'Paid',
        PayrollBatch::STATUS_CANCELLED => 'Cancelled',
    ];

    protected function sortable(): array
    {
        return ['pay_period_start', 'batch_number', 'total_gross', 'total_net'];
    }

    protected function filterProperties(): array
    {
        return ['year'];
    }

    protected function baseQuery(): Builder
    {
        $query = PayrollBatch::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('batch_number', 'like', "%{$term}%")->orWhere('notes', 'like', "%{$term}%"));
        }
        if ($this->year !== '' && ctype_digit($this->year)) {
            $query->whereBetween('pay_period_start', ["{$this->year}-01-01", "{$this->year}-12-31"]);
        }

        return $query;
    }

    public function render()
    {
        $years = PayrollBatch::query()->orderByDesc('pay_period_start')->pluck('pay_period_start')
            ->map(fn ($d) => Carbon::parse($d)->format('Y'))->unique()->mapWithKeys(fn ($y) => [$y => $y]);

        return view('livewire.payroll.payroll-batch-table', [
            'batches' => $this->rows(),
            'tabs' => $this->statusTabs(self::LABELS, [PayrollBatch::STATUS_FAILED], 'status', hideEmpty: true),
            'totals' => $this->filteredQuery()->toBase()->selectRaw('COUNT(*) as n, COALESCE(SUM(total_gross), 0) as gross, COALESCE(SUM(total_deductions), 0) as deductions, COALESCE(SUM(total_net), 0) as net')->first(),
            'years' => $years,
            'filtered' => $this->isFiltered(),
        ]);
    }
}
