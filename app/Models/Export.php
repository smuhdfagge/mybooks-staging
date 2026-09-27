<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Export extends Model
{
    use HasFactory, BelongsToTenant;

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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tenant()
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
     * Get available formats with labels
     */
    public static function getFormats(): array
    {
        return [
            self::FORMAT_CSV => 'CSV (Comma Separated Values)',
            self::FORMAT_XLSX => 'Excel Spreadsheet',
            self::FORMAT_JSON => 'JSON (JavaScript Object Notation)',
            self::FORMAT_PDF => 'PDF Document',
        ];
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
            && \Illuminate\Support\Facades\Storage::disk('exports')->exists($this->file_path);
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
        if (!$this->file_size) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            self::STATUS_PROCESSING => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
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
