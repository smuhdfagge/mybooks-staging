<?php

namespace App\Livewire\Expenses;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Expenses list (tables plan T3: the shared list design, see InvoicesTable),
 * with the approval steps: submit, approve or reject, mark as paid.
 */
class ExpensesTable extends Component
{
    use ChecksPermissions, ListTable;

    public string $period = '';

    public string $vendor = '';

    public string $account = '';

    // Rejection dialog
    public $showRejectionModal = false;

    public $rejectionExpenseId = null;

    public $rejectionReason = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'status'],
        'period' => ['except' => ''],
        'vendor' => ['except' => ''],
        'account' => ['except' => ''],
    ];

    /** Status => tab label, in the order an expense moves through them. */
    public const LABELS = [
        Expense::STATUS_DRAFT => 'Draft',
        Expense::STATUS_PENDING_APPROVAL => 'Waiting for approval',
        Expense::STATUS_APPROVED => 'Approved',
        Expense::STATUS_REJECTED => 'Rejected',
        Expense::STATUS_PAID => 'Paid',
    ];

    protected function sortable(): array
    {
        return ['expense_date', 'expense_number', 'total'];
    }

    protected function rowRelations(): array
    {
        return ['vendor:id,name', 'expenseAccount:id,name'];
    }

    protected function filterProperties(): array
    {
        return ['period', 'vendor', 'account'];
    }

    protected function baseQuery(): Builder
    {
        $query = Expense::query();
        if (($term = trim($this->search)) !== '') {
            $number = preg_replace('/[^0-9.]/', '', $term);
            $query->where(function ($q) use ($term, $number) {
                $q->where('expense_number', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('reference', 'like', "%{$term}%")
                    ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$term}%"));
                if ($number !== '' && is_numeric($number)) {
                    $q->orWhere('total', (float) $number);
                }
            });
        }
        if ($this->vendor !== '' && ctype_digit($this->vendor)) {
            $query->where('vendor_id', (int) $this->vendor);
        }
        if ($this->account !== '' && ctype_digit($this->account)) {
            $query->where('expense_account_id', (int) $this->account);
        }

        return $this->applyPeriod($query, 'expense_date', $this->period);
    }

    private function isAdmin(): bool
    {
        return auth()->user()->hasRole('admin') || auth()->user()->isSuperAdmin();
    }

    /** Delete one draft or rejected expense from its row menu. */
    public function deleteOne(int $id): void
    {
        $this->requirePermission('delete expenses');
        $expense = Expense::findOrFail($id);
        if (! in_array($expense->status, [Expense::STATUS_DRAFT, Expense::STATUS_REJECTED], true)) {
            $this->errorMessage = 'Only draft or rejected expenses can be deleted.';

            return;
        }
        DB::transaction(fn () => $expense->delete());
        $this->successMessage = "Deleted {$expense->expense_number}.";
        $this->selectedItems = array_values(array_diff($this->selectedItems, [(string) $id]));
    }

    public function submitForApproval($expenseId)
    {
        $this->requirePermission('edit expenses');
        $this->successMessage = '';
        $this->errorMessage = '';

        $expense = Expense::find($expenseId);
        if (! $expense || ! $expense->canBeSubmitted()) {
            $this->errorMessage = 'This expense can\'t be sent for approval.';

            return;
        }
        $expense->submitForApproval();
        $this->successMessage = "{$expense->expense_number} sent for approval.";
    }

    /** Approve (admins only). */
    public function approveExpense($expenseId)
    {
        $this->requireAdmin();
        $this->successMessage = '';
        $this->errorMessage = '';

        $expense = Expense::find($expenseId);
        if (! $expense || ! $expense->canBeApproved()) {
            $this->errorMessage = 'This expense can\'t be approved.';

            return;
        }
        $expense->approve(auth()->id());
        $this->successMessage = "{$expense->expense_number} approved.";
    }

    /** Open the reject dialog (admins only). */
    public function openRejectModal($expenseId)
    {
        $this->requireAdmin();
        $this->rejectionExpenseId = $expenseId;
        $this->rejectionReason = '';
        $this->showRejectionModal = true;
    }

    /** Reject (admins only): the expense goes back to whoever made it. */
    public function rejectExpense()
    {
        $this->requireAdmin();
        $this->successMessage = '';
        $this->errorMessage = '';

        $expense = Expense::find($this->rejectionExpenseId);
        if (! $expense || ! $expense->canBeRejected()) {
            $this->errorMessage = 'This expense can\'t be rejected.';
            $this->closeRejectModal();

            return;
        }
        $expense->reject(auth()->id(), $this->rejectionReason);
        $this->successMessage = "{$expense->expense_number} rejected and sent back.";
        $this->closeRejectModal();
    }

    public function closeRejectModal()
    {
        $this->showRejectionModal = false;
        $this->rejectionExpenseId = null;
        $this->rejectionReason = '';
    }

    /** Mark as paid (admins only): posts the expense to the books. */
    public function markAsPaid($expenseId)
    {
        $this->requireAdmin();
        $this->successMessage = '';
        $this->errorMessage = '';

        $expense = Expense::find($expenseId);
        if (! $expense || ! $expense->canBeMarkedAsPaid()) {
            $this->errorMessage = 'This expense must be approved before it is marked as paid.';

            return;
        }
        DB::transaction(fn () => $expense->markAsPaid());
        $this->successMessage = "{$expense->expense_number} marked as paid.";
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
            $this->errorMessage = 'Tick at least one expense first.';

            return;
        }

        $this->authorizeBulkAction();

        $expenses = fn () => Expense::whereIn('id', $this->selectedItems)->get();
        $n = 0;

        switch ($this->bulkAction) {
            case 'delete':
                DB::transaction(function () use ($expenses, &$n) {
                    foreach ($expenses() as $expense) {
                        if (in_array($expense->status, [Expense::STATUS_DRAFT, Expense::STATUS_REJECTED], true)) {
                            $expense->delete();
                            $n++;
                        }
                    }
                });
                $this->successMessage = "Deleted {$n} expense(s). Only drafts and rejected expenses can be deleted.";
                break;

            case 'submit':
                DB::transaction(function () use ($expenses, &$n) {
                    foreach ($expenses() as $expense) {
                        if ($expense->canBeSubmitted()) {
                            $expense->submitForApproval();
                            $n++;
                        }
                    }
                });
                $this->successMessage = "Sent {$n} expense(s) for approval.";
                break;

            case 'approve':
                DB::transaction(function () use ($expenses, &$n) {
                    foreach ($expenses() as $expense) {
                        if ($expense->canBeApproved()) {
                            $expense->approve(auth()->id());
                            $n++;
                        }
                    }
                });
                $this->successMessage = "Approved {$n} expense(s).";
                break;

            case 'mark_paid':
                DB::transaction(function () use ($expenses, &$n) {
                    foreach ($expenses() as $expense) {
                        if ($expense->canBeMarkedAsPaid()) {
                            $expense->markAsPaid();
                            $n++;
                        }
                    }
                });
                $this->successMessage = "Marked {$n} expense(s) as paid.";
                break;

            default:
                $this->errorMessage = 'Invalid action selected.';

                return;
        }

        $this->selectedItems = [];
        $this->bulkAction = '';
    }

    public function render()
    {
        return view('livewire.expenses.expenses-table', [
            'expenses' => $this->rows(),
            'tabs' => $this->statusTabs(self::LABELS, [Expense::STATUS_PENDING_APPROVAL], 'status', hideEmpty: true),
            'totals' => $this->filteredQuery()->toBase()->selectRaw('COUNT(*) as n, COALESCE(SUM(total), 0) as total')->first(),
            'vendors' => Vendor::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'accounts' => ChartOfAccount::whereIn('id', Expense::query()->select('expense_account_id')->distinct())->orderBy('name')->pluck('name', 'id'),
            'periods' => self::periodOptions(),
            'filtered' => $this->isFiltered(),
            'isAdmin' => $this->isAdmin(),
        ]);
    }
}
