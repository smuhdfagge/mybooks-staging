<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Import;
use App\Models\Inventory;
use App\Models\InventoryHistory;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Vendor;
use App\Support\Csv;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportService
{
    protected int $tenantId;

    protected array $options;

    protected array $errors = [];

    protected array $warnings = [];

    protected string $disk = 'imports';

    public function __construct()
    {
        $this->options = [];
    }

    /**
     * Get the storage disk for imports
     */
    protected function storage()
    {
        return Storage::disk($this->disk);
    }

    /**
     * Process an import
     */
    public function processImport(Import $import): bool
    {
        try {
            $import->update([
                'status' => Import::STATUS_PROCESSING,
                'started_at' => now(),
            ]);

            $this->tenantId = $import->tenant_id;
            $this->options = $import->options ?? [];
            $this->errors = [];
            $this->warnings = [];

            // Read the file (streamed row by row for CSV, P3)
            $data = $this->readFile($import);

            if ($data->isEmpty()) {
                throw new \Exception('No data found in the file');
            }

            $totalRows = $data->count();
            $import->update(['total_rows' => $totalRows]);

            // Process based on type
            $result = match ($import->type) {
                Import::TYPE_CUSTOMERS => $this->importCustomers($import, $data),
                Import::TYPE_VENDORS => $this->importVendors($import, $data),
                Import::TYPE_ITEMS => $this->importItems($import, $data),
                Import::TYPE_CHART_OF_ACCOUNTS => $this->importChartOfAccounts($import, $data),
                Import::TYPE_EXPENSES => $this->importExpenses($import, $data),
                Import::TYPE_EMPLOYEES => $this->importEmployees($import, $data),
                Import::TYPE_OPENING_BALANCES => $this->importOpeningBalances($import, $data),
                Import::TYPE_BUDGET_LINES => $this->importBudgetLines($import, $data),
                default => throw new \Exception('Unsupported import type: '.$import->type),
            };

            $import->update([
                'status' => Import::STATUS_COMPLETED,
                'processed_rows' => $totalRows,
                'completed_at' => now(),
                'errors' => ! empty($this->errors) ? $this->errors : null,
                'warnings' => ! empty($this->warnings) ? $this->warnings : null,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Import failed', [
                'import_id' => $import->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $import->update([
                'status' => Import::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
                'errors' => ! empty($this->errors) ? $this->errors : null,
            ]);

            return false;
        }
    }

    /**
     * Read file contents based on format
     */
    protected function readFile(Import $import): LazyCollection
    {
        $path = Storage::disk($this->disk)->path($import->file_path);

        if (! file_exists($path)) {
            throw new \Exception('Import file not found');
        }

        return match ($import->format) {
            Import::FORMAT_CSV => $this->readCsv($path),
            Import::FORMAT_XLSX => $this->readXlsx($path),
            Import::FORMAT_JSON => $this->readJson($path),
            default => throw new \Exception('Unsupported file format'),
        };
    }

    /**
     * Read CSV file
     */
    protected function readCsv(string $path): LazyCollection
    {
        // Streamed, so a large file is never held in memory whole (P3).
        return LazyCollection::make(function () use ($path) {
            if (($handle = fopen($path, 'r')) === false) {
                return;
            }

            try {
                $headers = null;
                $index = 0;
                while (($row = fgetcsv($handle)) !== false) {
                    if ($headers === null) {
                        // First row is headers
                        $headers = array_map(fn ($h) => Str::snake(trim((string) $h)), $row);

                        continue;
                    }

                    if (count($row) === count($headers)) {
                        // Our own exports mark formula-like cells; take the mark off (S4).
                        yield $index++ => array_combine($headers, array_map([Csv::class, 'unescapeCell'], $row));
                    }
                }
            } finally {
                fclose($handle);
            }
        });
    }

    /**
     * Read XLSX file
     */
    protected function readXlsx(string $path): LazyCollection
    {
        if (! Import::excelSupported()) {
            throw new \RuntimeException('Excel import is not available: the phpoffice/phpspreadsheet package is not installed.');
        }

        $spreadsheet = IOFactory::load($path);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        if (empty($rows)) {
            return LazyCollection::empty();
        }

        $headers = array_map(fn ($h) => Str::snake(trim($h ?? '')), array_shift($rows));
        $data = [];

        foreach ($rows as $row) {
            if (count($row) === count($headers)) {
                $rowData = array_combine($headers, $row);
                // Skip completely empty rows
                if (array_filter($rowData)) {
                    $data[] = $rowData;
                }
            }
        }

        return LazyCollection::make($data);
    }

    /**
     * Read JSON file
     */
    protected function readJson(string $path): LazyCollection
    {
        $content = file_get_contents($path);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON format: '.json_last_error_msg());
        }

        // If it's a keyed array (from backup), extract the data
        if (isset($data['data'])) {
            $data = $data['data'];
        }

        return LazyCollection::make(is_array($data) ? array_values($data) : []);
    }

    /**
     * Map row data using column mapping
     */
    protected function mapRow(array $row, array $mapping): array
    {
        $mapped = [];
        foreach ($mapping as $fileColumn => $dbField) {
            if (! empty($dbField) && isset($row[$fileColumn])) {
                $mapped[$dbField] = trim($row[$fileColumn]);
            }
        }

        return $mapped;
    }

    /**
     * Import customers
     */
    protected function importCustomers(Import $import, iterable $data): bool
    {
        $mapping = $import->column_mapping ?? [];
        $skipDuplicates = $this->options['skip_duplicates'] ?? true;
        $updateExisting = $this->options['update_existing'] ?? false;

        $successful = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($data as $index => $row) {
            $rowNum = $index + 2; // +2 for header row and 0-indexing

            try {
                $mapped = ! empty($mapping) ? $this->mapRow($row, $mapping) : $row;

                // Validate required fields
                if (empty($mapped['name'])) {
                    $this->errors[] = "Row {$rowNum}: Name is required";
                    $failed++;

                    continue;
                }

                // Check for existing customer
                $existing = Customer::where('tenant_id', $this->tenantId)
                    ->where(function ($q) use ($mapped) {
                        $q->where('email', $mapped['email'] ?? null);
                        if (! empty($mapped['name'])) {
                            $q->orWhere('name', $mapped['name']);
                        }
                    })
                    ->first();

                if ($existing) {
                    if ($updateExisting) {
                        $existing->update($this->prepareCustomerData($mapped));
                        $successful++;
                        $this->warnings[] = "Row {$rowNum}: Updated existing customer '{$mapped['name']}'";
                    } elseif ($skipDuplicates) {
                        $skipped++;
                    } else {
                        $this->errors[] = "Row {$rowNum}: Customer '{$mapped['name']}' already exists";
                        $failed++;
                    }

                    continue;
                }

                Customer::create(array_merge(
                    ['tenant_id' => $this->tenantId],
                    $this->prepareCustomerData($mapped)
                ));
                $successful++;

            } catch (\Exception $e) {
                $this->errors[] = "Row {$rowNum}: ".$e->getMessage();
                $failed++;
            }

            $this->reportProgress($import, $index + 1);
        }

        $import->update([
            'successful_rows' => $successful,
            'failed_rows' => $failed,
            'skipped_rows' => $skipped,
        ]);

        return true;
    }

    /**
     * Prepare customer data for database
     */
    protected function prepareCustomerData(array $data): array
    {
        return [
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'billing_address' => $data['billing_address'] ?? null,
            'billing_city' => $data['billing_city'] ?? null,
            'billing_state' => $data['billing_state'] ?? null,
            'billing_postal_code' => $data['billing_postal_code'] ?? null,
            'billing_country' => $data['billing_country'] ?? null,
            'shipping_address' => $data['shipping_address'] ?? null,
            'shipping_city' => $data['shipping_city'] ?? null,
            'shipping_state' => $data['shipping_state'] ?? null,
            'shipping_postal_code' => $data['shipping_postal_code'] ?? null,
            'shipping_country' => $data['shipping_country'] ?? null,
            'tax_number' => $data['tax_number'] ?? null,
            'website' => $data['website'] ?? null,
            'notes' => $data['notes'] ?? null,
            'credit_limit' => isset($data['credit_limit']) && $data['credit_limit'] !== '' ? (float) $data['credit_limit'] : 0,
            'payment_terms' => isset($data['payment_terms']) && $data['payment_terms'] !== '' ? (int) $data['payment_terms'] : 30,
        ];
    }

    /**
     * Import vendors
     */
    protected function importVendors(Import $import, iterable $data): bool
    {
        $mapping = $import->column_mapping ?? [];
        $skipDuplicates = $this->options['skip_duplicates'] ?? true;
        $updateExisting = $this->options['update_existing'] ?? false;

        $successful = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($data as $index => $row) {
            $rowNum = $index + 2;

            try {
                $mapped = ! empty($mapping) ? $this->mapRow($row, $mapping) : $row;

                if (empty($mapped['name'])) {
                    $this->errors[] = "Row {$rowNum}: Name is required";
                    $failed++;

                    continue;
                }

                $existing = Vendor::where('tenant_id', $this->tenantId)
                    ->where('name', $mapped['name'])
                    ->first();

                if ($existing) {
                    if ($updateExisting) {
                        $existing->update($this->prepareVendorData($mapped));
                        $successful++;
                    } elseif ($skipDuplicates) {
                        $skipped++;
                    } else {
                        $this->errors[] = "Row {$rowNum}: Vendor '{$mapped['name']}' already exists";
                        $failed++;
                    }

                    continue;
                }

                Vendor::create(array_merge(
                    ['tenant_id' => $this->tenantId],
                    $this->prepareVendorData($mapped)
                ));
                $successful++;

            } catch (\Exception $e) {
                $this->errors[] = "Row {$rowNum}: ".$e->getMessage();
                $failed++;
            }

            $this->reportProgress($import, $index + 1);
        }

        $import->update([
            'successful_rows' => $successful,
            'failed_rows' => $failed,
            'skipped_rows' => $skipped,
        ]);

        return true;
    }

    /**
     * Prepare vendor data
     */
    protected function prepareVendorData(array $data): array
    {
        return [
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'country' => $data['country'] ?? null,
            'tax_number' => $data['tax_number'] ?? null,
            'website' => $data['website'] ?? null,
            'notes' => $data['notes'] ?? null,
            'payment_terms' => isset($data['payment_terms']) ? (int) $data['payment_terms'] : 30,
            'account_number' => $data['account_number'] ?? null,
        ];
    }

    /**
     * Import items
     */
    protected function importItems(Import $import, iterable $data): bool
    {
        $mapping = $import->column_mapping ?? [];
        $skipDuplicates = $this->options['skip_duplicates'] ?? true;
        $updateExisting = $this->options['update_existing'] ?? false;

        $successful = 0;
        $failed = 0;
        $skipped = 0;

        // Cache categories and accounts
        $categories = ItemCategory::where('tenant_id', $this->tenantId)->pluck('id', 'name');
        $accounts = ChartOfAccount::where('tenant_id', $this->tenantId)->pluck('id', 'account_code');

        foreach ($data as $index => $row) {
            $rowNum = $index + 2;

            try {
                $mapped = ! empty($mapping) ? $this->mapRow($row, $mapping) : $row;

                if (empty($mapped['name'])) {
                    $this->errors[] = "Row {$rowNum}: Name is required";
                    $failed++;

                    continue;
                }

                // Determine item type
                $type = strtolower($mapped['type'] ?? 'product');
                if (! in_array($type, ['product', 'service'])) {
                    $type = 'product';
                }

                $existing = Item::where('tenant_id', $this->tenantId)
                    ->where(function ($q) use ($mapped) {
                        $q->where('name', $mapped['name']);
                        if (! empty($mapped['sku'])) {
                            $q->orWhere('sku', $mapped['sku']);
                        }
                    })
                    ->first();

                if ($existing) {
                    if ($updateExisting) {
                        $existing->update($this->prepareItemData($mapped, $type, $categories, $accounts));

                        // Create inventory record if doesn't exist and tracking is enabled
                        if ($existing->track_inventory && ! $existing->inventory) {
                            $initialStock = isset($mapped['initial_stock']) && $mapped['initial_stock'] !== '' ? (int) $mapped['initial_stock'] : 0;

                            Inventory::create([
                                'tenant_id' => $this->tenantId,
                                'item_id' => $existing->id,
                                'quantity' => $initialStock,
                                'reserved_quantity' => 0,
                                'unit_cost' => $existing->cost_price ?? 0,
                            ]);

                            if ($initialStock > 0) {
                                InventoryHistory::create([
                                    'tenant_id' => $this->tenantId,
                                    'item_id' => $existing->id,
                                    'type' => 'in',
                                    'quantity' => $initialStock,
                                    'notes' => 'Initial stock from import',
                                    'created_by' => auth()->id(),
                                ]);
                            }
                        }
                        $successful++;
                    } elseif ($skipDuplicates) {
                        // Still create inventory record if missing for skipped duplicates
                        if ($existing->track_inventory && ! $existing->inventory) {
                            $initialStock = isset($mapped['initial_stock']) && $mapped['initial_stock'] !== '' ? (int) $mapped['initial_stock'] : 0;

                            Inventory::create([
                                'tenant_id' => $this->tenantId,
                                'item_id' => $existing->id,
                                'quantity' => $initialStock,
                                'reserved_quantity' => 0,
                                'unit_cost' => $existing->cost_price ?? 0,
                            ]);

                            if ($initialStock > 0) {
                                InventoryHistory::create([
                                    'tenant_id' => $this->tenantId,
                                    'item_id' => $existing->id,
                                    'type' => 'in',
                                    'quantity' => $initialStock,
                                    'notes' => 'Initial stock from import',
                                    'created_by' => auth()->id(),
                                ]);
                            }
                        }
                        $skipped++;
                    } else {
                        $this->errors[] = "Row {$rowNum}: Item '{$mapped['name']}' already exists";
                        $failed++;
                    }

                    continue;
                }

                // Create or get category
                $categoryId = null;
                if (! empty($mapped['category'])) {
                    if (isset($categories[$mapped['category']])) {
                        $categoryId = $categories[$mapped['category']];
                    } else {
                        $category = ItemCategory::create([
                            'tenant_id' => $this->tenantId,
                            'name' => $mapped['category'],
                        ]);
                        $categoryId = $category->id;
                        $categories[$mapped['category']] = $categoryId;
                    }
                }

                $itemData = array_merge(
                    ['tenant_id' => $this->tenantId, 'category_id' => $categoryId],
                    $this->prepareItemData($mapped, $type, $categories, $accounts)
                );

                $item = Item::create($itemData);

                // Create inventory record if tracking inventory
                if ($item->track_inventory) {
                    $initialStock = isset($mapped['initial_stock']) && $mapped['initial_stock'] !== '' ? (int) $mapped['initial_stock'] : 0;

                    Inventory::create([
                        'tenant_id' => $this->tenantId,
                        'item_id' => $item->id,
                        'quantity' => $initialStock,
                        'reserved_quantity' => 0,
                        'unit_cost' => $item->cost_price ?? 0,
                    ]);

                    // Record initial stock in history if there's stock
                    if ($initialStock > 0) {
                        InventoryHistory::create([
                            'tenant_id' => $this->tenantId,
                            'item_id' => $item->id,
                            'type' => 'in',
                            'quantity' => $initialStock,
                            'notes' => 'Initial stock from import',
                            'created_by' => auth()->id(),
                        ]);
                    }
                }

                $successful++;

            } catch (\Exception $e) {
                $this->errors[] = "Row {$rowNum}: ".$e->getMessage();
                $failed++;
            }

            $this->reportProgress($import, $index + 1);
        }

        $import->update([
            'successful_rows' => $successful,
            'failed_rows' => $failed,
            'skipped_rows' => $skipped,
        ]);

        return true;
    }

    /**
     * Prepare item data
     */
    protected function prepareItemData(array $data, string $type, $categories, $accounts): array
    {
        $isTaxable = isset($data['is_taxable'])
            ? in_array(strtolower($data['is_taxable']), ['yes', 'true', '1'])
            : true;

        $trackInventory = isset($data['track_inventory'])
            ? in_array(strtolower($data['track_inventory']), ['yes', 'true', '1'])
            : ($type === 'product');

        return [
            'name' => $data['name'] ?? null,
            'type' => $type,
            'sku' => $data['sku'] ?? null,
            'description' => $data['description'] ?? null,
            'selling_price' => isset($data['sale_price']) && $data['sale_price'] !== '' ? (float) $data['sale_price'] : (isset($data['selling_price']) && $data['selling_price'] !== '' ? (float) $data['selling_price'] : 0),
            'cost_price' => isset($data['purchase_price']) && $data['purchase_price'] !== '' ? (float) $data['purchase_price'] : (isset($data['cost_price']) && $data['cost_price'] !== '' ? (float) $data['cost_price'] : 0),
            'unit' => $data['unit'] ?? 'pcs',
            'tax_rate' => isset($data['tax_rate']) ? (float) $data['tax_rate'] : 0,
            'is_taxable' => $isTaxable,
            'track_inventory' => $trackInventory,
            'reorder_level' => isset($data['reorder_level']) ? (int) $data['reorder_level'] : 0,
            'sales_account_id' => $accounts[$data['sales_account'] ?? ''] ?? null,
            'purchase_account_id' => $accounts[$data['purchase_account'] ?? ''] ?? null,
            'inventory_account_id' => $accounts[$data['inventory_account'] ?? ''] ?? null,
        ];
    }

    /**
     * Import chart of accounts
     */
    protected function importChartOfAccounts(Import $import, iterable $data): bool
    {
        $mapping = $import->column_mapping ?? [];
        $skipDuplicates = $this->options['skip_duplicates'] ?? true;
        $updateExisting = $this->options['update_existing'] ?? false;

        $successful = 0;
        $failed = 0;
        $skipped = 0;

        // Valid account types
        $validTypes = ['asset', 'liability', 'equity', 'revenue', 'expense'];

        // First pass: create all accounts without parents
        $createdAccounts = [];
        foreach ($data as $index => $row) {
            $rowNum = $index + 2;

            try {
                $mapped = ! empty($mapping) ? $this->mapRow($row, $mapping) : $row;

                if (empty($mapped['code']) || empty($mapped['name']) || empty($mapped['type'])) {
                    $this->errors[] = "Row {$rowNum}: Code, Name, and Type are required";
                    $failed++;

                    continue;
                }

                $type = strtolower($mapped['type']);
                if (! in_array($type, $validTypes)) {
                    $this->errors[] = "Row {$rowNum}: Invalid account type '{$mapped['type']}'. Must be one of: ".implode(', ', $validTypes);
                    $failed++;

                    continue;
                }

                $existing = ChartOfAccount::where('tenant_id', $this->tenantId)
                    ->where('account_code', $mapped['code'])
                    ->first();

                if ($existing) {
                    if ($updateExisting) {
                        $existing->update([
                            'name' => $mapped['name'],
                            'type' => $type,
                            'description' => $mapped['description'] ?? null,
                            'is_active' => ! isset($mapped['is_active']) || in_array(strtolower($mapped['is_active']), ['yes', 'true', '1']),
                        ]);
                        $createdAccounts[$mapped['code']] = $existing->id;
                        $successful++;
                    } elseif ($skipDuplicates) {
                        $createdAccounts[$mapped['code']] = $existing->id;
                        $skipped++;
                    } else {
                        $this->errors[] = "Row {$rowNum}: Account with code '{$mapped['code']}' already exists";
                        $failed++;
                    }

                    continue;
                }

                $account = ChartOfAccount::create([
                    'tenant_id' => $this->tenantId,
                    'account_code' => $mapped['code'],
                    'name' => $mapped['name'],
                    'type' => $type,
                    'description' => $mapped['description'] ?? null,
                    'is_active' => ! isset($mapped['is_active']) || in_array(strtolower($mapped['is_active']), ['yes', 'true', '1']),
                ]);
                $createdAccounts[$mapped['code']] = $account->id;
                $successful++;

            } catch (\Exception $e) {
                $this->errors[] = "Row {$rowNum}: ".$e->getMessage();
                $failed++;
            }

            $this->reportProgress($import, $index + 1);
        }

        // Second pass: update parent relationships
        foreach ($data as $index => $row) {
            $mapped = ! empty($mapping) ? $this->mapRow($row, $mapping) : $row;

            if (! empty($mapped['parent_code']) && isset($createdAccounts[$mapped['code']])) {
                $parentId = $createdAccounts[$mapped['parent_code']] ?? null;
                if ($parentId) {
                    ChartOfAccount::where('id', $createdAccounts[$mapped['code']])
                        ->update(['parent_id' => $parentId]);
                }
            }
        }

        $import->update([
            'successful_rows' => $successful,
            'failed_rows' => $failed,
            'skipped_rows' => $skipped,
        ]);

        return true;
    }

    /**
     * Import expenses
     */
    protected function importExpenses(Import $import, iterable $data): bool
    {
        $mapping = $import->column_mapping ?? [];

        $successful = 0;
        $failed = 0;
        $skipped = 0;

        // Cache accounts and vendors
        // Accounts can be given by code or by name. The codes used to be read
        // from a column that doesn't exist, and merge() renumbers numeric keys
        // like "5000" anyway, so only names ever matched (found by PHPStan).
        $accounts = [];
        foreach (ChartOfAccount::where('tenant_id', $this->tenantId)->where('type', 'expense')->get() as $a) {
            $accounts[strtolower((string) $a->name)] = $a;
            $accounts[strtolower((string) $a->account_code)] = $a;
        }

        $vendors = Vendor::where('tenant_id', $this->tenantId)->pluck('id', 'name');
        $paymentAccounts = ChartOfAccount::where('tenant_id', $this->tenantId)
            ->whereIn('type', ['asset'])
            ->get()
            ->keyBy('account_code');

        foreach ($data as $index => $row) {
            $rowNum = $index + 2;

            try {
                $mapped = ! empty($mapping) ? $this->mapRow($row, $mapping) : $row;

                if (empty($mapped['date']) || empty($mapped['account']) || empty($mapped['amount'])) {
                    $this->errors[] = "Row {$rowNum}: Date, Account, and Amount are required";
                    $failed++;

                    continue;
                }

                // Find expense account
                $accountKey = strtolower($mapped['account']);
                $expenseAccount = $accounts[$accountKey] ?? null;

                if (! $expenseAccount) {
                    $this->errors[] = "Row {$rowNum}: Expense account '{$mapped['account']}' not found";
                    $failed++;

                    continue;
                }

                // Find vendor if specified
                $vendorId = null;
                if (! empty($mapped['vendor'])) {
                    $vendorId = $vendors[$mapped['vendor']] ?? null;
                    if (! $vendorId) {
                        // Create vendor
                        $vendor = Vendor::create([
                            'tenant_id' => $this->tenantId,
                            'name' => $mapped['vendor'],
                        ]);
                        $vendorId = $vendor->id;
                        $vendors[$mapped['vendor']] = $vendorId;
                    }
                }

                // Find payment account if specified
                $paymentAccountId = null;
                if (! empty($mapped['payment_account'])) {
                    $paymentAccountId = $paymentAccounts[$mapped['payment_account']]->id ?? null;
                }

                // Imported expenses start as drafts and go through the normal
                // approve-and-pay flow, like ones entered on the form. The old
                // call used columns expenses doesn't have and no expense
                // number, so every row failed.
                $amount = round((float) $mapped['amount'], 2);
                Expense::create([
                    'tenant_id' => $this->tenantId,
                    'expense_number' => Expense::generateNumber($this->tenantId),
                    'name' => $mapped['description'] ?? $expenseAccount->name,
                    'expense_account_id' => $expenseAccount->id,
                    'vendor_id' => $vendorId,
                    'paid_through_id' => $paymentAccountId,
                    'expense_date' => $this->parseDate($mapped['date']),
                    'amount' => $amount,
                    'tax_amount' => 0,
                    'total' => $amount,
                    'description' => $mapped['description'] ?? null,
                    'reference' => $mapped['reference'] ?? null,
                    'payment_method' => $mapped['payment_method'] ?? null,
                    'status' => Expense::STATUS_DRAFT,
                    'created_by' => $import->user_id,
                ]);
                $successful++;

            } catch (\Exception $e) {
                $this->errors[] = "Row {$rowNum}: ".$e->getMessage();
                $failed++;
            }

            $this->reportProgress($import, $index + 1);
        }

        $import->update([
            'successful_rows' => $successful,
            'failed_rows' => $failed,
            'skipped_rows' => $skipped,
        ]);

        return true;
    }

    /**
     * Import employees
     */
    protected function importEmployees(Import $import, iterable $data): bool
    {
        $mapping = $import->column_mapping ?? [];
        $skipDuplicates = $this->options['skip_duplicates'] ?? true;
        $updateExisting = $this->options['update_existing'] ?? false;

        $successful = 0;
        $failed = 0;
        $skipped = 0;

        // Cache departments and designations
        $departments = Department::where('tenant_id', $this->tenantId)->pluck('id', 'name');
        $designations = Designation::where('tenant_id', $this->tenantId)->pluck('id', 'name');

        foreach ($data as $index => $row) {
            $rowNum = $index + 2;

            try {
                $mapped = ! empty($mapping) ? $this->mapRow($row, $mapping) : $row;

                if (empty($mapped['name']) || empty($mapped['email'])) {
                    $this->errors[] = "Row {$rowNum}: Name and Email are required";
                    $failed++;

                    continue;
                }

                $existing = Employee::where('tenant_id', $this->tenantId)
                    ->where('email', $mapped['email'])
                    ->first();

                if ($existing) {
                    if ($updateExisting) {
                        $updates = $this->prepareEmployeeData($mapped, $departments, $designations);
                        if (empty($updates['employee_id'])) {
                            unset($updates['employee_id']); // keep the employee's current ID
                        }
                        $existing->update($updates);
                        $successful++;
                    } elseif ($skipDuplicates) {
                        $skipped++;
                    } else {
                        $this->errors[] = "Row {$rowNum}: Employee with email '{$mapped['email']}' already exists";
                        $failed++;
                    }

                    continue;
                }

                // Create or get department
                $departmentId = null;
                if (! empty($mapped['department'])) {
                    $departmentId = $departments[$mapped['department']] ?? null;
                    if (! $departmentId) {
                        $dept = Department::create([
                            'tenant_id' => $this->tenantId,
                            'name' => $mapped['department'],
                        ]);
                        $departmentId = $dept->id;
                        $departments[$mapped['department']] = $departmentId;
                    }
                }

                // Create or get designation
                $designationId = null;
                if (! empty($mapped['designation'])) {
                    $designationId = $designations[$mapped['designation']] ?? null;
                    if (! $designationId) {
                        $desig = Designation::create([
                            'tenant_id' => $this->tenantId,
                            'name' => $mapped['designation'],
                        ]);
                        $designationId = $desig->id;
                        $designations[$mapped['designation']] = $designationId;
                    }
                }

                $employeeData = $this->prepareEmployeeData($mapped, $departments, $designations);
                if (empty($employeeData['employee_id'])) {
                    $employeeData['employee_id'] = Employee::generateEmployeeId($this->tenantId);
                } elseif (Employee::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('employee_id', $employeeData['employee_id'])->exists()) {
                    $this->errors[] = "Row {$rowNum}: employee ID {$employeeData['employee_id']} already exists";
                    $failed++;

                    continue;
                }

                Employee::create(array_merge(
                    [
                        'tenant_id' => $this->tenantId,
                        'department_id' => $departmentId,
                        'designation_id' => $designationId,
                    ],
                    $employeeData
                ));
                $successful++;

            } catch (\Exception $e) {
                $this->errors[] = "Row {$rowNum}: ".$e->getMessage();
                $failed++;
            }

            $this->reportProgress($import, $index + 1);
        }

        $import->update([
            'successful_rows' => $successful,
            'failed_rows' => $failed,
            'skipped_rows' => $skipped,
        ]);

        return true;
    }

    /**
     * Prepare employee data
     */
    protected function prepareEmployeeData(array $data, $departments, $designations): array
    {
        return [
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'employee_id' => $data['employee_id'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'date_of_birth' => ! empty($data['date_of_birth']) ? $this->parseDate($data['date_of_birth']) : null,
            'hire_date' => ! empty($data['hire_date']) ? $this->parseDate($data['hire_date']) : null,
            'basic_salary' => isset($data['salary']) ? (float) $data['salary'] : 0,
            'pay_frequency' => $data['pay_frequency'] ?? 'monthly',
            'bank_name' => $data['bank_name'] ?? null,
            'bank_account_number' => $data['bank_account'] ?? null,
            'status' => 'active',
        ];
    }

    /**
     * Import opening balances
     */
    protected function importOpeningBalances(Import $import, iterable $data): bool
    {
        $mapping = $import->column_mapping ?? [];

        $successful = 0;
        $failed = 0;
        $skipped = 0;

        // Cache accounts
        $accounts = ChartOfAccount::where('tenant_id', $this->tenantId)
            ->get()
            ->keyBy('account_code');

        $asOfDate = null;
        $journalEntries = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($data as $index => $row) {
            $rowNum = $index + 2;

            try {
                $mapped = ! empty($mapping) ? $this->mapRow($row, $mapping) : $row;

                if (empty($mapped['account_code'])) {
                    $this->errors[] = "Row {$rowNum}: Account Code is required";
                    $failed++;

                    continue;
                }

                $account = $accounts[$mapped['account_code']] ?? null;
                if (! $account) {
                    $this->errors[] = "Row {$rowNum}: Account '{$mapped['account_code']}' not found";
                    $failed++;

                    continue;
                }

                $debit = (float) ($mapped['debit'] ?? 0);
                $credit = (float) ($mapped['credit'] ?? 0);

                if ($debit == 0 && $credit == 0) {
                    $skipped++;

                    continue;
                }

                if (! $asOfDate && ! empty($mapped['as_of_date'])) {
                    $asOfDate = $this->parseDate($mapped['as_of_date']);
                }

                $journalEntries[] = [
                    'account_id' => $account->id,
                    'debit' => $debit,
                    'credit' => $credit,
                    'description' => 'Opening Balance',
                ];

                $totalDebit += $debit;
                $totalCredit += $credit;
                $successful++;

            } catch (\Exception $e) {
                $this->errors[] = "Row {$rowNum}: ".$e->getMessage();
                $failed++;
            }

            $this->reportProgress($import, $index + 1);
        }

        // Opening balances must balance: debits = credits. Previously this was
        // only a warning, an unbalanced journal was saved, it had no journal
        // number and the account balances were never updated.
        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);

        if (! empty($journalEntries) && abs($totalDebit - $totalCredit) >= 0.005) {
            $this->errors[] = 'Total debits ('.number_format($totalDebit, 2).') do not equal total credits ('
                .number_format($totalCredit, 2).'), a difference of '.number_format(abs($totalDebit - $totalCredit), 2)
                .'. Nothing was imported. Correct the file and import it again.';
            $failed += $successful;
            $successful = 0;
            $journalEntries = [];
        }

        if (! empty($journalEntries)) {
            DB::transaction(function () use ($journalEntries, $asOfDate) {
                $journal = Journal::create([
                    'tenant_id' => $this->tenantId,
                    'journal_number' => Journal::generateNumber($this->tenantId),
                    'journal_date' => $asOfDate ?? now(),
                    'reference' => 'OB-'.date('Ymd'),
                    'description' => 'Opening Balances Import',
                    'status' => 'posted',
                    'is_posted' => true,
                    'posted_at' => now(),
                    'created_by' => auth()->id(),
                ]);

                foreach ($journalEntries as $entry) {
                    JournalEntry::create([
                        'journal_id' => $journal->id,
                        'account_id' => $entry['account_id'],
                        'debit' => round($entry['debit'], 2),
                        'credit' => round($entry['credit'], 2),
                        'description' => $entry['description'],
                    ]);
                }

                $journal->updateTotals();

                // Apply to account balances (checks the journal balances)
                app(JournalService::class)->updateAccountBalances($journal);
            });
        }

        $import->update([
            'successful_rows' => $successful,
            'failed_rows' => $failed,
            'skipped_rows' => $skipped,
        ]);

        return true;
    }

    /**
     * Import budget line items into an existing budget
     */
    public function importBudgetLines(Import $import, iterable $data, ?Budget $budget = null): bool
    {
        $mapping = $import->column_mapping ?? [];
        $skipDuplicates = $this->options['skip_duplicates'] ?? true;
        $updateExisting = $this->options['update_existing'] ?? false;
        $budgetId = $this->options['budget_id'] ?? null;

        if (! $budget && $budgetId) {
            $budget = Budget::where('tenant_id', $this->tenantId)->find($budgetId);
        }

        if (! $budget) {
            throw new \Exception('Budget not found or not specified');
        }

        if ($budget->isLocked()) {
            throw new \Exception('Cannot import lines into a locked budget');
        }

        $successful = 0;
        $failed = 0;
        $skipped = 0;
        $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];

        foreach ($data as $index => $row) {
            $rowNum = $index + 2;

            try {
                $mapped = ! empty($mapping) ? $this->mapRow($row, $mapping) : $row;

                // Validate required field
                if (empty($mapped['account_code'])) {
                    $this->errors[] = "Row {$rowNum}: Account Code is required";
                    $failed++;

                    continue;
                }

                // Find the account
                $account = ChartOfAccount::where('tenant_id', $this->tenantId)
                    ->where('account_code', $mapped['account_code'])
                    ->first();

                if (! $account) {
                    $this->errors[] = "Row {$rowNum}: Account with code '{$mapped['account_code']}' not found";
                    $failed++;

                    continue;
                }

                // Only allow income and expense accounts
                if (! in_array($account->type, ['income', 'expense'])) {
                    $this->errors[] = "Row {$rowNum}: Account '{$mapped['account_code']}' is not an income or expense account";
                    $failed++;

                    continue;
                }

                // Check for existing line with same account
                $existing = BudgetLine::where('budget_id', $budget->id)
                    ->where('account_id', $account->id)
                    ->first();

                // Prepare line data
                $lineData = ['account_id' => $account->id];
                foreach ($months as $month) {
                    $lineData[$month] = isset($mapped[$month]) && $mapped[$month] !== '' ? (float) $mapped[$month] : 0;
                }
                $lineData['notes'] = $mapped['notes'] ?? null;

                if ($existing) {
                    if ($updateExisting) {
                        $existing->fill($lineData);
                        $existing->calculateAnnualTotal();
                        $existing->save();
                        $successful++;
                        $this->warnings[] = "Row {$rowNum}: Updated existing line for account '{$mapped['account_code']}'";
                    } elseif ($skipDuplicates) {
                        $skipped++;
                    } else {
                        $this->errors[] = "Row {$rowNum}: Budget line for account '{$mapped['account_code']}' already exists";
                        $failed++;
                    }

                    continue;
                }

                $line = new BudgetLine($lineData);
                $line->budget_id = $budget->id;
                $line->calculateAnnualTotal();
                $line->save();
                $successful++;

            } catch (\Exception $e) {
                $this->errors[] = "Row {$rowNum}: ".$e->getMessage();
                $failed++;
            }

            $this->reportProgress($import, $index + 1);
        }

        $import->update([
            'successful_rows' => $successful,
            'failed_rows' => $failed,
            'skipped_rows' => $skipped,
        ]);

        return true;
    }

    /**
     * Parse date from various formats
     */
    protected function parseDate(string $date): string
    {
        // Try common formats
        $formats = ['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d', 'd-m-Y', 'm-d-Y'];

        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $date);
            if ($parsed !== false) {
                return $parsed->format('Y-m-d');
            }
        }

        // Try strtotime as fallback
        $timestamp = strtotime($date);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return $date;
    }

    /**
     * Preview file contents (first N rows)
     */
    public function previewFile(string $path, string $format, int $rows = 5): array
    {
        $fullPath = Storage::disk($this->disk)->path($path);

        $data = match ($format) {
            Import::FORMAT_CSV => $this->readCsv($fullPath),
            Import::FORMAT_XLSX => $this->readXlsx($fullPath),
            Import::FORMAT_JSON => $this->readJson($fullPath),
            default => LazyCollection::empty(),
        };

        $first = $data->first();

        return [
            'headers' => is_array($first) ? array_keys($first) : [],
            'rows' => $data->take($rows)->values()->all(),
            'total_rows' => $data->count(),
        ];
    }

    /**
     * Saves progress every 500 rows rather than after every row (P3).
     */
    protected function reportProgress(Import $import, int $processed): void
    {
        if ($processed % 500 === 0) {
            $import->update(['processed_rows' => $processed]);
        }
    }

    /**
     * Get sample template for import type
     */
    public static function getSampleData(string $type): array
    {
        return match ($type) {
            Import::TYPE_CUSTOMERS => [
                ['name' => 'John Doe', 'email' => 'john@example.com', 'phone' => '555-0100', 'company_name' => 'Acme Corp', 'billing_address' => '123 Main St', 'billing_city' => 'New York', 'billing_state' => 'NY', 'billing_postal_code' => '10001'],
                ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'phone' => '555-0101', 'company_name' => 'Tech Inc', 'billing_address' => '456 Oak Ave', 'billing_city' => 'Los Angeles', 'billing_state' => 'CA', 'billing_postal_code' => '90001'],
            ],
            Import::TYPE_VENDORS => [
                ['name' => 'Supply Co', 'email' => 'info@supplyco.com', 'phone' => '555-0200', 'address' => '789 Elm St', 'city' => 'Chicago', 'state' => 'IL', 'postal_code' => '60601'],
                ['name' => 'Parts Plus', 'email' => 'sales@partsplus.com', 'phone' => '555-0201', 'address' => '321 Pine Rd', 'city' => 'Houston', 'state' => 'TX', 'postal_code' => '77001'],
            ],
            Import::TYPE_ITEMS => [
                ['name' => 'Widget A', 'type' => 'product', 'sku' => 'WGT-001', 'sale_price' => '29.99', 'purchase_price' => '15.00', 'category' => 'Widgets', 'track_inventory' => 'yes', 'initial_stock' => '100'],
                ['name' => 'Consulting Service', 'type' => 'service', 'sku' => 'SVC-001', 'sale_price' => '150.00', 'description' => 'Professional consulting per hour'],
            ],
            Import::TYPE_CHART_OF_ACCOUNTS => [
                ['code' => '1000', 'name' => 'Cash', 'type' => 'asset', 'description' => 'Cash on hand'],
                ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'description' => 'Money owed by customers'],
                ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability', 'description' => 'Money owed to vendors'],
                ['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'revenue', 'description' => 'Income from sales'],
                ['code' => '5000', 'name' => 'Cost of Goods Sold', 'type' => 'expense', 'description' => 'Direct costs'],
            ],
            Import::TYPE_EMPLOYEES => [
                ['name' => 'Alice Johnson', 'email' => 'alice@company.com', 'employee_id' => 'EMP001', 'department' => 'Sales', 'designation' => 'Sales Manager', 'hire_date' => '2024-01-15', 'salary' => '5000'],
                ['name' => 'Bob Williams', 'email' => 'bob@company.com', 'employee_id' => 'EMP002', 'department' => 'Engineering', 'designation' => 'Developer', 'hire_date' => '2024-03-01', 'salary' => '4500'],
            ],
            Import::TYPE_EXPENSES => [
                ['date' => '2025-01-05', 'account' => 'Office Supplies', 'amount' => '150.00', 'vendor' => 'Supply Co', 'reference' => 'INV-001', 'description' => 'Printer paper and ink', 'payment_account' => '1000'],
                ['date' => '2025-01-10', 'account' => 'Utilities', 'amount' => '250.00', 'vendor' => 'City Power', 'reference' => 'BILL-2025-01', 'description' => 'Monthly electricity bill', 'payment_account' => '1000'],
            ],
            Import::TYPE_INVOICES => [
                ['invoice_number' => 'INV-001', 'customer' => 'John Doe', 'date' => '2025-01-15', 'due_date' => '2025-02-14', 'item' => 'Widget A', 'quantity' => '5', 'unit_price' => '29.99', 'description' => 'Widget order', 'tax_rate' => '10'],
                ['invoice_number' => 'INV-001', 'customer' => 'John Doe', 'date' => '2025-01-15', 'due_date' => '2025-02-14', 'item' => 'Consulting Service', 'quantity' => '2', 'unit_price' => '150.00', 'description' => 'Setup assistance', 'tax_rate' => '0'],
                ['invoice_number' => 'INV-002', 'customer' => 'Jane Smith', 'date' => '2025-01-20', 'due_date' => '2025-02-19', 'item' => 'Widget A', 'quantity' => '10', 'unit_price' => '29.99', 'description' => 'Bulk widget order', 'tax_rate' => '10'],
            ],
            Import::TYPE_BILLS => [
                ['bill_number' => 'BILL-001', 'vendor' => 'Supply Co', 'date' => '2025-01-10', 'due_date' => '2025-02-09', 'item' => 'Raw Materials', 'quantity' => '100', 'unit_price' => '5.00', 'description' => 'Monthly supplies', 'tax_rate' => '0'],
                ['bill_number' => 'BILL-002', 'vendor' => 'Parts Plus', 'date' => '2025-01-12', 'due_date' => '2025-02-11', 'item' => 'Equipment Parts', 'quantity' => '25', 'unit_price' => '12.50', 'description' => 'Replacement parts', 'tax_rate' => '5'],
            ],
            Import::TYPE_JOURNALS => [
                ['date' => '2025-01-01', 'reference' => 'JE-001', 'description' => 'Owner investment', 'account_code' => '1000', 'account_name' => 'Cash', 'debit' => '10000.00', 'credit' => '0.00'],
                ['date' => '2025-01-01', 'reference' => 'JE-001', 'description' => 'Owner investment', 'account_code' => '3000', 'account_name' => 'Owner Equity', 'debit' => '0.00', 'credit' => '10000.00'],
                ['date' => '2025-01-05', 'reference' => 'JE-002', 'description' => 'Purchase equipment', 'account_code' => '1500', 'account_name' => 'Equipment', 'debit' => '2000.00', 'credit' => '0.00'],
                ['date' => '2025-01-05', 'reference' => 'JE-002', 'description' => 'Purchase equipment', 'account_code' => '1000', 'account_name' => 'Cash', 'debit' => '0.00', 'credit' => '2000.00'],
            ],
            Import::TYPE_BUDGET_LINES => [
                ['account_code' => '4000', 'account_name' => 'Sales Revenue', 'jan' => '5000.00', 'feb' => '5500.00', 'mar' => '6000.00', 'apr' => '5000.00', 'may' => '5500.00', 'jun' => '6000.00', 'jul' => '5000.00', 'aug' => '5500.00', 'sep' => '6000.00', 'oct' => '5500.00', 'nov' => '6000.00', 'dec' => '7000.00', 'notes' => 'Projected sales revenue'],
                ['account_code' => '5000', 'account_name' => 'Cost of Goods Sold', 'jan' => '2000.00', 'feb' => '2200.00', 'mar' => '2400.00', 'apr' => '2000.00', 'may' => '2200.00', 'jun' => '2400.00', 'jul' => '2000.00', 'aug' => '2200.00', 'sep' => '2400.00', 'oct' => '2200.00', 'nov' => '2400.00', 'dec' => '2800.00', 'notes' => 'Estimated COGS'],
                ['account_code' => '6000', 'account_name' => 'Office Supplies', 'jan' => '200.00', 'feb' => '200.00', 'mar' => '200.00', 'apr' => '200.00', 'may' => '200.00', 'jun' => '200.00', 'jul' => '200.00', 'aug' => '200.00', 'sep' => '200.00', 'oct' => '200.00', 'nov' => '200.00', 'dec' => '200.00', 'notes' => 'Monthly office supplies'],
            ],
            Import::TYPE_OPENING_BALANCES => [
                ['account_code' => '1000', 'account_name' => 'Cash', 'debit' => '10000.00', 'credit' => '0.00', 'as_of_date' => '2025-01-01'],
                ['account_code' => '1100', 'account_name' => 'Accounts Receivable', 'debit' => '5000.00', 'credit' => '0.00', 'as_of_date' => '2025-01-01'],
                ['account_code' => '2000', 'account_name' => 'Accounts Payable', 'debit' => '0.00', 'credit' => '3000.00', 'as_of_date' => '2025-01-01'],
                ['account_code' => '3000', 'account_name' => 'Owner Equity', 'debit' => '0.00', 'credit' => '12000.00', 'as_of_date' => '2025-01-01'],
            ],
            default => [],
        };
    }
}
