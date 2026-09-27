<?php

namespace App\Livewire\ActivityLogs;

use App\Models\ActivityLog;
use App\Models\Export;
use App\Models\User;
use App\Services\ExportService;
use Livewire\Component;
use Livewire\WithPagination;

class ActivityLogsTable extends Component
{
    use WithPagination;

    public $search = '';
    public $userId = '';
    public $action = '';
    public $modelType = '';
    public $startDate = '';
    public $endDate = '';
    public $perPage = 25;

    protected $queryString = [
        'search' => ['except' => ''],
        'userId' => ['except' => ''],
        'action' => ['except' => ''],
        'modelType' => ['except' => ''],
        'startDate' => ['except' => ''],
        'endDate' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingUserId()
    {
        $this->resetPage();
    }

    public function updatingAction()
    {
        $this->resetPage();
    }

    public function updatingModelType()
    {
        $this->resetPage();
    }

    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['search', 'userId', 'action', 'modelType', 'startDate', 'endDate']);
        $this->resetPage();
    }

    public function export($format = 'csv')
    {
        $export = Export::create([
            'tenant_id' => auth()->user()->tenant_id,
            'user_id' => auth()->id(),
            'type' => Export::TYPE_ACTIVITY_LOGS,
            'format' => $format,
            'status' => Export::STATUS_PENDING,
            'options' => [
                'date_from' => $this->startDate ?: null,
                'date_to' => $this->endDate ?: null,
            ],
        ]);

        $exportService = new ExportService();
        $exportService->processExport($export);

        if ($export->status === Export::STATUS_COMPLETED) {
            session()->flash('success', 'Activity logs exported successfully. Check the Exports page to download.');
            return redirect()->route('exports.index');
        }

        session()->flash('error', 'Export failed: ' . ($export->error_message ?? 'Unknown error'));
    }

    public function render()
    {
        $tenantId = auth()->user()->tenant_id;

        $query = ActivityLog::where('tenant_id', $tenantId)
            ->with('user')
            ->orderBy('created_at', 'desc');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('description', 'like', "%{$this->search}%")
                    ->orWhere('model_name', 'like', "%{$this->search}%")
                    ->orWhere('user_name', 'like', "%{$this->search}%");
            });
        }

        if ($this->userId) {
            $query->where('user_id', $this->userId);
        }

        if ($this->action) {
            $query->where('action', $this->action);
        }

        if ($this->modelType) {
            $query->where('model_type', 'like', '%' . $this->modelType);
        }

        if ($this->startDate) {
            $query->whereDate('created_at', '>=', $this->startDate);
        }

        if ($this->endDate) {
            $query->whereDate('created_at', '<=', $this->endDate);
        }

        $logs = $query->paginate($this->perPage);

        // Get filter options
        $users = User::where('tenant_id', $tenantId)->orderBy('name')->get();
        
        $actions = ActivityLog::where('tenant_id', $tenantId)
            ->distinct()
            ->pluck('action')
            ->sort()
            ->values();
        
        $modelTypes = ActivityLog::where('tenant_id', $tenantId)
            ->whereNotNull('model_type')
            ->distinct()
            ->pluck('model_type')
            ->map(fn($type) => class_basename($type))
            ->unique()
            ->sort()
            ->values();

        return view('livewire.activity-logs.activity-logs-table', compact('logs', 'users', 'actions', 'modelTypes'));
    }
}
