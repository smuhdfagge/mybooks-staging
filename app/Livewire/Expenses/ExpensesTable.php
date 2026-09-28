<?php

namespace App\Livewire\Expenses;

use App\Livewire\Concerns\ChecksPermissions;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class ExpensesTable extends Component
{
    use ChecksPermissions, WithPagination;

    public $search = '';

    public $sortField = 'expense_date';

    public $sortDirection = 'desc';

    public $perPage = 10;

    public $statusFilter = '';

    // Bulk operation properties
    public $selectedItems = [];

    public $selectAll = false;

    public $bulkAction = '';

    public $successMessage = '';

    public $errorMessage = '';

    // Rejection modal properties
    public $showRejectionModal = false;

    public $rejectionExpenseId = null;

    public $rejectionReason = '';

    protected $queryString = ['search', 'sortField', 'sortDirection', 'statusFilter'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
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
            $this->selectedItems = $this->getFilteredExpenseIds();
        } else {
            $this->selectedItems = [];
        }
    }

    public function updatedSelectedItems()
    {
        $this->selectAll = count($this->selectedItems) === count($this->getFilteredExpenseIds());
    }

    private function getFilteredExpenseIds()
    {
        return Expense::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('expense_number', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%')
                        ->orWhere('reference', 'like', '%'.$this->search.'%')
                        ->orWhereHas('vendor', function ($vq) {
                            $vq->where('name', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
    }

    public $confirmingDeletion = false;

    public $expenseToDelete = null;

    public function confirmDelete($expenseId)
    {
        $this->requirePermission('delete expenses');

        $expense = Expense::find($expenseId);
        if ($expense && ! in_array($expense->status, [Expense::STATUS_DRAFT, Expense::STATUS_REJECTED])) {
            $this->errorMessage = 'Only draft or rejected expenses can be deleted.';

            return;
        }
        $this->expenseToDelete = $expenseId;
        $this->confirmingDeletion = true;
    }

    public function deleteExpense()
    {
        $this->requirePermission('delete expenses');

        $this->successMessage = '';
        $this->errorMessage = '';

        if (! $this->expenseToDelete) {
            return;
        }

        $expense = Expense::find($this->expenseToDelete);

        if ($expense) {
            if (! in_array($expense->status, [Expense::STATUS_DRAFT, Expense::STATUS_REJECTED])) {
                $this->errorMessage = 'Only draft or rejected expenses can be deleted.';
                $this->confirmingDeletion = false;
                $this->expenseToDelete = null;

                return;
            }

            DB::transaction(function () use ($expense) {
                $expense->delete();
            });
            $this->successMessage = 'Expense deleted successfully.';
        } else {
            $this->errorMessage = 'Expense not found.';
        }

        $this->confirmingDeletion = false;
        $this->expenseToDelete = null;
    }

    public function cancelDelete()
    {
        $this->confirmingDeletion = false;
        $this->expenseToDelete = null;
    }

    /**
     * Submit expense for approval
     */
    public function submitForApproval($expenseId)
    {
        $this->requirePermission('edit expenses');

        $this->successMessage = '';
        $this->errorMessage = '';

        $expense = Expense::find($expenseId);
        if (! $expense) {
            $this->errorMessage = 'Expense not found.';

            return;
        }

        if (! $expense->canBeSubmitted()) {
            $this->errorMessage = 'This expense cannot be submitted for approval.';

            return;
        }

        $expense->submitForApproval();
        $this->successMessage = 'Expense submitted for approval.';
    }

    /**
     * Approve expense (admin only)
     */
    public function approveExpense($expenseId)
    {
        $this->requireAdmin();

        $this->successMessage = '';
        $this->errorMessage = '';

        if (! auth()->user()->hasRole('admin') && ! auth()->user()->isSuperAdmin()) {
            $this->errorMessage = 'You do not have permission to approve expenses.';

            return;
        }

        $expense = Expense::find($expenseId);
        if (! $expense) {
            $this->errorMessage = 'Expense not found.';

            return;
        }

        if (! $expense->canBeApproved()) {
            $this->errorMessage = 'This expense cannot be approved.';

            return;
        }

        $expense->approve(auth()->id());
        $this->successMessage = 'Expense approved successfully.';
    }

    /**
     * Open rejection modal
     */
    public function openRejectModal($expenseId)
    {
        $this->requireAdmin();

        if (! auth()->user()->hasRole('admin') && ! auth()->user()->isSuperAdmin()) {
            $this->errorMessage = 'You do not have permission to reject expenses.';

            return;
        }

        $this->rejectionExpenseId = $expenseId;
        $this->rejectionReason = '';
        $this->showRejectionModal = true;
    }

    /**
     * Reject expense (admin only)
     */
    public function rejectExpense()
    {
        $this->requireAdmin();

        $this->successMessage = '';
        $this->errorMessage = '';

        if (! auth()->user()->hasRole('admin') && ! auth()->user()->isSuperAdmin()) {
            $this->errorMessage = 'You do not have permission to reject expenses.';

            return;
        }

        $expense = Expense::find($this->rejectionExpenseId);
        if (! $expense) {
            $this->errorMessage = 'Expense not found.';
            $this->closeRejectModal();

            return;
        }

        if (! $expense->canBeRejected()) {
            $this->errorMessage = 'This expense cannot be rejected.';
            $this->closeRejectModal();

            return;
        }

        $expense->reject(auth()->id(), $this->rejectionReason);
        $this->successMessage = 'Expense rejected and sent back for review.';
        $this->closeRejectModal();
    }

    /**
     * Close rejection modal
     */
    public function closeRejectModal()
    {
        $this->showRejectionModal = false;
        $this->rejectionExpenseId = null;
        $this->rejectionReason = '';
    }

    /**
     * Mark expense as paid (admin only)
     */
    public function markAsPaid($expenseId)
    {
        $this->requireAdmin();

        $this->successMessage = '';
        $this->errorMessage = '';

        if (! auth()->user()->hasRole('admin') && ! auth()->user()->isSuperAdmin()) {
            $this->errorMessage = 'You do not have permission to mark expenses as paid.';

            return;
        }

        $expense = Expense::find($expenseId);
        if (! $expense) {
            $this->errorMessage = 'Expense not found.';

            return;
        }

        if (! $expense->canBeMarkedAsPaid()) {
            $this->errorMessage = 'This expense must be approved before marking as paid.';

            return;
        }

        DB::transaction(function () use ($expense) {
            $expense->markAsPaid();
        });

        $this->successMessage = 'Expense marked as paid. Journal entries and account balances have been updated.';
    }

    /**
     * Permission required for each bulk action (see ChecksPermissions).
     */
    protected function bulkActionPermissions(): array
    {
        return [
            'delete' => 'delete expenses',
            'submit' => 'edit expenses',
            'approve' => self::ADMIN_ONLY,
            'mark_paid' => self::ADMIN_ONLY,
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Please select at least one expense.';

            return;
        }

        if (empty($this->bulkAction)) {
            $this->errorMessage = 'Please select an action.';

            return;
        }

        $count = count($this->selectedItems);
        $isAdmin = auth()->user()->hasRole('admin') || auth()->user()->isSuperAdmin();

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'delete':
                $deletedCount = 0;
                DB::transaction(function () use (&$deletedCount) {
                    foreach ($this->selectedItems as $expenseId) {
                        $expense = Expense::find($expenseId);
                        if ($expense && in_array($expense->status, [Expense::STATUS_DRAFT, Expense::STATUS_REJECTED])) {
                            $expense->delete();
                            $deletedCount++;
                        }
                    }
                });
                $this->successMessage = "Successfully deleted {$deletedCount} expense(s).";
                break;

            case 'submit':
                $submittedCount = 0;
                DB::transaction(function () use (&$submittedCount) {
                    foreach ($this->selectedItems as $expenseId) {
                        $expense = Expense::find($expenseId);
                        if ($expense && $expense->canBeSubmitted()) {
                            $expense->submitForApproval();
                            $submittedCount++;
                        }
                    }
                });
                $this->successMessage = "Successfully submitted {$submittedCount} expense(s) for approval.";
                break;

            case 'approve':
                if (! $isAdmin) {
                    $this->errorMessage = 'You do not have permission to approve expenses.';

                    return;
                }
                $approvedCount = 0;
                DB::transaction(function () use (&$approvedCount) {
                    foreach ($this->selectedItems as $expenseId) {
                        $expense = Expense::find($expenseId);
                        if ($expense && $expense->canBeApproved()) {
                            $expense->approve(auth()->id());
                            $approvedCount++;
                        }
                    }
                });
                $this->successMessage = "Successfully approved {$approvedCount} expense(s).";
                break;

            case 'mark_paid':
                if (! $isAdmin) {
                    $this->errorMessage = 'You do not have permission to mark expenses as paid.';

                    return;
                }
                $paidCount = 0;
                DB::transaction(function () use (&$paidCount) {
                    foreach ($this->selectedItems as $expenseId) {
                        $expense = Expense::find($expenseId);
                        if ($expense && $expense->canBeMarkedAsPaid()) {
                            $expense->markAsPaid();
                            $paidCount++;
                        }
                    }
                });
                $this->successMessage = "Successfully marked {$paidCount} expense(s) as paid.";
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
        $expenses = Expense::with(['vendor', 'expenseAccount', 'createdBy'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('expense_number', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%')
                        ->orWhere('reference', 'like', '%'.$this->search.'%')
                        ->orWhereHas('vendor', function ($vq) {
                            $vq->where('name', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        $isAdmin = auth()->user()->hasRole('admin') || auth()->user()->isSuperAdmin();

        // Build bulk actions based on user role
        $bulkActions = [
            'delete' => 'Delete (Draft/Rejected only)',
            'submit' => 'Submit for Approval',
        ];

        if ($isAdmin) {
            $bulkActions['approve'] = 'Approve';
            $bulkActions['mark_paid'] = 'Mark as Paid';
        }

        return view('livewire.expenses.expenses-table', [
            'expenses' => $expenses,
            'statuses' => Expense::getStatuses(),
            'bulkActions' => $bulkActions,
            'isAdmin' => $isAdmin,
        ]);
    }
}
