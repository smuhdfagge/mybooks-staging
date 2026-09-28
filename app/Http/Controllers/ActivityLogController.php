<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $query = ActivityLog::where('tenant_id', $tenantId)
            ->with('user')
            ->orderBy('created_at', 'desc');

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by action
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // Filter by model type
        if ($request->filled('model_type')) {
            $query->where('model_type', 'like', '%'.$request->model_type.'%');
        }

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('model_name', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate(25)->withQueryString();

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
            ->map(fn ($type) => class_basename($type))
            ->unique()
            ->sort()
            ->values();

        return view('activity-logs.index', compact('logs', 'users', 'actions', 'modelTypes'));
    }

    public function show(ActivityLog $activityLog)
    {
        // Ensure the log belongs to the user's tenant
        if ($activityLog->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        return view('activity-logs.show', compact('activityLog'));
    }

    /**
     * Get activity logs for a specific model
     */
    public function forModel(Request $request, string $modelType, int $modelId)
    {
        $tenantId = auth()->user()->tenant_id;

        // Build the full model class name
        $fullModelType = "App\\Models\\{$modelType}";

        $logs = ActivityLog::where('tenant_id', $tenantId)
            ->where('model_type', $fullModelType)
            ->where('model_id', $modelId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($logs);
    }
}
