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
    | Sub-processors (finding O7)
    |--------------------------------------------------------------------------
    |
    | Companies that process personal data for MyBooks, listed on the privacy
    | page as the Nigeria Data Protection Act expects. Keep this up to date
    | when a provider changes (for example the email or hosting provider).
    |
    */

    'sub_processors' => [
        ['name' => 'Paystack Payments Ltd', 'purpose' => 'Subscription payments (card and bank details are handled by Paystack, not stored by MyBooks)', 'location' => 'Nigeria'],
        ['name' => 'Email delivery provider (SMTP)', 'purpose' => 'Sending invoices, reminders, password resets and other emails', 'location' => 'Varies by provider'],
        ['name' => 'Termii', 'purpose' => 'Sending SMS and WhatsApp invoice reminders and receipts to a business\'s customers (phone number and message text)', 'location' => 'Nigeria'],
        ['name' => 'Meta Platforms (WhatsApp)', 'purpose' => 'Delivering WhatsApp messages to customers who use WhatsApp', 'location' => 'United States / Ireland'],
        ['name' => 'Tawk.to Inc.', 'purpose' => 'Live chat support on the website and app', 'location' => 'United States'],
        ['name' => 'Hosting provider', 'purpose' => 'Servers, database and file storage, and backups', 'location' => 'Varies by provider'],
    ],

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
    | Optional modules. A module switched off answers 404. The ones finished
    | in Phase F are on by default and can still be switched off here or in
    | .env; inventory valuation is not finished yet.
    |
    */

    'features' => [
        'quotations' => (bool) env('MYBOOKS_FEATURE_QUOTATIONS', true),
        'delivery_notes' => (bool) env('MYBOOKS_FEATURE_DELIVERY_NOTES', true),
        'credit_notes' => (bool) env('MYBOOKS_FEATURE_CREDIT_NOTES', true),
        'warehouses' => (bool) env('MYBOOKS_FEATURE_WAREHOUSES', true),
        // Moving stock between warehouses (session 13); needs warehouses on.
        'stock_transfers' => (bool) env('MYBOOKS_FEATURE_STOCK_TRANSFERS', true),
        'assembly' => (bool) env('MYBOOKS_FEATURE_ASSEMBLY', true),              // bills of materials and assembly orders
        'inventory_valuation' => (bool) env('MYBOOKS_FEATURE_INVENTORY_VALUATION', false),
        // "Reverse on" date on manual journals (accruals), posted by journals:post-reversals (S8).
        // Off hides the field and stops all automatic reversals, including pending ones.
        'auto_reversing_journals' => (bool) env('MYBOOKS_FEATURE_AUTO_REVERSING_JOURNALS', true),
        // Prepaid expense and deferred revenue schedules, released monthly by accruals:release (S9).
        'prepaid_schedules' => (bool) env('MYBOOKS_FEATURE_PREPAID_SCHEDULES', true),
        // Customer and supplier statements (PDF, email, bulk email) and the
        // receivables / payables control account reconciliation (session 10).
        'statements' => (bool) env('MYBOOKS_FEATURE_STATEMENTS', true),
        // Staff and all-users lock dates on the accounting periods page, with
        // their history (session 11). Off stops the lock dates being checked.
        'lock_dates' => (bool) env('MYBOOKS_FEATURE_LOCK_DATES', true),
        // Saved card and automatic renewal of the MyBooks subscription through
        // Paystack (session 15). Off: no cards are saved and nothing is charged.
        'auto_renewal' => (bool) env('MYBOOKS_FEATURE_AUTO_RENEWAL', true),
        // SMS and WhatsApp reminders and receipts to customers (session 16).
        // Sends nothing real until the Termii / WhatsApp keys are set.
        'sms_whatsapp' => (bool) env('MYBOOKS_FEATURE_SMS_WHATSAPP', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS and WhatsApp messages to customers (session 16)
    |--------------------------------------------------------------------------
    |
    | Drivers: 'termii', 'meta' (WhatsApp only), 'log' (writes the message to
    | the log, sends nothing) or 'auto' (the provider whose keys are set,
    | else 'log'). Each plan has a monthly allowance (plans table); a long SMS
    | counts once per 160-character page.
    |
    | Reminders and invoice messages wait for 07:00 Lagos time if they would
    | go out between 21:00 and 07:00; payment receipts go at once.
    |
    | WhatsApp messages to customers who haven't written first must use
    | templates approved by Meta (through Termii or Meta's WhatsApp Manager).
    | Each template gets these values, in this order (see the README for the
    | wording to submit).
    |
    */

    'messaging' => [
        'sms_driver' => env('MESSAGING_SMS_DRIVER', 'auto'),
        'whatsapp_driver' => env('MESSAGING_WHATSAPP_DRIVER', 'auto'),
        // Secret part of the webhook URLs given to Termii / Meta.
        'webhook_token' => env('MESSAGING_WEBHOOK_TOKEN'),
        'timezone' => 'Africa/Lagos',
        'quiet_from' => (int) env('MESSAGING_QUIET_FROM', 21),
        'quiet_until' => (int) env('MESSAGING_QUIET_UNTIL', 7),
        // A failed send is tried this many times in all (1 + 2 retries).
        'max_attempts' => 3,
        'retry_minutes' => [1, 5],
        // Overdue reminders: day 1 after the due date, then every N days
        // (the "Remind every" setting), at most this many per invoice.
        'max_overdue_reminders' => (int) env('MESSAGING_MAX_OVERDUE_REMINDERS', 4),
        'whatsapp_templates' => [
            'invoice_sent' => ['name' => env('WHATSAPP_TEMPLATE_INVOICE_SENT', 'mybooks_invoice_sent'), 'params' => ['customer', 'business', 'invoice', 'amount', 'due_date']],
            'payment_reminder' => ['name' => env('WHATSAPP_TEMPLATE_PAYMENT_REMINDER', 'mybooks_payment_reminder'), 'params' => ['customer', 'business', 'invoice', 'amount', 'due_date']],
            'overdue' => ['name' => env('WHATSAPP_TEMPLATE_OVERDUE', 'mybooks_invoice_overdue'), 'params' => ['customer', 'business', 'invoice', 'amount', 'due_date']],
            'payment_received' => ['name' => env('WHATSAPP_TEMPLATE_PAYMENT_RECEIVED', 'mybooks_payment_received'), 'params' => ['customer', 'business', 'amount', 'invoice', 'balance']],
            'test' => ['name' => env('WHATSAPP_TEMPLATE_TEST', 'mybooks_test_message'), 'params' => ['business']],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Subscription billing: automatic renewal (session 15)
    |--------------------------------------------------------------------------
    |
    | "php artisan subscriptions:auto-renew" runs daily at 06:00. It charges
    | the saved card of each business with auto-renewal on whose subscription
    | ends within charge_days_before days (1 = the day before the end date).
    | A failed charge is retried retry_days after the first attempt; after
    | the last retry the normal expiry flow takes over.
    |
    */

    'billing' => [
        'charge_days_before' => max(0, (int) env('MYBOOKS_AUTO_RENEW_DAYS_BEFORE', 1)),
        'retry_days' => array_values(array_filter(array_map('intval', explode(',', (string) env('MYBOOKS_AUTO_RENEW_RETRY_DAYS', '1,3'))), fn ($d) => $d > 0)),
        // Email a warning this many days before the saved card's expiry month ends.
        'card_expiry_warning_days' => max(1, (int) env('MYBOOKS_CARD_EXPIRY_WARNING_DAYS', 7)),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue worker from the scheduler (finding N7)
    |--------------------------------------------------------------------------
    |
    | The scheduler empties the queue every minute, so emails, payroll
    | batches, imports and exports go out on hosting that can't keep
    | "php artisan queue:work" running (most shared hosting). On by default
    | since round 3 (finding R5): with it off and no worker, queued emails
    | never left. If Supervisor runs a worker, it can be switched off, but
    | leaving it on does no harm.
    |
    */

    'queue_work_from_scheduler' => (bool) env('QUEUE_WORK_FROM_SCHEDULER', true),

    /*
    |--------------------------------------------------------------------------
    | Backups (finding O1)
    |--------------------------------------------------------------------------
    |
    | "php artisan mybooks:backup" runs daily at 01:30 from the scheduler. It
    | writes one zip (database dump + uploaded files) to every disk listed in
    | BACKUP_DISKS. "backups" is a folder on this server (storage/app/backups):
    | add an off-site disk too, or a server failure loses the backups with
    | the data. BACKUP_ARCHIVE_PASSWORD encrypts the zip (AES-256); keep the
    | password somewhere other than this server.
    |
    */

    'backup' => [
        'disks' => explode(',', (string) env('BACKUP_DISKS', 'backups')),
        'keep_days' => (int) env('BACKUP_KEEP_DAYS', 30),
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),
        'notify' => env('BACKUP_NOTIFY_EMAIL', env('MYBOOKS_SUPPORT_EMAIL')),
        'mysqldump' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
        'enabled' => (bool) env('BACKUP_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Error alerts (finding O2)
    |--------------------------------------------------------------------------
    |
    | Server errors are emailed to ERROR_ALERT_EMAIL: each distinct error at
    | most once an hour, and at most ERROR_ALERT_MAX_PER_HOUR in total. On
    | outside production unless ERROR_ALERTS_ENABLED says otherwise.
    |
    */

    'error_alerts' => [
        'email' => env('ERROR_ALERT_EMAIL', env('MYBOOKS_SUPPORT_EMAIL')),
        'enabled' => (bool) env('ERROR_ALERTS_ENABLED', env('APP_ENV') === 'production'),
        'max_per_hour' => (int) env('ERROR_ALERT_MAX_PER_HOUR', 20),
    ],

];
