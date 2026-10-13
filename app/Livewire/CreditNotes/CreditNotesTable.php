<?php

namespace App\Livewire\CreditNotes;

use App\Enums\CreditNoteStatus;
use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\CreditNote;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * Customer credit notes list (tables plan T2: the shared list design, see InvoicesTable).
 */
class CreditNotesTable extends Component
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
        return ['credit_note_date', 'credit_note_number', 'total', 'balance'];
    }

    protected function filterProperties(): array
    {
        return ['period', 'customer'];
    }

    protected function rowRelations(): array
    {
        return ['customer:id,name', 'invoice:id,invoice_number'];
    }

    protected function baseQuery(): Builder
    {
        $query = CreditNote::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('credit_note_number', 'like', "%{$term}%")
                ->orWhereHas('invoice', fn ($i) => $i->where('invoice_number', 'like', "%{$term}%"))
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%")));
        }
        if ($this->customer !== '' && ctype_digit($this->customer)) {
            $query->where('customer_id', (int) $this->customer);
        }

        return $this->applyPeriod($query, 'credit_note_date', $this->period);
    }

    public function render()
    {
        $labels = [];
        foreach (CreditNoteStatus::cases() as $s) {
            $labels[$s->value] = $s->value === 'closed' ? 'Used up' : ucfirst($s->value);
        }

        return view('livewire.credit-notes.credit-notes-table', [
            'notes' => $this->rows(),
            'tabs' => $this->statusTabs($labels, [], 'status', hideEmpty: false),
            'totals' => $this->filteredQuery()->toBase()->selectRaw('COUNT(*) as n, COALESCE(SUM(total), 0) as total, COALESCE(SUM(balance), 0) as balance')->first(),
            'customers' => Customer::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'periods' => self::periodOptions(),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
