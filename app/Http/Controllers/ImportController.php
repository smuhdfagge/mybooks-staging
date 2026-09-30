<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessImport;
use App\Models\Import;
use App\Services\ImportService;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportController extends Controller
{
    protected ImportService $importService;

    public function __construct(ImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * Display the import dashboard
     */
    public function index()
    {
        $imports = Import::where('tenant_id', auth()->user()->tenant_id)
            ->orderByDesc('created_at')
            ->paginate(15);

        $importTypes = Import::getImportTypes();

        return view('imports.index', compact('imports', 'importTypes'));
    }

    /**
     * Show import creation form - Step 1: Select type and upload file
     */
    public function create(Request $request)
    {
        $type = $request->get('type');
        $importTypes = Import::getImportTypes();
        $formats = Import::getFormats();

        return view('imports.create', compact('type', 'importTypes', 'formats'));
    }

    /**
     * Store the uploaded file and show mapping - Step 2
     */
    public function upload(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:'.implode(',', array_keys(Import::getImportTypes())),
            'file' => [
                'required',
                'file',
                function ($attribute, $value, $fail) {
                    $extension = strtolower($value->getClientOriginalExtension());
                    if (in_array($extension, ['xlsx', 'xls']) && ! Import::excelSupported()) {
                        $fail('Excel import is not available yet. Please save the sheet as CSV and upload that instead.');
                    }
                },
                'mimes:'.implode(',', Import::acceptedExtensions()),
                'max:'.config('mybooks.import_max_file_size', 10240),
            ],
        ]);

        $file = $request->file('file');
        $format = $this->detectFormat($file);

        // Store the file
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = auth()->user()->tenant_id.'/'.$filename;

        Storage::disk('imports')->put($path, file_get_contents($file));

        // Create import record
        $import = Import::create([
            'tenant_id' => auth()->user()->tenant_id,
            'user_id' => auth()->id(),
            'type' => $validated['type'],
            'format' => $format,
            'status' => Import::STATUS_MAPPING,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
        ]);

        return redirect()->route('imports.mapping', $import);
    }

    /**
     * Show column mapping interface - Step 2
     */
    public function mapping(Import $import)
    {
        $this->authorizeImport($import);

        // Get preview data
        $preview = $this->importService->previewFile(
            $import->file_path,
            $import->format,
            5
        );

        $availableFields = Import::getAvailableFields($import->type);
        $requiredFields = Import::getRequiredFields($import->type);

        // Auto-detect mappings based on header names
        $suggestedMapping = $this->suggestMapping($preview['headers'], $availableFields);

        return view('imports.mapping', compact('import', 'preview', 'availableFields', 'requiredFields', 'suggestedMapping'));
    }

    /**
     * Process the import with mapping - Step 3
     */
    public function process(Request $request, Import $import)
    {
        $this->authorizeImport($import);

        $validated = $request->validate([
            'mapping' => 'required|array',
            'mapping.*' => 'nullable|string',
            'options' => 'nullable|array',
            'options.skip_duplicates' => 'nullable|boolean',
            'options.update_existing' => 'nullable|boolean',
        ]);

        // Save mapping and options
        $import->update([
            'column_mapping' => $validated['mapping'],
            'options' => $validated['options'] ?? ['skip_duplicates' => true],
            'status' => Import::STATUS_PROCESSING,
        ]);

        // Runs on the queue; the import page shows progress (P3).
        ProcessImport::dispatch($import);

        return redirect()->route('imports.show', $import)
            ->with('success', 'Your import has started. This page shows its progress and the results when it finishes.');
    }

    /**
     * Show import details and results
     */
    public function show(Import $import)
    {
        $this->authorizeImport($import);

        return view('imports.show', compact('import'));
    }

    /**
     * Download sample template
     */
    public function template(Request $request)
    {
        $type = $request->get('type');
        $format = $request->get('format', 'csv');

        if (! $type || ! isset(Import::getImportTypes()[$type])) {
            return back()->with('error', 'Invalid import type');
        }

        $sampleData = ImportService::getSampleData($type);

        if (empty($sampleData)) {
            return back()->with('error', 'No sample data available for this type');
        }

        $filename = $type.'_template.'.$format;

        if ($format === 'csv') {
            $headers = array_keys($sampleData[0]);

            $callback = function () use ($sampleData, $headers) {
                $file = fopen('php://output', 'w');
                Csv::writeRow($file, $headers);
                foreach ($sampleData as $row) {
                    Csv::writeRow($file, array_values($row));
                }
                fclose($file);
            };

            return response()->stream($callback, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

        if ($format === 'json') {
            return response()->json($sampleData)
                ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
        }

        return back()->with('error', 'Unsupported format');
    }

    /**
     * Delete an import record
     */
    public function destroy(Import $import)
    {
        $this->authorizeImport($import);

        // Delete the file
        if ($import->file_path) {
            Storage::disk('imports')->delete($import->file_path);
        }

        $import->delete();

        return redirect()->route('imports.index')->with('success', 'Import record deleted.');
    }

    /**
     * Retry a failed import
     */
    public function retry(Import $import)
    {
        $this->authorizeImport($import);

        if (! $import->canRetry()) {
            return back()->with('error', 'This import cannot be retried.');
        }

        $import->update([
            'status' => Import::STATUS_PROCESSING,
            'error_message' => null,
            'errors' => null,
            'warnings' => null,
            'processed_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
            'skipped_rows' => 0,
        ]);

        ProcessImport::dispatch($import);

        return redirect()->route('imports.show', $import)
            ->with('success', 'The import is running again. This page shows its progress.');
    }

    /**
     * Detect file format from uploaded file
     */
    protected function detectFormat($file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return match ($extension) {
            'csv', 'txt' => Import::FORMAT_CSV,
            'xlsx', 'xls' => Import::FORMAT_XLSX,
            'json' => Import::FORMAT_JSON,
            default => Import::FORMAT_CSV,
        };
    }

    /**
     * Suggest column mapping based on header names
     */
    protected function suggestMapping(array $headers, array $availableFields): array
    {
        $mapping = [];
        $fieldKeys = array_keys($availableFields);

        foreach ($headers as $header) {
            $normalized = Str::snake(strtolower(trim($header)));

            // Direct match
            if (in_array($normalized, $fieldKeys)) {
                $mapping[$header] = $normalized;

                continue;
            }

            // Partial match
            foreach ($fieldKeys as $field) {
                if (Str::contains($normalized, $field) || Str::contains($field, $normalized)) {
                    $mapping[$header] = $field;
                    break;
                }
            }

            // Common aliases
            $aliases = [
                'customer_name' => 'name',
                'vendor_name' => 'name',
                'item_name' => 'name',
                'product_name' => 'name',
                'full_name' => 'name',
                'mail' => 'email',
                'e_mail' => 'email',
                'email_address' => 'email',
                'tel' => 'phone',
                'telephone' => 'phone',
                'mobile' => 'phone',
                'phone_number' => 'phone',
                'addr' => 'address',
                'street' => 'address',
                'street_address' => 'address',
                'zip' => 'postal_code',
                'zip_code' => 'postal_code',
                'zipcode' => 'postal_code',
                'postcode' => 'postal_code',
                'price' => 'sale_price',
                'selling_price' => 'sale_price',
                'unit_price' => 'sale_price',
                'cost' => 'purchase_price',
                'buying_price' => 'purchase_price',
                'account_type' => 'type',
                'acct_type' => 'type',
                'account_code' => 'code',
                'acct_code' => 'code',
                'account_number' => 'code',
                'acct_name' => 'name',
                'account_name' => 'name',
                'desc' => 'description',
                'memo' => 'description',
                'notes' => 'description',
                'qty' => 'quantity',
                'job_title' => 'designation',
                'position' => 'designation',
                'title' => 'designation',
                'dept' => 'department',
                'base_salary' => 'salary',
                'basic_salary' => 'salary',
                'monthly_salary' => 'salary',
            ];

            if (isset($aliases[$normalized]) && in_array($aliases[$normalized], $fieldKeys)) {
                $mapping[$header] = $aliases[$normalized];
            }
        }

        return $mapping;
    }

    /**
     * Authorize access to import
     */
    protected function authorizeImport(Import $import): void
    {
        if ($import->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
    }
}
