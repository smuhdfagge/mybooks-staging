<?php

namespace App\Livewire\Journals;

use App\Models\Journal;
use Livewire\Component;
use Livewire\WithPagination;

class JournalsTable extends Component
{
    use WithPagination;

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
                    $q->where('journal_number', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%')
                      ->orWhere('reference', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->pluck('id')
            ->map(fn($id) => (string) $id)
            ->toArray();
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

        switch ($this->bulkAction) {
            case 'post':
                Journal::whereIn('id', $this->selectedItems)
                    ->where('status', 'draft')
                    ->update(['status' => 'posted']);
                $this->successMessage = "Successfully posted selected journal(s).";
                break;

            case 'void':
                Journal::whereIn('id', $this->selectedItems)
                    ->where('status', 'posted')
                    ->update(['status' => 'voided']);
                $this->successMessage = "Successfully voided selected journal(s).";
                break;

            case 'delete':
                $deletedCount = 0;
                $skippedCount = 0;
                
                foreach ($this->selectedItems as $journalId) {
                    $journal = Journal::find($journalId);
                    if (!$journal) continue;
                    
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
                    $this->errorMessage = "Could not delete any journals. Only draft journals can be deleted.";
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
                    $q->where('journal_number', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%')
                      ->orWhere('reference', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.journals.journals-table', [
            'journals' => $journals,
        ]);
    }
}
