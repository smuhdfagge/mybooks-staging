<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Budget extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    // Status constants
    const STATUS_DRAFT = 'draft';

    const STATUS_ACTIVE = 'active';

    const STATUS_LOCKED = 'locked';

    protected $fillable = [
        'tenant_id',
        'name',
        'fiscal_year',
        'status',
        'description',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    /**
     * Get the lines for this budget
     *
     * @return HasMany<BudgetLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }

    /**
     * Get the user who created this budget
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved this budget
     *
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if budget is draft
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if budget is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if budget is locked
     */
    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    /**
     * Check if budget can be edited
     */
    public function canBeEdited(): bool
    {
        return $this->status !== self::STATUS_LOCKED;
    }

    /**
     * Activate the budget
     */
    public function activate(): bool
    {
        if ($this->isLocked()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_ACTIVE,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return true;
    }

    /**
     * Lock the budget (make it read-only)
     */
    public function lock(): bool
    {
        $this->update([
            'status' => self::STATUS_LOCKED,
        ]);

        return true;
    }

    /**
     * Get the total budget amount across all lines
     */
    public function getTotalAttribute(): float
    {
        return $this->lines()->sum('annual_total');
    }

    /**
     * Get available statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_LOCKED => 'Locked',
        ];
    }

    /**
     * Get fiscal years for dropdown (current year +/- 2 years)
     */
    public static function getFiscalYears(): array
    {
        $currentYear = (int) date('Y');
        $years = [];

        for ($i = $currentYear - 2; $i <= $currentYear + 2; $i++) {
            $years[$i] = (string) $i;
        }

        return $years;
    }

    /**
     * Get month columns for budget lines
     */
    public static function getMonthColumns(): array
    {
        return [
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
        ];
    }
}
