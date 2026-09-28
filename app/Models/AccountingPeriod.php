<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Carbon\Carbon;

class AccountingPeriod extends Model
{
    use HasFactory, BelongsToTenant, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'name',
        'start_date',
        'end_date',
        'status',
        'closed_at',
        'closed_by',
        'closing_notes',
        'is_year_end',
        'fiscal_year',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
        'is_year_end' => 'boolean',
    ];

    // Period statuses
    const STATUS_OPEN = 'open';
    const STATUS_CLOSED = 'closed';
    const STATUS_LOCKED = 'locked'; // Permanently locked (year-end)

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Check if the period is open for transactions
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Check if the period is closed
     */
    public function isClosed(): bool
    {
        return in_array($this->status, [self::STATUS_CLOSED, self::STATUS_LOCKED]);
    }

    /**
     * Check if the period is permanently locked
     */
    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    /**
     * Close the period
     */
    public function close(?string $notes = null): bool
    {
        if ($this->isClosed()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by' => auth()->id(),
            'closing_notes' => $notes,
        ]);

        return true;
    }

    /**
     * Reopen the period (only if not permanently locked)
     */
    public function reopen(): bool
    {
        if ($this->isLocked()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_OPEN,
            'closed_at' => null,
            'closed_by' => null,
        ]);

        return true;
    }

    /**
     * Permanently lock the period (year-end closing)
     */
    public function lock(?string $notes = null): bool
    {
        $this->update([
            'status' => self::STATUS_LOCKED,
            'closed_at' => now(),
            'closed_by' => auth()->id(),
            'closing_notes' => $notes,
            'is_year_end' => true,
        ]);

        return true;
    }

    /**
     * Check if a date falls within this period
     */
    public function containsDate($date): bool
    {
        $date = Carbon::parse($date);
        return $date->between($this->start_date, $this->end_date);
    }

    /**
     * Get the period for a specific date
     */
    public static function getPeriodForDate($date, $tenantId = null): ?self
    {
        $tenantId = $tenantId ?? auth()->user()->tenant_id;
        $date = Carbon::parse($date);

        return static::where('tenant_id', $tenantId)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }

    /**
     * Check if a date is in a closed period
     */
    public static function isDateInClosedPeriod($date, $tenantId = null): bool
    {
        $period = static::getPeriodForDate($date, $tenantId);
        return $period ? $period->isClosed() : false;
    }

    /**
     * Check if a date is allowed for transactions (not in closed period)
     */
    public static function isDateAllowed($date, $tenantId = null): bool
    {
        return !static::isDateInClosedPeriod($date, $tenantId);
    }

    /**
     * Get the current open period
     */
    public static function getCurrentOpenPeriod($tenantId = null): ?self
    {
        $tenantId = $tenantId ?? auth()->user()->tenant_id;

        return static::where('tenant_id', $tenantId)
            ->where('status', self::STATUS_OPEN)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();
    }

    /**
     * Generate monthly periods for a fiscal year
     */
    public static function generateMonthlyPeriods(int $tenantId, int $year, int $startMonth = 1): array
    {
        $periods = [];
        
        for ($month = 0; $month < 12; $month++) {
            $currentMonth = (($startMonth - 1 + $month) % 12) + 1;
            $currentYear = $year + floor(($startMonth - 1 + $month) / 12);
            
            $startDate = Carbon::createFromDate($currentYear, $currentMonth, 1);
            $endDate = $startDate->copy()->endOfMonth();
            
            $period = static::create([
                'tenant_id' => $tenantId,
                'name' => $startDate->format('F Y'),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => self::STATUS_OPEN,
                'fiscal_year' => $year,
                'is_year_end' => $month === 11,
            ]);
            
            $periods[] = $period;
        }
        
        return $periods;
    }

    /**
     * Get all periods for a fiscal year
     */
    public static function getPeriodsForYear(int $year, $tenantId = null)
    {
        $tenantId = $tenantId ?? auth()->user()->tenant_id;
        
        return static::where('tenant_id', $tenantId)
            ->where('fiscal_year', $year)
            ->orderBy('start_date')
            ->get();
    }

    /**
     * Get validation error message for closed period
     */
    public static function getClosedPeriodMessage($date): string
    {
        $period = static::getPeriodForDate($date);
        if ($period) {
            return "The date falls within a closed accounting period ({$period->name}). Transactions cannot be created or modified in closed periods.";
        }
        return "The date falls within a closed accounting period.";
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_OPEN => 'Open',
            self::STATUS_CLOSED => 'Closed',
            self::STATUS_LOCKED => 'Locked',
        ];
    }
}
