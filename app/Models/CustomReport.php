<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;

class CustomReport extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'created_by',
        'name',
        'description',
        'data_source',
        'columns',
        'filters',
        'group_by',
        'sort_by',
        'aggregations',
        'date_field',
        'is_public',
        'is_favorite',
        'last_run_at',
    ];

    protected $casts = [
        'columns' => 'array',
        'filters' => 'array',
        'sort_by' => 'array',
        'aggregations' => 'array',
        'is_public' => 'boolean',
        'is_favorite' => 'boolean',
        'last_run_at' => 'datetime',
    ];

    /**
     * Data source configurations with available columns and filters
     */
    public static function getDataSources(): array
    {
        return [
            'invoices' => [
                'label' => 'Invoices',
                'model' => Invoice::class,
                'columns' => [
                    'invoice_number' => ['label' => 'Invoice Number', 'type' => 'string'],
                    'customer.name' => ['label' => 'Customer', 'type' => 'string', 'relation' => 'customer'],
                    'invoice_date' => ['label' => 'Invoice Date', 'type' => 'date'],
                    'due_date' => ['label' => 'Due Date', 'type' => 'date'],
                    'status' => ['label' => 'Status', 'type' => 'string'],
                    'subtotal' => ['label' => 'Subtotal', 'type' => 'decimal'],
                    'tax_amount' => ['label' => 'Tax Amount', 'type' => 'decimal'],
                    'discount_amount' => ['label' => 'Discount', 'type' => 'decimal'],
                    'total' => ['label' => 'Total', 'type' => 'decimal'],
                    'amount_paid' => ['label' => 'Amount Paid', 'type' => 'decimal'],
                    'balance_due' => ['label' => 'Balance Due', 'type' => 'decimal'],
                ],
                'date_fields' => ['invoice_date', 'due_date'],
                'group_fields' => ['customer.name', 'status', 'invoice_date'],
            ],
            'bills' => [
                'label' => 'Bills',
                'model' => Bill::class,
                'columns' => [
                    'bill_number' => ['label' => 'Bill Number', 'type' => 'string'],
                    'vendor.name' => ['label' => 'Vendor', 'type' => 'string', 'relation' => 'vendor'],
                    'bill_date' => ['label' => 'Bill Date', 'type' => 'date'],
                    'due_date' => ['label' => 'Due Date', 'type' => 'date'],
                    'status' => ['label' => 'Status', 'type' => 'string'],
                    'subtotal' => ['label' => 'Subtotal', 'type' => 'decimal'],
                    'tax_amount' => ['label' => 'Tax Amount', 'type' => 'decimal'],
                    'total' => ['label' => 'Total', 'type' => 'decimal'],
                    'amount_paid' => ['label' => 'Amount Paid', 'type' => 'decimal'],
                    'balance_due' => ['label' => 'Balance Due', 'type' => 'decimal'],
                ],
                'date_fields' => ['bill_date', 'due_date'],
                'group_fields' => ['vendor.name', 'status', 'bill_date'],
            ],
            'expenses' => [
                'label' => 'Expenses',
                'model' => Expense::class,
                'columns' => [
                    'expense_number' => ['label' => 'Expense #', 'type' => 'string'],
                    'vendor.name' => ['label' => 'Vendor', 'type' => 'string', 'relation' => 'vendor'],
                    'account.name' => ['label' => 'Account', 'type' => 'string', 'relation' => 'account'],
                    'expense_date' => ['label' => 'Date', 'type' => 'date'],
                    'category' => ['label' => 'Category', 'type' => 'string'],
                    'amount' => ['label' => 'Amount', 'type' => 'decimal'],
                    'tax_amount' => ['label' => 'Tax Amount', 'type' => 'decimal'],
                    'description' => ['label' => 'Description', 'type' => 'string'],
                    'payment_method' => ['label' => 'Payment Method', 'type' => 'string'],
                    'is_billable' => ['label' => 'Billable', 'type' => 'boolean'],
                ],
                'date_fields' => ['expense_date'],
                'group_fields' => ['vendor.name', 'account.name', 'category', 'payment_method', 'expense_date'],
            ],
            'customers' => [
                'label' => 'Customers',
                'model' => Customer::class,
                'columns' => [
                    'name' => ['label' => 'Name', 'type' => 'string'],
                    'email' => ['label' => 'Email', 'type' => 'string'],
                    'phone' => ['label' => 'Phone', 'type' => 'string'],
                    'company_name' => ['label' => 'Company', 'type' => 'string'],
                    'billing_address' => ['label' => 'Billing Address', 'type' => 'string'],
                    'city' => ['label' => 'City', 'type' => 'string'],
                    'state' => ['label' => 'State', 'type' => 'string'],
                    'country' => ['label' => 'Country', 'type' => 'string'],
                    'outstanding_balance' => ['label' => 'Outstanding Balance', 'type' => 'decimal'],
                    'total_sales' => ['label' => 'Total Sales', 'type' => 'decimal'],
                    'created_at' => ['label' => 'Created Date', 'type' => 'date'],
                ],
                'date_fields' => ['created_at'],
                'group_fields' => ['city', 'state', 'country'],
            ],
            'vendors' => [
                'label' => 'Vendors',
                'model' => Vendor::class,
                'columns' => [
                    'name' => ['label' => 'Name', 'type' => 'string'],
                    'email' => ['label' => 'Email', 'type' => 'string'],
                    'phone' => ['label' => 'Phone', 'type' => 'string'],
                    'company_name' => ['label' => 'Company', 'type' => 'string'],
                    'address' => ['label' => 'Address', 'type' => 'string'],
                    'city' => ['label' => 'City', 'type' => 'string'],
                    'state' => ['label' => 'State', 'type' => 'string'],
                    'country' => ['label' => 'Country', 'type' => 'string'],
                    'outstanding_balance' => ['label' => 'Outstanding Balance', 'type' => 'decimal'],
                    'created_at' => ['label' => 'Created Date', 'type' => 'date'],
                ],
                'date_fields' => ['created_at'],
                'group_fields' => ['city', 'state', 'country'],
            ],
            'items' => [
                'label' => 'Items/Products',
                'model' => Item::class,
                'columns' => [
                    'name' => ['label' => 'Name', 'type' => 'string'],
                    'sku' => ['label' => 'SKU', 'type' => 'string'],
                    'category.name' => ['label' => 'Category', 'type' => 'string', 'relation' => 'category'],
                    'type' => ['label' => 'Type', 'type' => 'string'],
                    'unit' => ['label' => 'Unit', 'type' => 'string'],
                    'selling_price' => ['label' => 'Selling Price', 'type' => 'decimal'],
                    'cost_price' => ['label' => 'Cost Price', 'type' => 'decimal'],
                    'tax_rate' => ['label' => 'Tax Rate', 'type' => 'decimal'],
                    'is_active' => ['label' => 'Active', 'type' => 'boolean'],
                    'is_taxable' => ['label' => 'Taxable', 'type' => 'boolean'],
                ],
                'date_fields' => ['created_at'],
                'group_fields' => ['category.name', 'type', 'unit'],
            ],
            'payments_received' => [
                'label' => 'Payments Received',
                'model' => PaymentReceived::class,
                'columns' => [
                    'payment_number' => ['label' => 'Payment #', 'type' => 'string'],
                    'invoice.invoice_number' => ['label' => 'Invoice #', 'type' => 'string', 'relation' => 'invoice'],
                    'invoice.customer.name' => ['label' => 'Customer', 'type' => 'string', 'relation' => 'invoice.customer'],
                    'payment_date' => ['label' => 'Payment Date', 'type' => 'date'],
                    'amount' => ['label' => 'Amount', 'type' => 'decimal'],
                    'payment_method' => ['label' => 'Payment Method', 'type' => 'string'],
                    'reference' => ['label' => 'Reference', 'type' => 'string'],
                ],
                'date_fields' => ['payment_date'],
                'group_fields' => ['payment_method', 'payment_date'],
            ],
            'payments_made' => [
                'label' => 'Payments Made',
                'model' => PaymentMade::class,
                'columns' => [
                    'payment_number' => ['label' => 'Payment #', 'type' => 'string'],
                    'bill.bill_number' => ['label' => 'Bill #', 'type' => 'string', 'relation' => 'bill'],
                    'bill.vendor.name' => ['label' => 'Vendor', 'type' => 'string', 'relation' => 'bill.vendor'],
                    'payment_date' => ['label' => 'Payment Date', 'type' => 'date'],
                    'amount' => ['label' => 'Amount', 'type' => 'decimal'],
                    'payment_method' => ['label' => 'Payment Method', 'type' => 'string'],
                    'reference' => ['label' => 'Reference', 'type' => 'string'],
                ],
                'date_fields' => ['payment_date'],
                'group_fields' => ['payment_method', 'payment_date'],
            ],
            'payroll' => [
                'label' => 'Payroll',
                'model' => Payroll::class,
                'columns' => [
                    'employee.full_name' => ['label' => 'Employee', 'type' => 'string', 'relation' => 'employee'],
                    'employee.department.name' => ['label' => 'Department', 'type' => 'string', 'relation' => 'employee.department'],
                    'pay_period_start' => ['label' => 'Period Start', 'type' => 'date'],
                    'pay_period_end' => ['label' => 'Period End', 'type' => 'date'],
                    'pay_date' => ['label' => 'Pay Date', 'type' => 'date'],
                    'basic_salary' => ['label' => 'Basic Salary', 'type' => 'decimal'],
                    'allowances' => ['label' => 'Allowances', 'type' => 'decimal'],
                    'deductions' => ['label' => 'Deductions', 'type' => 'decimal'],
                    'gross_salary' => ['label' => 'Gross Salary', 'type' => 'decimal'],
                    'net_salary' => ['label' => 'Net Salary', 'type' => 'decimal'],
                    'status' => ['label' => 'Status', 'type' => 'string'],
                ],
                'date_fields' => ['pay_date', 'pay_period_start', 'pay_period_end'],
                'group_fields' => ['employee.department.name', 'status', 'pay_date'],
            ],
        ];
    }

    /**
     * Validation rules for saving a report (finding M7): every column,
     * filter, sort, grouping and aggregation must come from the chosen data
     * source's allowed list.
     */
    public static function validationRules(?string $dataSource): array
    {
        $sources = static::getDataSources();
        $source = $sources[$dataSource] ?? ['columns' => [], 'date_fields' => [], 'group_fields' => []];
        $columns = array_keys($source['columns']);

        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'data_source' => ['required', Rule::in(array_keys($sources))],
            'columns' => 'required|array|min:1',
            'columns.*' => ['required', 'string', Rule::in($columns)],
            'filters' => 'nullable|array',
            'filters.*.column' => ['required', 'string', Rule::in($columns)],
            'filters.*.operator' => ['required', Rule::in(array_keys(static::getFilterOperators()))],
            'filters.*.value' => 'nullable',
            'sort_by' => 'nullable|array',
            'sort_by.*.column' => ['required', 'string', Rule::in($columns)],
            'sort_by.*.direction' => ['required', Rule::in(['asc', 'desc'])],
            'group_by' => ['nullable', 'string', Rule::in($source['group_fields'] ?? [])],
            'aggregations' => 'nullable|array',
            'aggregations.*.column' => ['required', 'string', Rule::in($columns)],
            'aggregations.*.function' => ['required', Rule::in(array_keys(static::getAggregations()))],
            'date_field' => ['nullable', 'string', Rule::in($source['date_fields'] ?? [])],
        ];
    }

    /**
     * Get available aggregation functions
     */
    public static function getAggregations(): array
    {
        return [
            'sum' => 'Sum',
            'count' => 'Count',
            'avg' => 'Average',
            'min' => 'Minimum',
            'max' => 'Maximum',
        ];
    }

    /**
     * Get available filter operators
     */
    public static function getFilterOperators(): array
    {
        return [
            'equals' => '=',
            'not_equals' => '≠',
            'greater_than' => '>',
            'less_than' => '<',
            'greater_or_equal' => '≥',
            'less_or_equal' => '≤',
            'contains' => 'Contains',
            'starts_with' => 'Starts with',
            'ends_with' => 'Ends with',
            'is_null' => 'Is empty',
            'is_not_null' => 'Is not empty',
            'between' => 'Between',
            'in' => 'In list',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
