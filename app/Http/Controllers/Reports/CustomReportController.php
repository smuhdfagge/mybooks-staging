<?php

namespace App\Http\Controllers\Reports;

use App\Models\CustomReport;
use Illuminate\Http\Request;

/**
 * The custom report builder.
 *
 * Split out of the old 3,400-line ReportController (finding L4). Route
 * names are unchanged.
 */
class CustomReportController extends ReportController
{
    /** Most rows a custom report shows (P7). */
    public const MAX_ROWS = 5000;

    /**
     * Custom Report Builder - List saved reports
     */
    public function customReportIndex()
    {
        $tenantId = auth()->user()->tenant_id;
        $userId = auth()->id();

        $myReports = CustomReport::where('tenant_id', $tenantId)
            ->where('created_by', $userId)
            ->orderBy('is_favorite', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get();

        $sharedReports = CustomReport::where('tenant_id', $tenantId)
            ->where('is_public', true)
            ->where('created_by', '!=', $userId)
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('reports.custom.index', compact('myReports', 'sharedReports'));
    }

    /**
     * Custom Report Builder - Create form
     */
    public function customReportCreate()
    {
        $dataSources = CustomReport::getDataSources();
        $aggregations = CustomReport::getAggregations();
        $operators = CustomReport::getFilterOperators();

        return view('reports.custom.create', compact('dataSources', 'aggregations', 'operators'));
    }

    /**
     * Custom Report Builder - Store new report
     */
    public function customReportStore(Request $request)
    {
        $request->validate(CustomReport::validationRules($request->input('data_source')));

        $customReport = CustomReport::create([
            'tenant_id' => auth()->user()->tenant_id,
            'created_by' => auth()->id(),
            'name' => $request->name,
            'description' => $request->description,
            'data_source' => $request->data_source,
            'columns' => $request->columns,
            'filters' => $request->filters ?? [],
            'group_by' => $request->group_by,
            'sort_by' => $request->sort_by,
            'aggregations' => $request->aggregations ?? [],
            'date_field' => $request->date_field,
            'is_public' => $request->boolean('is_public'),
        ]);

        return redirect()->route('reports.custom.run', $customReport)
            ->with('success', 'Custom report created successfully.');
    }

    /**
     * Custom Report Builder - Edit form
     */
    public function customReportEdit(CustomReport $customReport)
    {
        $this->authorizeReport($customReport);

        $dataSources = CustomReport::getDataSources();
        $aggregations = CustomReport::getAggregations();
        $operators = CustomReport::getFilterOperators();

        return view('reports.custom.edit', compact('customReport', 'dataSources', 'aggregations', 'operators'));
    }

    /**
     * Custom Report Builder - Update report
     */
    public function customReportUpdate(Request $request, CustomReport $customReport)
    {
        $this->authorizeReport($customReport);

        $request->validate(CustomReport::validationRules($request->input('data_source')));

        $customReport->update([
            'name' => $request->name,
            'description' => $request->description,
            'data_source' => $request->data_source,
            'columns' => $request->columns,
            'filters' => $request->filters ?? [],
            'group_by' => $request->group_by,
            'sort_by' => $request->sort_by,
            'aggregations' => $request->aggregations ?? [],
            'date_field' => $request->date_field,
            'is_public' => $request->boolean('is_public'),
        ]);

        return redirect()->route('reports.custom.run', $customReport)
            ->with('success', 'Custom report updated successfully.');
    }

    /**
     * Custom Report Builder - Delete report
     */
    public function customReportDestroy(CustomReport $customReport)
    {
        $this->authorizeReport($customReport);

        $customReport->delete();

        return redirect()->route('reports.custom.index')
            ->with('success', 'Custom report deleted successfully.');
    }

    /**
     * Custom Report Builder - Toggle favorite
     */
    public function customReportToggleFavorite(CustomReport $customReport)
    {
        $this->authorizeReport($customReport);

        $customReport->update(['is_favorite' => ! $customReport->is_favorite]);

        return back()->with('success', $customReport->is_favorite ? 'Report added to favorites.' : 'Report removed from favorites.');
    }

    /**
     * Custom Report Builder - Run report
     */
    public function customReportRun(Request $request, CustomReport $customReport)
    {
        $this->authorizeReport($customReport, true);

        $tenantId = auth()->user()->tenant_id;
        $dataSources = CustomReport::getDataSources();
        $sourceConfig = $dataSources[$customReport->data_source] ?? null;

        if (! $sourceConfig) {
            return back()->with('error', 'Invalid data source.');
        }

        // Get date range from request or use defaults
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        // Build the query
        $modelClass = $sourceConfig['model'];
        $query = $modelClass::where('tenant_id', $tenantId);

        // Apply date filter if date field is set
        if ($customReport->date_field && isset($sourceConfig['date_fields']) && in_array($customReport->date_field, $sourceConfig['date_fields'])) {
            $query->whereBetween($customReport->date_field, [$startDate, $endDate]);
        }

        // Load necessary relations
        $relations = $this->extractRelations($customReport->columns, $sourceConfig['columns']);
        if (! empty($relations)) {
            $query->with($relations);
        }

        // Apply filters
        if (! empty($customReport->filters)) {
            $query = $this->applyFilters($query, $customReport->filters, $sourceConfig['columns']);
        }

        // Apply sorting
        if (! empty($customReport->sort_by)) {
            foreach ($customReport->sort_by as $sort) {
                if (isset($sort['column']) && isset($sort['direction'])) {
                    // Only the source's own columns, in a known direction (M7);
                    // reports saved before this check may hold anything.
                    if (! isset($sourceConfig['columns'][$sort['column']]) || ! in_array(strtolower((string) $sort['direction']), ['asc', 'desc'], true)) {
                        continue;
                    }
                    // Handle relation sorting
                    if (strpos($sort['column'], '.') !== false) {
                        // For simplicity, skip relation sorting in raw query
                        continue;
                    }
                    $query->orderBy($sort['column'], $sort['direction']);
                }
            }
        }

        // Get data, up to a limit (P7): the report used to load every
        // matching row. One extra row tells us the list was cut short.
        $query->orderBy($query->getModel()->getQualifiedKeyName());
        $data = $query->limit(static::MAX_ROWS + 1)->get();
        $truncated = $data->count() > static::MAX_ROWS;
        if ($truncated) {
            $data = $data->take(static::MAX_ROWS)->values();
        }
        $maxRows = static::MAX_ROWS;

        // Apply grouping if needed
        $groupedData = null;
        $aggregatedData = null;
        if ($customReport->group_by) {
            $groupedData = $this->groupData($data, $customReport->group_by);

            // Calculate aggregations per group
            if (! empty($customReport->aggregations)) {
                $aggregatedData = $this->calculateAggregations($groupedData, $customReport->aggregations);
            }
        }

        // Calculate overall aggregations
        $totals = [];
        if (! empty($customReport->aggregations)) {
            foreach ($customReport->aggregations as $agg) {
                if (isset($agg['column']) && isset($agg['function'])) {
                    $column = $agg['column'];
                    $function = $agg['function'];
                    $value = $this->calculateSingleAggregation($data, $column, $function);
                    $totals[$column.'_'.$function] = $value;
                }
            }
        }

        // Update last run timestamp
        $customReport->update(['last_run_at' => now()]);

        return view('reports.custom.run', compact(
            'customReport', 'data', 'groupedData', 'aggregatedData',
            'totals', 'sourceConfig', 'startDate', 'endDate', 'truncated', 'maxRows'
        ));
    }

    /**
     * Get data source columns (AJAX)
     */
    public function customReportGetColumns(Request $request)
    {
        $dataSource = $request->get('data_source');
        $dataSources = CustomReport::getDataSources();

        if (! isset($dataSources[$dataSource])) {
            return response()->json(['error' => 'Invalid data source'], 400);
        }

        return response()->json([
            'columns' => $dataSources[$dataSource]['columns'],
            'date_fields' => $dataSources[$dataSource]['date_fields'],
            'group_fields' => $dataSources[$dataSource]['group_fields'],
        ]);
    }

    /**
     * Authorize access to custom report
     */
    protected function authorizeReport(CustomReport $customReport, bool $allowShared = false): void
    {
        $tenantId = auth()->user()->tenant_id;
        $userId = auth()->id();

        if ($customReport->tenant_id !== $tenantId) {
            abort(403);
        }

        if (! $allowShared && $customReport->created_by !== $userId) {
            abort(403);
        }

        if ($allowShared && $customReport->created_by !== $userId && ! $customReport->is_public) {
            abort(403);
        }
    }

    /**
     * Extract relations from column definitions
     */
    protected function extractRelations(array $selectedColumns, array $columnConfig): array
    {
        $relations = [];

        foreach ($selectedColumns as $column) {
            if (isset($columnConfig[$column]['relation'])) {
                $relation = $columnConfig[$column]['relation'];
                if (! in_array($relation, $relations)) {
                    $relations[] = $relation;
                }
            }
        }

        return $relations;
    }

    /**
     * Apply filters to query
     */
    protected function applyFilters($query, array $filters, array $columnConfig)
    {
        foreach ($filters as $filter) {
            if (! isset($filter['column']) || ! isset($filter['operator'])) {
                continue;
            }

            $column = $filter['column'];
            $operator = $filter['operator'];
            $value = $filter['value'] ?? null;

            // Only the data source's allowed columns (M7), and not relation
            // columns (not supported yet)
            if (! isset($columnConfig[$column]) || strpos($column, '.') !== false) {
                continue;
            }

            switch ($operator) {
                case 'equals':
                    $query->where($column, '=', $value);
                    break;
                case 'not_equals':
                    $query->where($column, '!=', $value);
                    break;
                case 'greater_than':
                    $query->where($column, '>', $value);
                    break;
                case 'less_than':
                    $query->where($column, '<', $value);
                    break;
                case 'greater_or_equal':
                    $query->where($column, '>=', $value);
                    break;
                case 'less_or_equal':
                    $query->where($column, '<=', $value);
                    break;
                case 'contains':
                    $query->where($column, 'LIKE', "%{$value}%");
                    break;
                case 'starts_with':
                    $query->where($column, 'LIKE', "{$value}%");
                    break;
                case 'ends_with':
                    $query->where($column, 'LIKE', "%{$value}");
                    break;
                case 'is_null':
                    $query->whereNull($column);
                    break;
                case 'is_not_null':
                    $query->whereNotNull($column);
                    break;
                case 'between':
                    if (is_array($value) && count($value) === 2) {
                        $query->whereBetween($column, $value);
                    }
                    break;
                case 'in':
                    if (is_array($value)) {
                        $query->whereIn($column, $value);
                    }
                    break;
            }
        }

        return $query;
    }

    /**
     * Group data by field
     */
    protected function groupData($data, string $groupBy)
    {
        return $data->groupBy(function ($item) use ($groupBy) {
            return data_get($item, $groupBy) ?? 'N/A';
        });
    }

    /**
     * Calculate aggregations for grouped data
     */
    protected function calculateAggregations($groupedData, array $aggregations): array
    {
        $result = [];

        foreach ($groupedData as $groupKey => $items) {
            $result[$groupKey] = [];
            foreach ($aggregations as $agg) {
                if (isset($agg['column']) && isset($agg['function'])) {
                    $key = $agg['column'].'_'.$agg['function'];
                    $result[$groupKey][$key] = $this->calculateSingleAggregation($items, $agg['column'], $agg['function']);
                }
            }
        }

        return $result;
    }

    /**
     * Calculate a single aggregation
     */
    protected function calculateSingleAggregation($data, string $column, string $function)
    {
        $values = $data->pluck($column)->filter(function ($val) {
            return is_numeric($val);
        });

        switch ($function) {
            case 'sum':
                return $values->sum();
            case 'count':
                return $data->count();
            case 'avg':
                return $values->avg();
            case 'min':
                return $values->min();
            case 'max':
                return $values->max();
            default:
                return 0;
        }
    }
}
