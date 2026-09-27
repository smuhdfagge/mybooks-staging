<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;

class FixedAssetCategory extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'default_useful_life',
        'default_depreciation_method',
        'asset_account_id',
        'accumulated_depreciation_account_id',
        'depreciation_expense_account_id',
        'gain_loss_account_id',
    ];

    protected $casts = [
        'default_useful_life' => 'decimal:2',
    ];

    // Depreciation Methods
    const METHOD_STRAIGHT_LINE = 'straight_line';
    const METHOD_DECLINING_BALANCE = 'declining_balance';
    const METHOD_DOUBLE_DECLINING = 'double_declining';
    const METHOD_SUM_OF_YEARS = 'sum_of_years';

    public static function getDepreciationMethods(): array
    {
        return [
            self::METHOD_STRAIGHT_LINE => 'Straight Line',
            self::METHOD_DECLINING_BALANCE => 'Declining Balance',
            self::METHOD_DOUBLE_DECLINING => 'Double Declining Balance',
            self::METHOD_SUM_OF_YEARS => 'Sum of Years Digits',
        ];
    }

    public function assets()
    {
        return $this->hasMany(FixedAsset::class, 'category_id');
    }

    public function assetAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'asset_account_id');
    }

    public function accumulatedDepreciationAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'accumulated_depreciation_account_id');
    }

    public function depreciationExpenseAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'depreciation_expense_account_id');
    }

    public function gainLossAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'gain_loss_account_id');
    }

    public function getActiveAssetsCountAttribute(): int
    {
        return $this->assets()->where('status', 'active')->count();
    }

    public function getTotalAssetValueAttribute(): float
    {
        return $this->assets()->where('status', 'active')->sum('book_value');
    }
}
