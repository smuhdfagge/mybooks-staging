<?php

namespace App\Livewire\Journals;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\Journal;
use App\Services\JournalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Journals list (tables plan T4): every entry in the books, manual ones and
 * those made by documents, with what they came from.
 */
class JournalsTable extends Component
{
    use ChecksPermissions, ListTable;

    public string $period = '';

    public string $source = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'status'],
        'period' => ['except' => ''],
        'source' => ['except' => ''],
    ];

    public const LABELS = ['draft' => 'Draft', 'pending' => 'Waiting', 'posted' => 'Posted', 'reversed' => 'Reversed'];

    /** Where an entry came from: the document class => what people call it. */
    public const SOURCES = [
        'manual' => 'Manual entries',
        'App\\Models\\Invoice' => 'Invoices',
        'App\\Models\\PaymentReceived' => 'Payments received',
        'App\\Models\\Bill' => 'Bills',
        'App\\Models\\PaymentMade' => 'Payments made',
        'App\\Models\\Expense' => 'Expenses',
    ];

    protected function sortable(): array
    {
        return ['journal_date', 'journal_number', 'total_debit'];
    }

    protected function filterProperties(): array
    {
        return ['period', 'source'];
    }

    protected function baseQuery(): Builder
    {
        $query = Journal::query();
        if (($term = trim($this->search)) !== '') {
            $number = preg_replace('/[^0-9.]/', '', $term);
            $query->where(function ($q) use ($term, $number) {
                $q->where('journal_number', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('reference', 'like', "%{$term}%");
                if ($number !== '' && is_numeric($number)) {
                    $q->orWhere('total_debit', (float) $number);
                }
            });
        }
        if ($this->source === 'manual') {
            $query->whereNull('reference_type');
        } elseif (array_key_exists($this->source, self::SOURCES)) {
            $query->where('reference_type', $this->source);
        }

        return $this->applyPeriod($query, 'journal_date', $this->period);
    }

    /**
     * Permission required for each bulk action (see ChecksPermissions).
     */
    protected function bulkActionPermissions(): array
    {
        return [
            'post' => 'post journals',
            'void' => 'edit journals',
            'delete' => 'delete journals',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Tick at least one journal first.';

            return;
        }

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'post':
                $posted = 0;
                $failed = [];
                foreach (Journal::with('entries.account')->whereIn('id', $this->selectedItems)->where('status', 'draft')->get() as $journal) {
                    try {
                        DB::transaction(fn () => $journal->post());
                        $posted++;
                    } catch (\Throwable $e) {
                        report($e);
                        $failed[] = $journal->journal_number;
                    }
                }
                $this->successMessage = "Posted {$posted} journal(s). Only drafts are posted.";
                if ($failed) {
                    $this->errorMessage = 'Not posted (unbalanced or in a closed period): '.implode(', ', $failed).'.';
                }
                break;

            case 'void':
                $voided = 0;
                $skipped = [];
                $journalService = app(JournalService::class);
                foreach (Journal::with('entries.account')->whereIn('id', $this->selectedItems)->where('status', 'posted')->get() as $journal) {
                    if ($journal->reference_type || $journal->manualReversalBlockedReason()) {
                        $skipped[] = $journal->journal_number;

                        continue;
                    }
                    $journalService->reverseJournal($journal, 'Voided');
                    $voided++;
                }
                $this->successMessage = "Reversed {$voided} journal(s) with reversing entries.";
                if ($skipped) {
                    $this->errorMessage = 'Skipped (made by a document, or already reversed): '.implode(', ', $skipped).'. Change the document instead.';
                }
                break;

            case 'delete':
                $deleted = 0;
                $skipped = 0;
                foreach (Journal::whereIn('id', $this->selectedItems)->get() as $journal) {
                    if ($journal->status !== 'draft') {
                        $skipped++;

                        continue;
                    }
                    $this->deleteDraft($journal);
                    $deleted++;
                }
                if ($deleted > 0) {
                    $this->successMessage = "Deleted {$deleted} draft journal(s).".($skipped ? " Skipped {$skipped} that are not drafts." : '');
                } else {
                    $this->errorMessage = 'None deleted: only draft journals can be deleted.';
                }
                break;

            default:
                $this->errorMessage = 'Invalid action selected.';

                return;
        }

        $this->selectedItems = [];
        $this->bulkAction = '';
    }

    /** Delete one draft journal from its row menu. */
    public function deleteOne(int $id): void
    {
        $this->requirePermission('delete journals');
        $journal = Journal::findOrFail($id);
        if ($journal->status !== 'draft') {
            $this->errorMessage = 'Only draft journals can be deleted. Reverse a posted one instead.';

            return;
        }
        $this->deleteDraft($journal);
        $this->successMessage = "Deleted {$journal->journal_number}.";
        $this->selectedItems = array_values(array_diff($this->selectedItems, [(string) $id]));
    }

    private function deleteDraft(Journal $journal): void
    {
        DB::transaction(function () use ($journal) {
            $journal->entries()->delete();
            $journal->delete();
        });
    }

    public function render()
    {
        return view('livewire.journals.journals-table', [
            'journals' => $this->rows(),
            'tabs' => $this->statusTabs(self::LABELS, [], 'status', hideEmpty: true),
            'totals' => $this->filteredQuery()->toBase()->selectRaw('COUNT(*) as n, COALESCE(SUM(total_debit), 0) as total')->first(),
            'sources' => self::SOURCES,
            'periods' => self::periodOptions(),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
