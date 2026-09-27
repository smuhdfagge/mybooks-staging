<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;

class Bank extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'chart_of_account_id',
        'name',
        'bank_name',
        'account_number',
        'account_type',
        'currency',
        'routing_number',
        'swift_code',
        'iban',
        'branch_name',
        'branch_address',
        'opening_balance',
        'current_balance',
        'opening_balance_date',
        'description',
        'is_primary',
        'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'opening_balance_date' => 'date',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'account_number' => 'encrypted',
        'routing_number' => 'encrypted',
        'swift_code' => 'encrypted',
        'iban' => 'encrypted',
    ];

    // Account Types
    const TYPE_CHECKING = 'checking';
    const TYPE_SAVINGS = 'savings';
    const TYPE_CREDIT_CARD = 'credit_card';
    const TYPE_CASH = 'cash';
    const TYPE_OTHER = 'other';

    public static function getAccountTypes(): array
    {
        return [
            self::TYPE_CHECKING => 'Checking Account',
            self::TYPE_SAVINGS => 'Savings Account',
            self::TYPE_CREDIT_CARD => 'Credit Card',
            self::TYPE_CASH => 'Cash',
            self::TYPE_OTHER => 'Other',
        ];
    }

    public function chartOfAccount()
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    public function transactions()
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function getMaskedAccountNumberAttribute(): string
    {
        if (empty($this->account_number)) {
            return '';
        }
        
        $length = strlen($this->account_number);
        if ($length <= 4) {
            return $this->account_number;
        }
        
        return str_repeat('•', $length - 4) . substr($this->account_number, -4);
    }

    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->current_balance, 2);
    }

    public function updateBalance(): void
    {
        $deposits = $this->transactions()
            ->whereIn('type', ['deposit', 'interest'])
            ->sum('amount');
            
        $withdrawals = $this->transactions()
            ->whereIn('type', ['withdrawal', 'fee', 'transfer_out'])
            ->sum('amount');
            
        $this->current_balance = $this->opening_balance + $deposits - $withdrawals;
        $this->save();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }
}
