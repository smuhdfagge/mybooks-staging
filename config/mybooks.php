<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Support & Contact
    |--------------------------------------------------------------------------
    */

    'support_email' => env('MYBOOKS_SUPPORT_EMAIL', 'support@my-books.cloud'),

    /*
    |--------------------------------------------------------------------------
    | HSTS (finding L10)
    |--------------------------------------------------------------------------
    |
    | Turn these on only if every subdomain of the site's domain is served
    | over HTTPS, permanently. preload also needs includeSubDomains and a
    | submission at hstspreload.org.
    |
    */

    'hsts' => [
        'include_subdomains' => (bool) env('MYBOOKS_HSTS_INCLUDE_SUBDOMAINS', false),
        'preload' => (bool) env('MYBOOKS_HSTS_PRELOAD', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Limits (in kilobytes)
    |--------------------------------------------------------------------------
    */

    'import_max_file_size' => env('MYBOOKS_IMPORT_MAX_FILE_SIZE', 10240), // 10 MB

    /*
    |--------------------------------------------------------------------------
    | Supported Currencies
    |--------------------------------------------------------------------------
    |
    | Central currency registry used by Tenant::getCurrencySymbolAttribute(),
    | API SettingsController::currencies(), and anywhere else that needs the
    | canonical list.
    |
    */

    'currencies' => [
        ['code' => 'NGN', 'name' => 'Nigerian Naira',       'symbol' => '₦'],
        ['code' => 'USD', 'name' => 'US Dollar',            'symbol' => '$'],
        ['code' => 'EUR', 'name' => 'Euro',                 'symbol' => '€'],
        ['code' => 'GBP', 'name' => 'British Pound',        'symbol' => '£'],
        ['code' => 'GHS', 'name' => 'Ghanaian Cedi',        'symbol' => '₵'],
        ['code' => 'KES', 'name' => 'Kenyan Shilling',      'symbol' => 'KSh'],
        ['code' => 'ZAR', 'name' => 'South African Rand',   'symbol' => 'R'],
        ['code' => 'INR', 'name' => 'Indian Rupee',         'symbol' => '₹'],
        ['code' => 'CAD', 'name' => 'Canadian Dollar',       'symbol' => 'C$'],
        ['code' => 'AUD', 'name' => 'Australian Dollar',     'symbol' => 'A$'],
        ['code' => 'JPY', 'name' => 'Japanese Yen',          'symbol' => '¥'],
        ['code' => 'CNY', 'name' => 'Chinese Yuan',          'symbol' => '¥'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tawk.to Live Chat
    |--------------------------------------------------------------------------
    |
    | Set your Tawk.to widget embed URL to enable live chat across the site.
    | Leave empty or set to null to disable. Find your URL at:
    | https://dashboard.tawk.to → Administration → Chat Widget → Direct Chat Link
    |
    */

    'tawk_to_url' => env('TAWK_TO_URL', 'https://embed.tawk.to/69c5485632b9fc1c3eedc710/1jkla551e'),

    /*
    |--------------------------------------------------------------------------
    | Data Retention Policies
    |--------------------------------------------------------------------------
    |
    | Configure retention periods (in months) for terminated employee data.
    | After the retention period, PII is anonymized. Set to 0 to disable.
    | Payroll records are kept for the statutory period regardless.
    |
    */

    'retention' => [
        // Months after termination_date before PII is anonymized
        'terminated_employee_months' => (int) env('MYBOOKS_RETENTION_TERMINATED_MONTHS', 84), // 7 years (tax/legal default)

        // Months to keep activity logs before pruning
        'activity_log_months' => (int) env('MYBOOKS_RETENTION_ACTIVITY_LOG_MONTHS', 84), // 7 years

        // Months to keep soft-deleted records before hard-deleting
        'soft_deleted_months' => (int) env('MYBOOKS_RETENTION_SOFT_DELETED_MONTHS', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Unfinished modules (finding N4)
    |--------------------------------------------------------------------------
    |
    | These modules have back-end code but no screens yet, or their ledger
    | postings are not finished. Their URLs answer 404 until switched on.
    | Switch one on only once its screens exist and its tests pass.
    |
    */

    'features' => [
        'quotations' => (bool) env('MYBOOKS_FEATURE_QUOTATIONS', false),
        'delivery_notes' => (bool) env('MYBOOKS_FEATURE_DELIVERY_NOTES', false),
        'credit_notes' => (bool) env('MYBOOKS_FEATURE_CREDIT_NOTES', false),
        'warehouses' => (bool) env('MYBOOKS_FEATURE_WAREHOUSES', false),
        'stock_transfers' => (bool) env('MYBOOKS_FEATURE_STOCK_TRANSFERS', false),
        'assembly' => (bool) env('MYBOOKS_FEATURE_ASSEMBLY', false),              // bills of materials and assembly orders
        'inventory_valuation' => (bool) env('MYBOOKS_FEATURE_INVENTORY_VALUATION', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue worker from the scheduler (finding N7)
    |--------------------------------------------------------------------------
    |
    | On hosting that can't keep "php artisan queue:work" running, set this
    | to true and the scheduler empties the queue every minute.
    |
    */

    'queue_work_from_scheduler' => (bool) env('QUEUE_WORK_FROM_SCHEDULER', false),

];
