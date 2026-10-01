<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\KeepsTotalsBalanced;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurrentBill extends Model
{
    use BelongsToTenant, HasFactory, KeepsTotalsBalanced, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'vendor_id',
        'profile_name',
        'frequency',
        'start_date',
        'end_date',
        'next_bill_date',
        'subtotal',
        'tax_amount',
        'total',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'next_bill_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return HasMany<RecurrentBillItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(RecurrentBillItem::class);
    }

    /** @return HasMany<Bill, $this> */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class, 'recurrent_bill_id');
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

        if ($this->end_date && $this->next_bill_date->gt($this->end_date)) {
            return false;
        }

        return $this->next_bill_date->lte(now());
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Advance the next_bill_date based on frequency
     */
    public function advanceNextDate(): void
    {
        $this->next_bill_date = match ($this->frequency) {
            'weekly' => $this->next_bill_date->addWeek(),
            'monthly' => $this->next_bill_date->addMonth(),
            'quarterly' => $this->next_bill_date->addMonths(3),
            'yearly' => $this->next_bill_date->addYear(),
        };

        if ($this->end_date && $this->next_bill_date->gt($this->end_date)) {
            $this->status = 'stopped';
        }

        $this->save();
    }

    /**
     * total = subtotal + tax_amount (see KeepsTotalsBalanced, Q2).
     */
    protected function documentTotalParts(): array
    {
        return [['subtotal', 'tax_amount'], []];
    }
}
