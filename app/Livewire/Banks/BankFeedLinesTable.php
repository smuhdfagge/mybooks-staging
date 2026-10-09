<?php

namespace App\Livewire\Banks;

use App\Enums\BankFeedLineStatus;
use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\LimitsPageSize;
use App\Models\BankFeedConnection;
use App\Models\BankFeedLine;
use App\Services\BankFeeds\BankFeedException;
use App\Services\BankFeeds\LineActions;
use App\Services\BankFeeds\Matcher;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The bank lines to review (session 17): filter by account, status, date
 * and words; accept or refuse a suggested match; ignore; accept every High
 * suggestion at once. Recording something new from a line is on the line's
 * own page. Nothing posts to the books from here.
 */
class BankFeedLinesTable extends Component
{
    use ChecksPermissions, LimitsPageSize, WithPagination;

    /** Used by the shared permission check; this table has no bulk menu. */
    public $bulkAction = '';

    public $search = '';

    public $connectionFilter = '';

    public $statusFilter = 'new';

    public $directionFilter = '';

    public $dateFrom = '';

    public $dateTo = '';

    public $perPage = 25;

    /** @var array<int, string> */
    public $selectedItems = [];

    public $successMessage = '';

    public $errorMessage = '';

    protected $queryString = ['search', 'connectionFilter', 'statusFilter', 'directionFilter', 'dateFrom', 'dateTo'];

    public function updating($name): void
    {
        if (in_array($name, ['search', 'connectionFilter', 'statusFilter', 'directionFilter', 'dateFrom', 'dateTo', 'perPage'], true)) {
            $this->resetPage();
            $this->selectedItems = [];
        }
    }

    public function accept(int $lineId, string $recordType, int $recordId, LineActions $actions): void
    {
        $this->requirePermission('reconcile banks');
        $this->reset(['successMessage', 'errorMessage']);
        try {
            $actions->accept(BankFeedLine::findOrFail($lineId), $recordType, $recordId, auth()->user());
            $this->successMessage = 'Matched.';
        } catch (BankFeedException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function reject(int $lineId, string $recordType, int $recordId, LineActions $actions): void
    {
        $this->requirePermission('reconcile banks');
        $this->reset(['successMessage', 'errorMessage']);
        $actions->reject(BankFeedLine::findOrFail($lineId), $recordType, $recordId, auth()->user());
    }

    public function ignore(int $lineId, LineActions $actions): void
    {
        $this->requirePermission('reconcile banks');
        $this->reset(['successMessage', 'errorMessage']);
        $actions->ignore(BankFeedLine::findOrFail($lineId), auth()->user());
        $this->successMessage = 'Ignored. You can bring it back from the Ignored list.';
    }

    public function unignore(int $lineId, LineActions $actions): void
    {
        $this->requirePermission('reconcile banks');
        $actions->unignore(BankFeedLine::findOrFail($lineId), auth()->user());
    }

    public function undo(int $lineId, LineActions $actions): void
    {
        $this->requirePermission('reconcile banks');
        $this->reset(['successMessage', 'errorMessage']);
        try {
            $actions->undo(BankFeedLine::findOrFail($lineId), auth()->user());
            $this->successMessage = 'The match was taken back.';
        } catch (BankFeedException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /** Every High suggestion among the lines shown by the filters (not just this page). */
    public function acceptAllSuggested(LineActions $actions): void
    {
        $this->requirePermission('reconcile banks');
        $this->reset(['successMessage', 'errorMessage']);
        $lines = $this->filtered()->toReview()->orderBy('date')->limit(500)->get();
        $count = $actions->acceptSuggested($lines, auth()->user());
        $this->successMessage = $count === 0
            ? 'No suggestion was sure enough to accept in one go.'
            : "{$count} ".($count === 1 ? 'line' : 'lines').' matched.';
        $this->selectedItems = [];
    }

    public function ignoreSelected(LineActions $actions): void
    {
        $this->requirePermission('reconcile banks');
        $this->reset(['successMessage', 'errorMessage']);
        if (! $this->selectedItems) {
            $this->errorMessage = 'Tick the lines to ignore first.';

            return;
        }
        $count = 0;
        foreach (BankFeedLine::whereIn('id', $this->selectedItems)->get() as $line) {
            $actions->ignore($line, auth()->user());
            $count++;
        }
        $this->selectedItems = [];
        $this->successMessage = "{$count} ".($count === 1 ? 'line' : 'lines').' ignored.';
    }

    /** @return Builder<BankFeedLine> */
    private function filtered(): Builder
    {
        return BankFeedLine::query()
            ->when($this->connectionFilter !== '', fn ($q) => $q->where('connection_id', (int) $this->connectionFilter))
            ->when($this->statusFilter !== '' && BankFeedLineStatus::tryFrom((string) $this->statusFilter), fn ($q) => $q->where('status', $this->statusFilter))
            ->when(in_array($this->directionFilter, ['credit', 'debit'], true), fn ($q) => $q->where('direction', $this->directionFilter))
            ->when($this->dateFrom !== '' && strtotime((string) $this->dateFrom), fn ($q) => $q->where('date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '' && strtotime((string) $this->dateTo), fn ($q) => $q->where('date', '<=', $this->dateTo))
            ->when(trim((string) $this->search) !== '', function ($q) {
                $term = trim((string) $this->search);
                $q->where(function ($w) use ($term) {
                    $w->where('narration', 'like', '%'.$term.'%');
                    if (is_numeric(str_replace(',', '', $term))) {
                        $w->orWhere('amount', str_replace(',', '', $term));
                    }
                });
            });
    }

    public function render(Matcher $matcher)
    {
        $lines = $this->filtered()->with(['connection', 'bank', 'matched'])
            ->orderByDesc('date')->orderByDesc('id')->paginate($this->pageSize());

        return view('livewire.banks.bank-feed-lines-table', [
            'lines' => $lines,
            'suggestions' => $matcher->suggest($lines->getCollection()),
            'connections' => BankFeedConnection::whereNotNull('provider_account_id')->orderBy('id')->get(),
            'counts' => [
                'new' => BankFeedLine::toReview()->count(),
                'matched' => BankFeedLine::where('status', 'matched')->count(),
                'created' => BankFeedLine::where('status', 'created')->count(),
                'ignored' => BankFeedLine::where('status', 'ignored')->count(),
            ],
            'canReconcile' => (bool) auth()->user()?->can('reconcile banks'),
        ]);
    }
}
