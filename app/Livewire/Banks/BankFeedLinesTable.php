<?php

namespace App\Livewire\Banks;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\BankFeedConnection;
use App\Models\BankFeedLine;
use App\Services\BankFeeds\BankFeedException;
use App\Services\BankFeeds\LineActions;
use App\Services\BankFeeds\Matcher;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * Bank lines to review (tables plan T4): what the bank sent, matched to
 * records in MyBooks. The first tab is the lines still to review.
 */
class BankFeedLinesTable extends Component
{
    use ChecksPermissions, ListTable;

    public string $period = '';

    public string $connection = '';

    public string $direction = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'status'],
        'period' => ['except' => ''],
        'connection' => ['except' => ''],
        'direction' => ['except' => ''],
    ];

    /** Tab key => label; '' is "To review". */
    public const LABELS = ['' => 'To review', 'matched' => 'Matched', 'created' => 'Recorded', 'ignored' => 'Ignored', 'all' => 'All'];

    protected function sortable(): array
    {
        return ['date', 'amount'];
    }

    protected function rowRelations(): array
    {
        return ['connection', 'bank:id,name', 'matched'];
    }

    protected function filterProperties(): array
    {
        return ['period', 'connection', 'direction'];
    }

    protected function baseQuery(): Builder
    {
        $query = BankFeedLine::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(function ($w) use ($term) {
                $w->where('narration', 'like', "%{$term}%");
                if (is_numeric(str_replace(',', '', $term))) {
                    $w->orWhere('amount', str_replace(',', '', $term));
                }
            });
        }
        if ($this->connection !== '' && ctype_digit($this->connection)) {
            $query->where('connection_id', (int) $this->connection);
        }
        if (in_array($this->direction, ['credit', 'debit'], true)) {
            $query->where('direction', $this->direction);
        }

        return $this->applyPeriod($query, 'date', $this->period);
    }

    protected function applyTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            '' => $query->scopes(['toReview']),
            'matched', 'created', 'ignored' => $query->where('status', $tab),
            default => $query,
        };
    }

    /** @return array<string, array{label: string, count: int, alert: bool}> one grouped query */
    private function tabs(): array
    {
        $counts = $this->baseQuery()->toBase()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n);
        $review = $this->applyTab($this->baseQuery(), '')->count();

        $tabs = [];
        foreach (self::LABELS as $key => $label) {
            $n = match ($key) {
                '' => $review,
                'all' => (int) $counts->sum(),
                default => (int) ($counts[$key] ?? 0),
            };
            $tabs[$key] = ['label' => $label, 'count' => $n, 'alert' => false];
        }

        return $tabs;
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
        $this->successMessage = 'Ignored. You can bring it back from the Ignored tab.';
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

    public function acceptAllSuggested(LineActions $actions): void
    {
        $this->requirePermission('reconcile banks');
        $this->reset(['successMessage', 'errorMessage']);
        $lines = BankFeedLine::query()->scopes(['toReview'])->whereIn('id', $this->baseQuery()->select('id'))->orderBy('date')->limit(500)->get();
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

    public function render(Matcher $matcher)
    {
        $lines = $this->rows();

        return view('livewire.banks.bank-feed-lines-table', [
            'lines' => $lines,
            'tabs' => $this->tabs(),
            'suggestions' => $this->tab === '' ? $matcher->suggest(collect($lines->items())) : [],
            'totals' => $this->filteredQuery()->toBase()->selectRaw("COUNT(*) as n, COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE 0 END), 0) as money_in, COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount ELSE 0 END), 0) as money_out")->first(),
            'connections' => BankFeedConnection::whereNotNull('provider_account_id')->orderBy('id')->get()->mapWithKeys(fn ($c) => [$c->id => $c->title()]),
            'directions' => ['credit' => 'Money in', 'debit' => 'Money out'],
            'periods' => self::periodOptions(),
            'filtered' => $this->isFiltered(),
            'canReconcile' => (bool) auth()->user()?->can('reconcile banks'),
        ]);
    }
}
