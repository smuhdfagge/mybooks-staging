<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class SalaryStructureItem extends Model
{
    protected $fillable = [
        'salary_structure_id',
        'type',
        'name',
        'amount_type',
        'amount',
        'is_taxable',
        'sort_order',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_taxable' => 'boolean',
    ];

    /** @return BelongsTo<SalaryStructure, $this> */
    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class);
    }

    public function getCalculatedAmountAttribute()
    {
        if ($this->amount_type === 'percentage') {
            return round($this->salaryStructure->basic_salary * $this->amount / 100, 2);
        }
        return $this->amount;
    }
}
