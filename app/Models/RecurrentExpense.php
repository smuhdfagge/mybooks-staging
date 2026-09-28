<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurrentExpense extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'vendor_id',
        'expense_account_id',
        'paid_through_id',
        'profile_name',
        'frequency',
        'start_date',
        'end_date',
        'next_expense_date',
        'amount',
        'tax_amount',
        'total',
        'payment_method',
        'description',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'next_expense_date' => 'date',
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return BelongsTo<ChartOfAccount, $this> */
    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }

    /** @return BelongsTo<ChartOfAccount, $this> */
    public function paidThroughAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'paid_through_id');
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'recurrent_expense_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if this profile is active and due for generation
     */
    public function isDue(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->end_date && $this->next_expense_date->gt($this->end_date)) {
            return false;
        }

        return $this->next_expense_date->lte(now());
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Advance the next_expense_date based on frequency
     */
    public function advanceNextDate(): void
    {
        $this->next_expense_date = match ($this->frequency) {
            'weekly' => $this->next_expense_date->addWeek(),
            'monthly' => $this->next_expense_date->addMonth(),
            'quarterly' => $this->next_expense_date->addMonths(3),
            'yearly' => $this->next_expense_date->addYear(),
        };

        if ($this->end_date && $this->next_expense_date->gt($this->end_date)) {
            $this->status = 'stopped';
        }

        $this->save();
    }
}
