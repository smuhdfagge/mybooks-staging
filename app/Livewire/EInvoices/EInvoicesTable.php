<?php

namespace App\Livewire\EInvoices;

use App\Actions\EInvoicing\SubmitInvoiceToNrs;
use App\Enums\EInvoiceStatus;
use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\LimitsPageSize;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Services\EInvoicing\EInvoiceException;
use App\Services\EInvoicing\PayloadBuilder;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The E-invoices list (session 18): invoices (or credit notes) with where
 * each stands with NRS. Filter by status and date; tick several that are not
 * sent yet and submit them in one go. Sending only records status; the
 * books are not touched.
 */
class EInvoicesTable extends Component
{
    use ChecksPermissions, LimitsPageSize, WithPagination;

    /** Used by the shared permission check; the one bulk action is checked in submitSelected(). */
    public $bulkAction = '';

    /** invoices or credit_notes */
    public $type = 'invoices';

    public $search = '';

    public $status = '';

    public $dateFrom = '';

    public $dateTo = '';

    public $perPage = 15;

    /** @var array<int, string> */
    public $selectedItems = [];

    public $successMessage = '';

    public $errorMessage = '';

    protected $queryString = [
        'type' => ['except' => 'invoices'],
        'status' => ['except' => ''],
        'search' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
    ];

    public function updating($name): void
    {
        if (in_array($name, ['type', 'search', 'status', 'dateFrom', 'dateTo', 'perPage'], true)) {
            $this->resetPage();
            $this->selectedItems = [];
        }
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

        $documents = $this->base()->whereIn($this->table().'.id', $ids)->get();
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

    private function table(): string
    {
        return $this->type === 'credit_notes' ? 'credit_notes' : 'invoices';
    }

    /** @return Builder<Invoice>|Builder<CreditNote> */
    private function base(): Builder
    {
        if ($this->type === 'credit_notes') {
            return CreditNote::query()->with(['customer', 'invoice', 'eInvoice'])->whereIn('status', ['open', 'closed']);
        }

        return Invoice::query()->with(['customer', 'eInvoice'])->whereNotIn('status', ['draft', 'cancelled'])->where('total', '>', 0);
    }

    /** @return Builder<Invoice>|Builder<CreditNote> */
    private function filtered(): Builder
    {
        $credit = $this->type === 'credit_notes';
        $numberColumn = $credit ? 'credit_note_number' : 'invoice_number';
        $dateColumn = $credit ? 'credit_note_date' : 'invoice_date';

        return $this->base()
            ->when($this->status === EInvoiceStatus::NotSubmitted->value, fn ($q) => $q->where(fn ($w) => $w->whereDoesntHave('eInvoice')->orWhereHas('eInvoice', fn ($e) => $e->where('status', EInvoiceStatus::NotSubmitted->value))))
            ->when($this->status !== '' && $this->status !== EInvoiceStatus::NotSubmitted->value && EInvoiceStatus::tryFrom((string) $this->status), fn ($q) => $q->whereHas('eInvoice', fn ($e) => $e->where('status', $this->status)))
            ->when($this->dateFrom !== '' && strtotime((string) $this->dateFrom), fn ($q) => $q->where($dateColumn, '>=', $this->dateFrom))
            ->when($this->dateTo !== '' && strtotime((string) $this->dateTo), fn ($q) => $q->where($dateColumn, '<=', $this->dateTo))
            ->when(trim((string) $this->search) !== '', function ($q) use ($numberColumn) {
                $term = trim((string) $this->search);
                $q->where(fn ($w) => $w->where($numberColumn, 'like', '%'.$term.'%')
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$term.'%'))
                    ->orWhereHas('eInvoice', fn ($e) => $e->where('irn', 'like', '%'.$term.'%')));
            });
    }

    public function render(PayloadBuilder $payloads)
    {
        $credit = $this->type === 'credit_notes';
        $rows = $this->filtered()
            ->orderByDesc($credit ? 'credit_note_date' : 'invoice_date')->orderByDesc('id')
            ->paginate($this->pageSize());

        $counts = [];
        $unfiltered = fn () => $this->base();
        $counts['accepted'] = $unfiltered()->whereHas('eInvoice', fn ($e) => $e->where('status', 'accepted'))->count();
        foreach (['pending', 'rejected', 'failed'] as $s) {
            $counts[$s] = $unfiltered()->whereHas('eInvoice', fn ($e) => $e->where('status', $s))->count();
        }
        $counts['not_submitted'] = $unfiltered()->where(fn ($w) => $w->whereDoesntHave('eInvoice')->orWhereHas('eInvoice', fn ($e) => $e->where('status', 'not_submitted')))->count();

        return view('livewire.e-invoices.e-invoices-table', [
            'rows' => $rows,
            'credit' => $credit,
            'counts' => $counts,
            'statuses' => EInvoiceStatus::cases(),
            'kinds' => $rows->getCollection()->mapWithKeys(fn ($d) => [$d->id => $payloads->kind($d)]),
            'canSubmit' => (bool) auth()->user()?->can('submit e-invoices'),
        ]);
    }
}
