<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PhpOffice\PhpSpreadsheet\IOFactory;

class Import extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'type',
        'format',
        'status',
        'original_filename',
        'file_path',
        'file_size',
        'column_mapping',
        'options',
        'total_rows',
        'processed_rows',
        'successful_rows',
        'failed_rows',
        'skipped_rows',
        'errors',
        'warnings',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'column_mapping' => 'array',
        'options' => 'array',
        'errors' => 'array',
        'warnings' => 'array',
        'file_size' => 'integer',
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'successful_rows' => 'integer',
        'failed_rows' => 'integer',
        'skipped_rows' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';

    const STATUS_VALIDATING = 'validating';

    const STATUS_MAPPING = 'mapping';

    const STATUS_PROCESSING = 'processing';

    const STATUS_COMPLETED = 'completed';

    const STATUS_FAILED = 'failed';

    // Type constants
    const TYPE_CUSTOMERS = 'customers';

    const TYPE_VENDORS = 'vendors';

    const TYPE_ITEMS = 'items';

    const TYPE_CHART_OF_ACCOUNTS = 'chart_of_accounts';

    const TYPE_INVOICES = 'invoices';

    const TYPE_BILLS = 'bills';

    const TYPE_EXPENSES = 'expenses';

    const TYPE_EMPLOYEES = 'employees';

    const TYPE_JOURNALS = 'journals';

    const TYPE_OPENING_BALANCES = 'opening_balances';

    const TYPE_BUDGET_LINES = 'budget_lines';

    // Format constants
    const FORMAT_CSV = 'csv';

    const FORMAT_XLSX = 'xlsx';

    const FORMAT_JSON = 'json';

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * The types the import screen offers.
     *
     * Not the same as the list below, which is labels for every type an
     * import row might hold, including older ones.
     *
     * Invoices, bills and journal entries were offered here, had sample
     * templates to download, and then failed at the end of the job with
     * "Unsupported import type": ImportService has no reader for them. A
     * person could choose one, download the template, fill it in, upload
     * it, map every column and only then be told. Budget lines belong to
     * a budget and are imported from the budget's own page; chosen here
     * they always failed with "Budget not found".
     *
     * Offered again as soon as ImportService can read them.
     *
     * @return array<string, string>
     */
    public static function getSupportedImportTypes(): array
    {
        return array_intersect_key(self::getImportTypes(), array_flip([
            self::TYPE_CUSTOMERS,
            self::TYPE_VENDORS,
            self::TYPE_ITEMS,
            self::TYPE_CHART_OF_ACCOUNTS,
            self::TYPE_INVOICES,
            self::TYPE_BILLS,
            self::TYPE_JOURNALS,
            self::TYPE_EXPENSES,
            self::TYPE_EMPLOYEES,
            self::TYPE_OPENING_BALANCES,
        ]));
    }

    /**
     * The permission each import type needs (audit MB-12). "import data"
     * only opens the Imports screen; without this map it let anyone with it
     * create employees, invoices, journals or opening balances.
     */
    public const PERMISSIONS = [
        self::TYPE_CUSTOMERS => 'create customers',
        self::TYPE_VENDORS => 'create vendors',
        self::TYPE_ITEMS => 'create items',
        self::TYPE_CHART_OF_ACCOUNTS => 'create chart-of-accounts',
        self::TYPE_INVOICES => 'create invoices',
        self::TYPE_BILLS => 'create bills',
        self::TYPE_JOURNALS => 'create journals',
        self::TYPE_EXPENSES => 'create expenses',
        self::TYPE_EMPLOYEES => 'create employees',
        self::TYPE_OPENING_BALANCES => 'edit chart-of-accounts',
        self::TYPE_BUDGET_LINES => 'edit budgets',
    ];

    public static function allowedFor(User $user, string $type): bool
    {
        return isset(self::PERMISSIONS[$type]) && $user->can(self::PERMISSIONS[$type]);
    }

    /**
     * The types on offer that $user may import.
     *
     * @return array<string, string>
     */
    public static function getSupportedImportTypesFor(User $user): array
    {
        return array_filter(self::getSupportedImportTypes(), fn ($type) => self::allowedFor($user, $type), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Labels for every import type, including the ones not offered.
     *
     * Kept whole so an import recorded before a type was withdrawn still
     * shows its name rather than a bare slug.
     */
    public static function getImportTypes(): array
    {
        return [
            self::TYPE_CUSTOMERS => 'Customers',
            self::TYPE_VENDORS => 'Vendors',
            self::TYPE_ITEMS => 'Items & Products',
            self::TYPE_CHART_OF_ACCOUNTS => 'Chart of Accounts',
            self::TYPE_INVOICES => 'Invoices',
            self::TYPE_BILLS => 'Bills',
            self::TYPE_EXPENSES => 'Expenses',
            self::TYPE_EMPLOYEES => 'Employees',
            self::TYPE_JOURNALS => 'Journal Entries',
            self::TYPE_OPENING_BALANCES => 'Opening Balances',
            self::TYPE_BUDGET_LINES => 'Budget Line Items',
        ];
    }

    /**
     * Excel files need the phpoffice/phpspreadsheet package. Until it is
     * installed, Excel uploads are refused with a clear message instead of
     * failing during processing (finding H5).
     */
    public static function excelSupported(): bool
    {
        return class_exists(IOFactory::class);
    }

    /**
     * File extensions accepted by the upload form.
     */
    public static function acceptedExtensions(): array
    {
        return self::excelSupported()
            ? ['csv', 'txt', 'xlsx', 'xls', 'json']
            : ['csv', 'txt', 'json'];
    }

    /**
     * Get available formats with labels
     */
    public static function getFormats(): array
    {
        $formats = [
            self::FORMAT_CSV => 'CSV (Comma Separated Values)',
            self::FORMAT_XLSX => 'Excel Spreadsheet (.xlsx)',
            self::FORMAT_JSON => 'JSON (JavaScript Object Notation)',
        ];

        if (! self::excelSupported()) {
            unset($formats[self::FORMAT_XLSX]);
        }

        return $formats;
    }

    /**
     * Get required fields for each import type
     */
    public static function getRequiredFields(string $type): array
    {
        return match ($type) {
            self::TYPE_CUSTOMERS => ['name', 'email'],
            self::TYPE_VENDORS => ['name'],
            self::TYPE_ITEMS => ['name', 'type'],
            self::TYPE_CHART_OF_ACCOUNTS => ['code', 'name', 'type'],
            self::TYPE_INVOICES => ['customer', 'invoice_number', 'invoice_date', 'total'],
            self::TYPE_BILLS => ['vendor', 'bill_number', 'bill_date', 'total'],
            self::TYPE_EXPENSES => ['date', 'account', 'amount'],
            self::TYPE_EMPLOYEES => ['name', 'email'],
            self::TYPE_JOURNALS => ['date', 'reference', 'entries'],
            self::TYPE_OPENING_BALANCES => ['account_code', 'debit', 'credit'],
            self::TYPE_BUDGET_LINES => ['account_code'],
            default => [],
        };
    }

    /**
     * Get all available fields for each import type
     */
    public static function getAvailableFields(string $type): array
    {
        return match ($type) {
            self::TYPE_CUSTOMERS => [
                'name' => 'Name *',
                'email' => 'Email *',
                'phone' => 'Phone',
                'company_name' => 'Company Name',
                'billing_address' => 'Billing Address',
                'billing_city' => 'Billing City',
                'billing_state' => 'Billing State',
                'billing_postal_code' => 'Billing Postal Code',
                'billing_country' => 'Billing Country',
                'shipping_address' => 'Shipping Address',
                'shipping_city' => 'Shipping City',
                'shipping_state' => 'Shipping State',
                'shipping_postal_code' => 'Shipping Postal Code',
                'shipping_country' => 'Shipping Country',
                'tax_number' => 'Tax Number',
                'website' => 'Website',
                'notes' => 'Notes',
                'credit_limit' => 'Credit Limit',
                'payment_terms' => 'Payment Terms (days)',
            ],
            self::TYPE_VENDORS => [
                'name' => 'Name *',
                'email' => 'Email',
                'phone' => 'Phone',
                'company_name' => 'Company Name',
                'address' => 'Address',
                'city' => 'City',
                'state' => 'State',
                'postal_code' => 'Postal Code',
                'country' => 'Country',
                'tax_number' => 'Tax Number',
                'website' => 'Website',
                'notes' => 'Notes',
                'payment_terms' => 'Payment Terms (days)',
                'account_number' => 'Account Number',
            ],
            self::TYPE_ITEMS => [
                'name' => 'Name *',
                'type' => 'Type * (product/service)',
                'sku' => 'SKU',
                'description' => 'Description',
                'sale_price' => 'Sale Price',
                'purchase_price' => 'Purchase Price',
                'cost_price' => 'Cost Price',
                'category' => 'Category',
                'unit' => 'Unit of Measure',
                'tax_rate' => 'Tax Rate (%)',
                'is_taxable' => 'Is Taxable (yes/no)',
                'track_inventory' => 'Track Inventory (yes/no)',
                'initial_stock' => 'Initial Stock Quantity',
                'opening_stock_date' => 'Opening Stock Date (default: today)',
                'reorder_level' => 'Reorder Level',
                'sales_account' => 'Sales Account Code',
                'purchase_account' => 'Purchase Account Code',
                'inventory_account' => 'Inventory Account Code',
            ],
            self::TYPE_CHART_OF_ACCOUNTS => [
                'code' => 'Account Code *',
                'name' => 'Account Name *',
                'type' => 'Account Type * (asset/liability/equity/revenue/expense)',
                'description' => 'Description',
                'parent_code' => 'Parent Account Code',
                'is_active' => 'Is Active (yes/no)',
            ],
            self::TYPE_INVOICES => [
                'customer' => 'Customer Name/Email *',
                'invoice_number' => 'Invoice Number *',
                'invoice_date' => 'Invoice Date *',
                'due_date' => 'Due Date',
                'item_name' => 'Item Name',
                'item_description' => 'Item Description',
                'quantity' => 'Quantity',
                'unit_price' => 'Unit Price',
                'tax_rate' => 'Tax Rate (%)',
                'discount' => 'Discount',
                'total' => 'Total *',
                'notes' => 'Notes',
                'status' => 'Status (draft/sent/paid)',
            ],
            self::TYPE_BILLS => [
                'vendor' => 'Vendor Name/Email *',
                'bill_number' => 'Bill Number *',
                'bill_date' => 'Bill Date *',
                'due_date' => 'Due Date',
                'item_name' => 'Item Name',
                'item_description' => 'Item Description',
                'quantity' => 'Quantity',
                'unit_price' => 'Unit Price',
                'tax_rate' => 'Tax Rate (%)',
                'total' => 'Total *',
                'notes' => 'Notes',
                'status' => 'Status (draft/received/paid)',
            ],
            self::TYPE_EXPENSES => [
                'date' => 'Date *',
                'account' => 'Expense Account Code/Name *',
                'amount' => 'Amount *',
                'vendor' => 'Vendor Name',
                'description' => 'Description',
                'reference' => 'Reference Number',
                'payment_method' => 'Payment Method',
                'payment_account' => 'Payment Account Code',
                'is_billable' => 'Is Billable (yes/no)',
                'customer' => 'Bill to Customer',
            ],
            self::TYPE_EMPLOYEES => [
                'name' => 'Name *',
                'email' => 'Email *',
                'employee_id' => 'Employee ID',
                'phone' => 'Phone',
                'address' => 'Address',
                'city' => 'City',
                'state' => 'State',
                'postal_code' => 'Postal Code',
                'date_of_birth' => 'Date of Birth',
                'hire_date' => 'Hire Date',
                'department' => 'Department',
                'designation' => 'Designation/Job Title',
                'salary' => 'Basic Salary',
                'pay_frequency' => 'Pay Frequency (monthly/biweekly/weekly)',
                'bank_name' => 'Bank Name',
                'bank_account' => 'Bank Account Number',
            ],
            self::TYPE_JOURNALS => [
                'date' => 'Date *',
                'reference' => 'Reference *',
                'description' => 'Description',
                'account_code' => 'Account Code',
                'account_name' => 'Account Name',
                'debit' => 'Debit Amount',
                'credit' => 'Credit Amount',
                'memo' => 'Line Memo',
            ],
            self::TYPE_OPENING_BALANCES => [
                'account_code' => 'Account Code *',
                'account_name' => 'Account Name',
                'debit' => 'Debit Balance *',
                'credit' => 'Credit Balance *',
                'as_of_date' => 'As of Date',
            ],
            self::TYPE_BUDGET_LINES => [
                'account_code' => 'Account Code *',
                'account_name' => 'Account Name',
                'jan' => 'January',
                'feb' => 'February',
                'mar' => 'March',
                'apr' => 'April',
                'may' => 'May',
                'jun' => 'June',
                'jul' => 'July',
                'aug' => 'August',
                'sep' => 'September',
                'oct' => 'October',
                'nov' => 'November',
                'dec' => 'December',
                'notes' => 'Notes',
            ],
            default => [],
        };
    }

    /**
     * Get status badge color
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'gray',
            self::STATUS_VALIDATING, self::STATUS_MAPPING => 'blue',
            self::STATUS_PROCESSING => 'yellow',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_FAILED => 'red',
            default => 'gray',
        };
    }

    /**
     * Get status label
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_VALIDATING => 'Validating',
            self::STATUS_MAPPING => 'Mapping Columns',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentage(): int
    {
        if ($this->total_rows === 0) {
            return 0;
        }

        return (int) round(($this->processed_rows / $this->total_rows) * 100);
    }

    /**
     * Check if import is in progress
     */
    public function isInProgress(): bool
    {
        return in_array($this->status, [
            self::STATUS_VALIDATING,
            self::STATUS_MAPPING,
            self::STATUS_PROCESSING,
        ]);
    }

    /**
     * Check if import can be retried
     */
    public function canRetry(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
