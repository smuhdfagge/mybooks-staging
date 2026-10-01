<?php

namespace App\Livewire\Journals;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\LimitsPageSize;
use App\Models\Journal;
use App\Services\JournalService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class JournalsTable extends Component
{
    use ChecksPermissions, LimitsPageSize, WithPagination;

    public $search = '';

    public $sortField = 'journal_date';

    public $sortDirection = 'desc';

    public $perPage = 10;

    public $statusFilter = '';

    // Bulk operation properties
    public $selectedItems = [];

    public $selectAll = false;

    public $bulkAction = '';

    public $successMessage = '';

    public $errorMessage = '';

    protected $queryString = ['search', 'sortField', 'sortDirection', 'statusFilter'];

    public function updatingSearch()
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
            $this->selectedItems = $this->getFilteredJournalIds();
        } else {
            $this->selectedItems = [];
        }
    }

    public function updatedSelectedItems()
    {
        $this->selectAll = count($this->selectedItems) === count($this->getFilteredJournalIds());
    }

    private function getFilteredJournalIds()
    {
        return Journal::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('journal_number', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%')
                        ->orWhere('reference', 'like', '%'.$this->search.'%');
                });
            })
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
            $this->errorMessage = 'Please select at least one journal.';

            return;
        }

        if (empty($this->bulkAction)) {
            $this->errorMessage = 'Please select an action.';

            return;
        }

        $count = count($this->selectedItems);

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'post':
                // Post through Journal::post(), which checks the journal balances
                // and updates the account balances (C5). A status-only update
                // left balances untouched.
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
                $this->successMessage = "Posted {$posted} journal(s).";
                if ($failed) {
                    $this->errorMessage = 'Not posted (unbalanced or in a closed period): '.implode(', ', $failed).'.';
                }
                break;

            case 'void':
                // A posted journal is voided by a reversing journal, so the
                // ledger keeps a record and balances are restored (C5, M6).
                // Journals created by invoices, bills etc. belong to their
                // document and are changed through it instead.
                $voided = 0;
                $skipped = [];
                $journalService = app(JournalService::class);
                foreach (Journal::with('entries.account')->whereIn('id', $this->selectedItems)->where('status', 'posted')->get() as $journal) {
                    if ($journal->reference_type) {
                        $skipped[] = $journal->journal_number;

                        continue;
                    }
                    // Marks the original 'reversed' (the journals.status enum has no 'voided').
                    $journalService->reverseJournal($journal, 'Voided');
                    $voided++;
                }
                $this->successMessage = "Voided {$voided} journal(s) with reversing entries.";
                if ($skipped) {
                    $this->errorMessage = 'Skipped (belong to a document; change the document instead): '.implode(', ', $skipped).'.';
                }
                break;

            case 'delete':
                $deletedCount = 0;
                $skippedCount = 0;

                foreach ($this->selectedItems as $journalId) {
                    $journal = Journal::find($journalId);
                    if (! $journal) {
                        continue;
                    }

                    // Only allow deletion of draft journals
                    if ($journal->status !== 'draft') {
                        $skippedCount++;

                        continue;
                    }

                    $journal->entries()->delete();
                    $journal->delete();
                    $deletedCount++;
                }

                if ($deletedCount > 0 && $skippedCount > 0) {
                    $this->successMessage = "Deleted {$deletedCount} journal(s). Skipped {$skippedCount} non-draft journal(s).";
                } elseif ($deletedCount > 0) {
                    $this->successMessage = "Successfully deleted {$deletedCount} journal(s).";
                } else {
                    $this->errorMessage = 'Could not delete any journals. Only draft journals can be deleted.';
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

    public function render()
    {
        $journals = Journal::with(['entries.account', 'createdBy'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('journal_number', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%')
                        ->orWhere('reference', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->pageSize());

        return view('livewire.journals.journals-table', [
            'journals' => $journals,
        ]);
    }
}
