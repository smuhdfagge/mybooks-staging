<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;

class FixedAssetDepreciation extends Model
{
    use HasFactory, BelongsToTenant, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'fixed_asset_id',
        'journal_id',
        'depreciation_date',
        'period_number',
        'depreciation_amount',
        'accumulated_depreciation',
        'book_value',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'depreciation_date' => 'date',
        'period_number' => 'integer',
        'depreciation_amount' => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
        'book_value' => 'decimal:2',
    ];

    // Status Constants
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_POSTED = 'posted';
    const STATUS_REVERSED = 'reversed';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_SCHEDULED => 'Scheduled',
            self::STATUS_POSTED => 'Posted',
            self::STATUS_REVERSED => 'Reversed',
        ];
    }

    public function fixedAsset()
    {
        return $this->belongsTo(FixedAsset::class);
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if depreciation is posted
     */
    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    /**
     * Check if depreciation can be reversed
     */
    public function canReverse(): bool
    {
        if ($this->status !== self::STATUS_POSTED) {
            return false;
        }

        // Can only reverse the latest depreciation
        $latestDepreciation = $this->fixedAsset->depreciations()
            ->where('status', self::STATUS_POSTED)
            ->latest('period_number')
            ->first();

        return $latestDepreciation && $latestDepreciation->id === $this->id;
    }

    /**
     * Scope for posted depreciations
     */
    public function scopePosted($query)
    {
        return $query->where('status', self::STATUS_POSTED);
    }

    /**
     * Scope for scheduled depreciations
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    /**
     * Get period year-month display
     */
    public function getPeriodDisplayAttribute(): string
    {
        return $this->depreciation_date->format('M Y');
    }
}
