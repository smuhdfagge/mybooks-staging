<?php

namespace App\Livewire\ActivityLogs;

use App\Jobs\ProcessExport;
use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\ActivityLog;
use App\Models\Export;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Activity log (tables plan T5): who did what, newest first. Tabs group the
 * actions into changes to records, sign-ins and security.
 */
class ActivityLogsTable extends Component
{
    use ChecksPermissions, ListTable;

    public string $period = '';

    public string $user = '';

    public string $module = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'show'],
        'period' => ['except' => ''],
        'user' => ['except' => ''],
        'module' => ['except' => ''],
    ];

    public const LABELS = ['changes' => 'Changes', 'signins' => 'Sign-ins', 'security' => 'Security'];

    /** Tab => the actions it holds. Anything else (sent, paid, exported…) shows under All. */
    public const GROUPS = [
        'changes' => [ActivityLog::ACTION_CREATED, ActivityLog::ACTION_UPDATED, ActivityLog::ACTION_DELETED, ActivityLog::ACTION_RESTORED],
        'signins' => [ActivityLog::ACTION_LOGIN, ActivityLog::ACTION_LOGOUT, ActivityLog::ACTION_LOGIN_FAILED],
        'security' => [
            ActivityLog::ACTION_PASSWORD_RESET, ActivityLog::ACTION_PASSWORD_CHANGED, ActivityLog::ACTION_2FA_ENABLED, ActivityLog::ACTION_2FA_DISABLED,
            ActivityLog::ACTION_ROLE_CHANGED, ActivityLog::ACTION_PERMISSION_CHANGED, ActivityLog::ACTION_ACCOUNT_LOCKED, ActivityLog::ACTION_ACCOUNT_UNLOCKED,
            ActivityLog::ACTION_API_TOKEN_CREATED, ActivityLog::ACTION_API_TOKEN_REVOKED, ActivityLog::ACTION_SUSPICIOUS_ACTIVITY,
        ],
    ];

    /** Actions worth a second look; their count turns the tab red. */
    private const WARN = [ActivityLog::ACTION_LOGIN_FAILED, ActivityLog::ACTION_SUSPICIOUS_ACTIVITY, ActivityLog::ACTION_ACCOUNT_LOCKED];

    protected function sortable(): array
    {
        return ['created_at'];
    }

    protected function filterProperties(): array
    {
        return ['period', 'user', 'module'];
    }

    protected function baseQuery(): Builder
    {
        $query = ActivityLog::query()->where('tenant_id', auth()->user()->tenant_id);
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('description', 'like', "%{$term}%")
                ->orWhere('model_name', 'like', "%{$term}%")
                ->orWhere('user_name', 'like', "%{$term}%"));
        }
        if ($this->user !== '' && ctype_digit($this->user)) {
            $query->where('user_id', (int) $this->user);
        }
        if ($this->module !== '') {
            $query->whereIn('model_type', $this->modelTypes()->filter(fn ($t) => class_basename($t) === $this->module)->values()->all());
        }

        return $this->applyPeriod($query, 'created_at', $this->period);
    }

    protected function applyTab(Builder $query, string $tab): Builder
    {
        return array_key_exists($tab, self::GROUPS) ? $query->whereIn('action', self::GROUPS[$tab]) : $query;
    }

    /** @return array<string, array{label: string, count: int, alert: bool}> one grouped query */
    private function tabs(): array
    {
        $counts = $this->baseQuery()->toBase()->selectRaw('action, COUNT(*) as n')->groupBy('action')->pluck('n', 'action')->map(fn ($n) => (int) $n);

        $tabs = ['' => ['label' => 'All', 'count' => (int) $counts->sum(), 'alert' => false]];
        foreach (self::LABELS as $key => $label) {
            $actions = self::GROUPS[$key];
            $warn = (int) $counts->only(array_intersect($actions, self::WARN))->sum();
            $tabs[$key] = ['label' => $label, 'count' => (int) $counts->only($actions)->sum(), 'alert' => $warn > 0];
        }

        return $tabs;
    }

    /** @return Collection<int, string> full class names seen in this business's log */
    private function modelTypes(): Collection
    {
        return once(fn () => ActivityLog::where('tenant_id', auth()->user()->tenant_id)->whereNotNull('model_type')->distinct()->pluck('model_type'));
    }

    /** Row menu: show only what this person did. */
    public function onlyUser(int $id): void
    {
        $this->user = (string) $id;
        $this->resetPage();
    }

    public function export($format = 'csv')
    {
        $this->requirePermission('view settings');

        $from = $to = null;
        if ($this->period !== '' && array_key_exists($this->period, self::periodOptions())) {
            $p = app(DashboardService::class)->period((int) auth()->user()->tenant_id, $this->period, 'none');
            [$from, $to] = [$p->from->toDateString(), $p->to->toDateString()];
        }

        $export = Export::create([
            'tenant_id' => auth()->user()->tenant_id,
            'user_id' => auth()->id(),
            'type' => Export::TYPE_ACTIVITY_LOGS,
            'format' => in_array($format, ['csv', 'json'], true) ? $format : 'csv',
            'status' => Export::STATUS_PENDING,
            'options' => ['date_from' => $from, 'date_to' => $to],
        ]);

        // Built on the queue (P3).
        ProcessExport::dispatch($export);

        session()->flash('success', 'Your activity log export is being prepared. It will be ready to download on the Exports page in a moment.');

        return redirect()->route('exports.index');
    }

    public function render()
    {
        $tenantId = auth()->user()->tenant_id;

        return view('livewire.activity-logs.activity-logs-table', [
            'logs' => $this->rows(),
            'tabs' => $this->tabs(),
            'users' => User::where('tenant_id', $tenantId)->orderBy('name')->pluck('name', 'id'),
            'modules' => $this->modelTypes()->map(fn ($type) => class_basename($type))->unique()->sort()->values()
                ->mapWithKeys(fn ($m) => [$m => Str::headline($m)]),
            'periods' => self::periodOptions(),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
