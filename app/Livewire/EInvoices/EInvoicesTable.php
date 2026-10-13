<?php

namespace App\Livewire\EInvoices;

use App\Actions\EInvoicing\SubmitInvoiceToNrs;
use App\Enums\EInvoiceStatus;
use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Services\EInvoicing\EInvoiceException;
use App\Services\EInvoicing\PayloadBuilder;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * E-invoices list (session 18, tables plan T4): invoices or credit notes with
 * where each stands with NRS. Tick several not yet accepted and send them in
 * one go. Sending only records status; the books are not touched.
 */
class EInvoicesTable extends Component
{
    use ChecksPermissions, ListTable;

    /** invoices or credit_notes */
    public string $type = 'invoices';

    public string $period = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'status'],
        'type' => ['except' => 'invoices'],
        'period' => ['except' => ''],
    ];

    /** Same words as the NRS status on an invoice's page (EInvoiceStatus::label()). */
    public const LABELS = ['not_submitted' => 'Not submitted', 'pending' => 'Pending', 'accepted' => 'Accepted', 'rejected' => 'Rejected', 'failed' => 'Failed'];

    protected function sortable(): array
    {
        return ['date', 'number', 'total'];
    }

    protected function filterProperties(): array
    {
        return ['type', 'period'];
    }

    private function credit(): bool
    {
        return $this->type === 'credit_notes';
    }

    /** The real column behind a sort key. */
    protected function sortColumn(): string
    {
        $key = in_array($this->sortField, $this->sortable(), true) ? $this->sortField : 'date';

        return match ($key) {
            'number' => $this->credit() ? 'credit_note_number' : 'invoice_number',
            'total' => 'total',
            default => $this->credit() ? 'credit_note_date' : 'invoice_date',
        };
    }

    protected function rowRelations(): array
    {
        return ['customer:id,name', 'eInvoice'];
    }

    /** @return Builder<Invoice>|Builder<CreditNote> documents that can go to NRS */
    private function documents(): Builder
    {
        return $this->credit()
            ? CreditNote::query()->whereIn('status', ['open', 'closed'])
            : Invoice::query()->whereNotIn('status', ['draft', 'cancelled'])->where('total', '>', 0);
    }

    protected function baseQuery(): Builder
    {
        $query = $this->documents();
        if (($term = trim($this->search)) !== '') {
            $number = $this->credit() ? 'credit_note_number' : 'invoice_number';
            $query->where(fn ($w) => $w->where($number, 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%"))
                ->orWhereHas('eInvoice', fn ($e) => $e->where('irn', 'like', "%{$term}%")));
        }

        return $this->applyPeriod($query, $this->credit() ? 'credit_note_date' : 'invoice_date', $this->period);
    }

    protected function applyTab(Builder $query, string $tab): Builder
    {
        if ($tab === EInvoiceStatus::NotSubmitted->value) {
            return $query->where(fn ($w) => $w->whereDoesntHave('eInvoice')->orWhereHas('eInvoice', fn ($e) => $e->where('status', $tab)));
        }

        return array_key_exists($tab, self::LABELS) ? $query->whereHas('eInvoice', fn ($e) => $e->where('status', $tab)) : $query;
    }

    /** @return array<string, array{label: string, count: int, alert: bool}> */
    private function tabs(): array
    {
        $tabs = ['' => ['label' => 'All', 'count' => $this->baseQuery()->count(), 'alert' => false]];
        foreach (self::LABELS as $key => $label) {
            $tabs[$key] = ['label' => $label, 'count' => $this->applyTab($this->baseQuery(), $key)->count(), 'alert' => in_array($key, ['rejected', 'failed'], true)];
        }

        return $tabs;
    }

    public function submitSelected(SubmitInvoiceToNrs $submit): void
    {
        abort_unless(config('mybooks.features.e_invoicing'), 404);
        $this->requirePermission('submit e-invoices');
        $this->reset(['successMessage', 'errorMessage']);

        $ids = array_map('intval', $this->selectedItems);
        if (! $ids) {
            $this->errorMessage = 'Tick the documents to send first.';

            return;
        }

        $documents = $this->documents()->with(['customer', 'eInvoice'])->whereIn($this->documents()->getModel()->getQualifiedKeyName(), $ids)->get();
        $accepted = $skipped = $problems = 0;
        $firstError = null;
        foreach ($documents as $document) {
            if ($document->eInvoice?->isAccepted()) {
                $skipped++;

                continue;
            }
            try {
                $row = $submit->handle($document, auth()->id());
                $row->status === EInvoiceStatus::Accepted->value ? $accepted++ : $problems++;
                $firstError ??= $row->status === EInvoiceStatus::Accepted->value ? null : $row->last_error;
            } catch (EInvoiceException $e) {
                $problems++;
                $firstError ??= $e->getMessage();
            }
        }

        $this->selectedItems = [];
        $this->successMessage = "{$accepted} accepted by NRS".($skipped ? ", {$skipped} were already accepted" : '').'.';
        if ($problems) {
            $this->errorMessage = "{$problems} did not go through. {$firstError}";
        }
    }

    public function render(PayloadBuilder $payloads)
    {
        $rows = $this->rows();

        return view('livewire.e-invoices.e-invoices-table', [
            'rows' => $rows,
            'credit' => $this->credit(),
            'tabs' => $this->tabs(),
            'types' => ['invoices' => 'Invoices', 'credit_notes' => 'Credit notes'],
            'kinds' => collect($rows->items())->mapWithKeys(fn ($d) => [$d->id => $payloads->kind($d)]),
            'periods' => self::periodOptions(),
            'filtered' => trim($this->search) !== '' || $this->period !== '',
            'canSubmit' => (bool) auth()->user()?->can('submit e-invoices'),
        ]);
    }
}
