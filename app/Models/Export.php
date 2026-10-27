<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class Export extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'type',
        'format',
        'status',
        'filename',
        'file_path',
        'file_size',
        'options',
        'included_data',
        'error_message',
        'started_at',
        'completed_at',
        'expires_at',
    ];

    protected $casts = [
        'options' => 'array',
        'included_data' => 'array',
        'file_size' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';

    const STATUS_PROCESSING = 'processing';

    const STATUS_COMPLETED = 'completed';

    const STATUS_FAILED = 'failed';

    // Type constants
    const TYPE_FULL_BACKUP = 'full_backup';

    const TYPE_CUSTOMERS = 'customers';

    const TYPE_VENDORS = 'vendors';

    const TYPE_ITEMS = 'items';

    const TYPE_INVOICES = 'invoices';

    const TYPE_BILLS = 'bills';

    const TYPE_EXPENSES = 'expenses';

    const TYPE_EMPLOYEES = 'employees';

    const TYPE_PAYROLL = 'payroll';

    const TYPE_JOURNALS = 'journals';

    const TYPE_CHART_OF_ACCOUNTS = 'chart_of_accounts';

    const TYPE_ACTIVITY_LOGS = 'activity_logs';

    // Format constants
    const FORMAT_CSV = 'csv';

    const FORMAT_XLSX = 'xlsx';

    const FORMAT_PDF = 'pdf';

    const FORMAT_JSON = 'json';

    const FORMAT_ZIP = 'zip';

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
     * Get available export types with labels
     */
    public static function getExportTypes(): array
    {
        return [
            self::TYPE_FULL_BACKUP => 'Full Backup (All Data)',
            self::TYPE_CUSTOMERS => 'Customers',
            self::TYPE_VENDORS => 'Vendors',
            self::TYPE_ITEMS => 'Items & Inventory',
            self::TYPE_INVOICES => 'Invoices',
            self::TYPE_BILLS => 'Bills',
            self::TYPE_EXPENSES => 'Expenses',
            self::TYPE_EMPLOYEES => 'Employees',
            self::TYPE_PAYROLL => 'Payroll Records',
            self::TYPE_JOURNALS => 'Journal Entries',
            self::TYPE_CHART_OF_ACCOUNTS => 'Chart of Accounts',
            self::TYPE_ACTIVITY_LOGS => 'Activity Logs',
        ];
    }

    /**
     * The permission each kind of record needs to be exported (audit MB-06).
     * "export reports" only opens the Exports screen; without this map it
     * let anyone with it download employees (with salaries), payroll, the
     * activity log or a full backup.
     */
    public const PERMISSIONS = [
        self::TYPE_CUSTOMERS => 'view customers',
        self::TYPE_VENDORS => 'view vendors',
        self::TYPE_ITEMS => 'view items',
        self::TYPE_INVOICES => 'view invoices',
        self::TYPE_BILLS => 'view bills',
        self::TYPE_EXPENSES => 'view expenses',
        self::TYPE_EMPLOYEES => 'view employees',
        self::TYPE_PAYROLL => 'view payroll',
        self::TYPE_JOURNALS => 'view journals',
        self::TYPE_CHART_OF_ACCOUNTS => 'view chart-of-accounts',
        self::TYPE_ACTIVITY_LOGS => 'view activity-logs',
    ];

    /** A full backup also needs this, on top of a permission for each part. */
    public const BACKUP_PERMISSION = 'export data';

    /**
     * Every permission an export of $type needs. A full backup needs each
     * included part's permission; with nothing chosen it includes them all.
     *
     * @param  array<int, string>|null  $included
     * @return array<int, string>
     */
    public static function permissionsFor(string $type, ?array $included = null): array
    {
        if ($type === self::TYPE_FULL_BACKUP) {
            $parts = $included ?: array_keys(self::PERMISSIONS);

            return array_values(array_unique([self::BACKUP_PERMISSION, ...array_map(
                fn ($part) => self::PERMISSIONS[$part] ?? self::BACKUP_PERMISSION.' '.$part, // unknown part: a permission nobody holds
                $parts,
            )]));
        }

        return [self::PERMISSIONS[$type] ?? self::BACKUP_PERMISSION.' '.$type];
    }

    /** @param  array<int, string>|null  $included */
    public static function allowedFor(User $user, string $type, ?array $included = null): bool
    {
        foreach (self::permissionsFor($type, $included) as $permission) {
            if (! $user->can($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The export types $user may choose. Full backup is listed when they
     * hold "export data"; the parts they may include are checked separately.
     *
     * @return array<string, string>
     */
    public static function getExportTypesFor(User $user): array
    {
        return array_filter(
            self::getExportTypes(),
            fn ($type) => $type === self::TYPE_FULL_BACKUP ? $user->can(self::BACKUP_PERMISSION) : self::allowedFor($user, $type),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /** Whether $user may see, download or delete this export. */
    public function isAllowedFor(User $user): bool
    {
        return $this->tenant_id === $user->tenant_id && self::allowedFor($user, $this->type, $this->included_data);
    }

    /**
     * Excel files need the phpoffice/phpspreadsheet package, the same as
     * importing them does. Without it the export quietly fell back to
     * CSV: the file was named .csv and was perfectly good, but somebody
     * who chose Excel was handed something else and never told. Offered
     * only when it can actually be produced.
     */
    public static function excelSupported(): bool
    {
        return class_exists(Spreadsheet::class);
    }

    /**
     * Get available formats with labels
     */
    public static function getFormats(): array
    {
        $formats = [
            self::FORMAT_CSV => 'CSV (Comma Separated Values)',
            self::FORMAT_XLSX => 'Excel Spreadsheet',
            self::FORMAT_JSON => 'JSON (JavaScript Object Notation)',
            self::FORMAT_PDF => 'PDF Document',
        ];

        if (! self::excelSupported()) {
            unset($formats[self::FORMAT_XLSX]);
        }

        return $formats;
    }

    /**
     * Get formats available for full backup
     */
    public static function getBackupFormats(): array
    {
        return [
            self::FORMAT_ZIP => 'ZIP Archive (All formats)',
            self::FORMAT_JSON => 'JSON (Single file)',
        ];
    }

    /**
     * Check if export is downloadable
     */
    public function isDownloadable(): bool
    {
        return $this->status === self::STATUS_COMPLETED
            && $this->file_path
            && Storage::disk('exports')->exists($this->file_path);
    }

    /**
     * Check if export has expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Get human-readable file size
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (! $this->file_size) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2).' '.$units[$unit];
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            self::STATUS_PROCESSING => 'bg-brand-100 text-brand-800 dark:bg-brand-900 dark:text-brand-200',
            self::STATUS_COMPLETED => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            self::STATUS_FAILED => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
        };
    }

    /**
     * Scope for pending exports
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for completed exports
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for non-expired exports
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }
}
