<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessExport;
use App\Models\Export;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ExportController extends Controller
{
    protected ExportService $exportService;

    public function __construct(ExportService $exportService)
    {
        $this->exportService = $exportService;
    }

    /**
     * Display the export dashboard
     */
    public function index()
    {
        // Only exports of records this person may see (audit MB-06).
        $exports = Export::where('tenant_id', auth()->user()->tenant_id)
            ->whereIn('type', array_keys(Export::getExportTypesFor(auth()->user())))
            ->orderByDesc('created_at')
            ->paginate(25);

        $exportTypes = Export::getExportTypes();
        $allowedTypes = Export::getExportTypesFor(auth()->user());
        $formats = Export::getFormats();
        $backupFormats = Export::getBackupFormats();

        return view('exports.index', compact('exports', 'exportTypes', 'allowedTypes', 'formats', 'backupFormats'));
    }

    /**
     * Show export creation form
     */
    public function create(Request $request)
    {
        $exportTypes = Export::getExportTypesFor(auth()->user());
        $type = $request->get('type', array_key_first($exportTypes));
        $formats = $type === Export::TYPE_FULL_BACKUP
            ? Export::getBackupFormats()
            : Export::getFormats();

        return view('exports.create', compact('type', 'exportTypes', 'formats'));
    }

    /**
     * Create and process an export
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:'.implode(',', array_keys(Export::getExportTypes())),
            'format' => 'required|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'included_data' => 'nullable|array',
            'included_data.*' => ['string', Rule::in(array_keys(Export::PERMISSIONS))],
        ]);
        $this->authorizeType($validated['type'], $validated['included_data'] ?? null);

        // Validate format based on type
        $allowedFormats = $validated['type'] === Export::TYPE_FULL_BACKUP
            ? array_keys(Export::getBackupFormats())
            : array_keys(Export::getFormats());

        if (! in_array($validated['format'], $allowedFormats)) {
            return back()->withErrors(['format' => 'Invalid format for this export type.']);
        }

        $options = [];
        if (! empty($validated['date_from'])) {
            $options['date_from'] = $validated['date_from'];
        }
        if (! empty($validated['date_to'])) {
            $options['date_to'] = $validated['date_to'];
        }

        $export = Export::create([
            'tenant_id' => auth()->user()->tenant_id,
            'user_id' => auth()->id(),
            'type' => $validated['type'],
            'format' => $validated['format'],
            'status' => Export::STATUS_PENDING,
            'options' => ! empty($options) ? $options : null,
            'included_data' => $validated['included_data'] ?? null,
        ]);

        // Built on the queue; it can be downloaded from the list when ready (P3).
        ProcessExport::dispatch($export);

        return redirect()->route('exports.index')
            ->with('success', 'Your export is being prepared. It will be ready to download below in a moment.');
    }

    /**
     * Quick export of a whole list as CSV or JSON
     */
    public function quickExport(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:'.implode(',', array_keys(Export::getExportTypes())),
            'format' => 'required|string|in:csv,json',
        ]);
        $this->authorizeType($validated['type']);

        $export = Export::create([
            'tenant_id' => auth()->user()->tenant_id,
            'user_id' => auth()->id(),
            'type' => $validated['type'],
            'format' => $validated['format'],
            'status' => Export::STATUS_PENDING,
        ]);

        ProcessExport::dispatch($export);

        return redirect()->route('exports.index')
            ->with('success', 'Your export is being prepared. It will be ready to download below in a moment.');
    }

    /**
     * Download an export
     */
    public function download(Export $export)
    {
        // This business's export, of records this person may see.
        abort_unless($export->isAllowedFor(auth()->user()), 403);

        if (! $export->isDownloadable()) {
            return back()->with('error', 'Export is not available for download.');
        }

        if ($export->isExpired()) {
            return back()->with('error', 'This export has expired and is no longer available.');
        }

        $headers = [];
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'csv' => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'json' => 'application/json',
            'zip' => 'application/zip',
        ];

        if (isset($mimeTypes[$export->format])) {
            $headers['Content-Type'] = $mimeTypes[$export->format];
        }

        return Storage::disk('exports')->download($export->file_path, $export->filename, $headers);
    }

    /**
     * Show export details
     */
    public function show(Export $export)
    {
        abort_unless($export->isAllowedFor(auth()->user()), 403);

        return view('exports.show', compact('export'));
    }

    /**
     * Delete an export
     */
    public function destroy(Export $export)
    {
        abort_unless($export->isAllowedFor(auth()->user()), 403);

        // Delete the file if it exists
        if ($export->file_path && Storage::disk('exports')->exists($export->file_path)) {
            Storage::disk('exports')->delete($export->file_path);
        }

        $export->delete();

        return redirect()->route('exports.index')
            ->with('success', 'Export deleted successfully.');
    }

    /**
     * Cleanup expired exports
     */
    public function cleanup()
    {
        $count = $this->exportService->cleanupExpiredExports();

        return redirect()->route('exports.index')
            ->with('success', "Cleaned up {$count} expired export(s).");
    }

    /**
     * Create a full backup
     */
    public function backup()
    {
        abort_unless(auth()->user()->can(Export::BACKUP_PERMISSION), 403);
        $backupFormats = Export::getBackupFormats();
        $dataTypes = array_filter([
            'customers' => 'Customers',
            'vendors' => 'Vendors',
            'items' => 'Items & Inventory',
            'invoices' => 'Invoices',
            'bills' => 'Bills',
            'expenses' => 'Expenses',
            'employees' => 'Employees',
            'payroll' => 'Payroll Records',
            'journals' => 'Journal Entries',
            'chart_of_accounts' => 'Chart of Accounts',
            'activity_logs' => 'Activity Logs',
        ], fn ($part) => Export::allowedFor(auth()->user(), $part), ARRAY_FILTER_USE_KEY);

        return view('exports.backup', compact('backupFormats', 'dataTypes'));
    }

    /**
     * Process full backup
     */
    public function processBackup(Request $request)
    {
        $validated = $request->validate([
            'format' => 'required|string|in:'.implode(',', array_keys(Export::getBackupFormats())),
            'included_data' => 'required|array|min:1',
            'included_data.*' => ['string', Rule::in(array_keys(Export::PERMISSIONS))],
        ]);
        $this->authorizeType(Export::TYPE_FULL_BACKUP, $validated['included_data']);

        $export = Export::create([
            'tenant_id' => auth()->user()->tenant_id,
            'user_id' => auth()->id(),
            'type' => Export::TYPE_FULL_BACKUP,
            'format' => $validated['format'],
            'status' => Export::STATUS_PENDING,
            'included_data' => $validated['included_data'],
        ]);

        ProcessExport::dispatch($export);

        return redirect()->route('exports.index')
            ->with('success', 'Your backup is being prepared. It will be ready to download below when it finishes.');
    }

    /**
     * Each kind of record needs its own view permission; "export reports"
     * alone only opens this screen (audit MB-06).
     *
     * @param  array<int, string>|null  $included
     */
    protected function authorizeType(string $type, ?array $included = null): void
    {
        abort_unless(Export::allowedFor(auth()->user(), $type, $included), 403, 'You do not have permission to export these records.');
    }
}
