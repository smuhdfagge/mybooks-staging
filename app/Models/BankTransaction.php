<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\ValidatesAccountingPeriod;

class BankTransaction extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, ValidatesAccountingPeriod;

    protected $fillable = [
        'tenant_id',
        'bank_id',
        'type',
        'date',
        'amount',
        'balance_after',
        'reference',
        'payee',
        'description',
        'category',
        'is_reconciled',
        'reconciled_date',
        'reconciled_by',
        'transactionable_type',
        'transactionable_id',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'is_reconciled' => 'boolean',
        'reconciled_date' => 'date',
    ];

    // Transaction Types
    const TYPE_DEPOSIT = 'deposit';
    const TYPE_WITHDRAWAL = 'withdrawal';
    const TYPE_TRANSFER_IN = 'transfer_in';
    const TYPE_TRANSFER_OUT = 'transfer_out';
    const TYPE_FEE = 'fee';
    const TYPE_INTEREST = 'interest';

    public static function getTypes(): array
    {
        return [
            self::TYPE_DEPOSIT => 'Deposit',
            self::TYPE_WITHDRAWAL => 'Withdrawal',
            self::TYPE_TRANSFER_IN => 'Transfer In',
            self::TYPE_TRANSFER_OUT => 'Transfer Out',
            self::TYPE_FEE => 'Bank Fee',
            self::TYPE_INTEREST => 'Interest',
        ];
    }

    /** @return BelongsTo<Bank, $this> */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function transactionable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function getFormattedAmountAttribute(): string
    {
        $prefix = in_array($this->type, [self::TYPE_DEPOSIT, self::TYPE_TRANSFER_IN, self::TYPE_INTEREST]) ? '+' : '-';
        return $prefix . number_format($this->amount, 2);
    }

    public function isInflow(): bool
    {
        return in_array($this->type, [self::TYPE_DEPOSIT, self::TYPE_TRANSFER_IN, self::TYPE_INTEREST]);
    }

    public function isOutflow(): bool
    {
        return in_array($this->type, [self::TYPE_WITHDRAWAL, self::TYPE_TRANSFER_OUT, self::TYPE_FEE]);
    }
}
