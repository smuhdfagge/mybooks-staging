<?php

namespace App\Models;

use App\Services\DepreciationService;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use App\Traits\ValidatesAccountingPeriod;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedAsset extends Model
{
    /** How the asset was paid for (finding A11) => what the purchase journal credits. */
    public const FUNDING_SOURCES = [
        'bank' => 'Paid from the bank',
        'cash' => 'Paid in cash',
        'on_account' => 'Owed to the vendor (no bill in MyBooks)',
        'bill' => 'On a vendor bill already entered in MyBooks',
        'opening_balance' => 'Already owned (opening balance)',
    ];

    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes, ValidatesAccountingPeriod;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'asset_number',
        'name',
        'description',
        'serial_number',
        'model',
        'manufacturer',
        'location',
        'vendor_id',
        'purchase_invoice',
        'purchase_date',
        'in_service_date',
        'purchase_cost',
        'funding_source',
        'salvage_value',
        'depreciable_amount',
        'useful_life',
        'depreciation_method',
        'depreciation_rate',
        'accumulated_depreciation',
        'book_value',
        'status',
        'disposal_date',
        'disposal_amount',
        'disposal_method',
        'disposal_notes',
        'gain_loss_on_disposal',
        'notes',
        'custom_fields',
        'assigned_to',
        'created_by',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'in_service_date' => 'date',
        'disposal_date' => 'date',
        'purchase_cost' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'depreciable_amount' => 'decimal:2',
        'depreciation_rate' => 'decimal:4',
        'accumulated_depreciation' => 'decimal:2',
        'book_value' => 'decimal:2',
        'disposal_amount' => 'decimal:2',
        'gain_loss_on_disposal' => 'decimal:2',
        'custom_fields' => 'array',
        'useful_life' => 'decimal:2',
    ];

    // Status Constants
    const STATUS_ACTIVE = 'active';

    const STATUS_UNDER_MAINTENANCE = 'under_maintenance';

    const STATUS_IDLE = 'idle';

    const STATUS_FULLY_DEPRECIATED = 'fully_depreciated';

    const STATUS_DISPOSED = 'disposed';

    const STATUS_SOLD = 'sold';

    // Depreciation Methods
    const METHOD_STRAIGHT_LINE = 'straight_line';

    const METHOD_DECLINING_BALANCE = 'declining_balance';

    const METHOD_DOUBLE_DECLINING = 'double_declining';

    const METHOD_SUM_OF_YEARS = 'sum_of_years';

    // Disposal Methods
    const DISPOSAL_SALE = 'sale';

    const DISPOSAL_SCRAPPED = 'scrapped';

    const DISPOSAL_DONATED = 'donated';

    const DISPOSAL_LOST = 'lost';

    const DISPOSAL_OTHER = 'other';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_UNDER_MAINTENANCE => 'Under Maintenance',
            self::STATUS_IDLE => 'Idle',
            self::STATUS_FULLY_DEPRECIATED => 'Fully Depreciated',
            self::STATUS_DISPOSED => 'Disposed',
            self::STATUS_SOLD => 'Sold',
        ];
    }

    public static function getDepreciationMethods(): array
    {
        return [
            self::METHOD_STRAIGHT_LINE => 'Straight Line',
            self::METHOD_DECLINING_BALANCE => 'Declining Balance',
            self::METHOD_DOUBLE_DECLINING => 'Double Declining Balance',
            self::METHOD_SUM_OF_YEARS => 'Sum of Years Digits',
        ];
    }

    public static function getDisposalMethods(): array
    {
        return [
            self::DISPOSAL_SALE => 'Sale',
            self::DISPOSAL_SCRAPPED => 'Scrapped',
            self::DISPOSAL_DONATED => 'Donated',
            self::DISPOSAL_LOST => 'Lost/Stolen',
            self::DISPOSAL_OTHER => 'Other',
        ];
    }

    /** @return BelongsTo<FixedAssetCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FixedAssetCategory::class, 'category_id');
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return HasMany<FixedAssetDepreciation, $this> */
    public function depreciations(): HasMany
    {
        return $this->hasMany(FixedAssetDepreciation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateNumber($tenantId): string
    {
        $lastAsset = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();

        $number = $lastAsset ? intval(substr($lastAsset->asset_number, 3)) + 1 : 1;

        return 'FA-'.str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate depreciable amount
     */
    public function calculateDepreciableAmount(): float
    {
        return $this->purchase_cost - $this->salvage_value;
    }

    /**
     * Get useful life in months
     */
    public function getUsefulLifeMonthsAttribute(): int
    {
        return (int) ($this->useful_life * 12);
    }

    /**
     * Get remaining useful life in months
     */
    public function getRemainingUsefulLifeMonthsAttribute(): int
    {
        if (! $this->in_service_date) {
            return $this->useful_life_months;
        }

        $monthsInService = $this->in_service_date->diffInMonths(now());

        return max(0, $this->useful_life_months - $monthsInService);
    }

    /**
     * Get depreciation percentage
     */
    public function getDepreciationPercentageAttribute(): float
    {
        if ($this->depreciable_amount <= 0) {
            return 100;
        }

        return min(100, ($this->accumulated_depreciation / $this->depreciable_amount) * 100);
    }

    /**
     * Check if asset is fully depreciated
     */
    public function isFullyDepreciated(): bool
    {
        return $this->book_value <= $this->salvage_value || $this->status === self::STATUS_FULLY_DEPRECIATED;
    }

    /**
     * Check if asset can be depreciated
     */
    public function canDepreciate(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->isFullyDepreciated();
    }

    /**
     * Check if asset can be disposed
     */
    public function canDispose(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_FULLY_DEPRECIATED]);
    }

    /**
     * Get the next depreciation date
     */
    public function getNextDepreciationDateAttribute(): ?Carbon
    {
        if (! $this->canDepreciate()) {
            return null;
        }

        $lastDepreciation = $this->depreciations()->latest('depreciation_date')->first();

        if ($lastDepreciation) {
            return $lastDepreciation->depreciation_date->copy()->addMonth()->endOfMonth();
        }

        return $this->in_service_date->copy()->endOfMonth();
    }

    /**
     * Calculate monthly depreciation amount
     */
    public function calculateMonthlyDepreciation(int $periodNumber = 1): float
    {
        $service = app(DepreciationService::class);

        return $service->calculateMonthlyDepreciation($this, $periodNumber);
    }

    /**
     * Generate depreciation schedule
     */
    public function generateDepreciationSchedule(): array
    {
        $service = app(DepreciationService::class);

        return $service->generateSchedule($this);
    }

    /**
     * Record depreciation for a period
     */
    public function recordDepreciation(Carbon $depreciationDate, ?string $notes = null): ?FixedAssetDepreciation
    {
        $service = app(DepreciationService::class);

        return $service->recordDepreciation($this, $depreciationDate, $notes);
    }

    /**
     * Dispose the asset
     */
    public function dispose(string $method, ?float $amount = null, ?Carbon $date = null, ?string $notes = null): bool
    {
        $service = app(DepreciationService::class);

        return $service->disposeAsset($this, $method, $amount, $date, $notes);
    }

    /**
     * Scope for active assets
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for depreciable assets
     */
    public function scopeDepreciable($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereColumn('book_value', '>', 'salvage_value');
    }

    protected static function booted()
    {
        static::creating(function ($asset) {
            if (empty($asset->asset_number)) {
                $asset->asset_number = static::generateNumber($asset->tenant_id);
            }

            // Calculate depreciable amount
            $asset->depreciable_amount = $asset->purchase_cost - $asset->salvage_value;

            // Set initial book value
            if (empty($asset->book_value)) {
                $asset->book_value = $asset->purchase_cost;
            }

            // Set default depreciation rate for declining balance methods
            if (in_array($asset->depreciation_method, [self::METHOD_DECLINING_BALANCE, self::METHOD_DOUBLE_DECLINING])) {
                if (empty($asset->depreciation_rate)) {
                    $yearsLife = $asset->useful_life;
                    $asset->depreciation_rate = $asset->depreciation_method === self::METHOD_DOUBLE_DECLINING
                        ? (2 / $yearsLife) * 100
                        : (1 / $yearsLife) * 100;
                }
            }
        });

        static::updating(function ($asset) {
            // Recalculate depreciable amount if cost or salvage value changed
            if ($asset->isDirty(['purchase_cost', 'salvage_value'])) {
                $asset->depreciable_amount = $asset->purchase_cost - $asset->salvage_value;
            }
        });
    }
}
