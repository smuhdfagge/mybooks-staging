<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BudgetLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_id',
        'account_id',
        'jan',
        'feb',
        'mar',
        'apr',
        'may',
        'jun',
        'jul',
        'aug',
        'sep',
        'oct',
        'nov',
        'dec',
        'annual_total',
        'notes',
    ];

    protected $casts = [
        'jan' => 'decimal:2',
        'feb' => 'decimal:2',
        'mar' => 'decimal:2',
        'apr' => 'decimal:2',
        'may' => 'decimal:2',
        'jun' => 'decimal:2',
        'jul' => 'decimal:2',
        'aug' => 'decimal:2',
        'sep' => 'decimal:2',
        'oct' => 'decimal:2',
        'nov' => 'decimal:2',
        'dec' => 'decimal:2',
        'annual_total' => 'decimal:2',
    ];

    /**
     * Get the budget for this line
     */
    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * Get the account for this line
     */
    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    /**
     * Calculate and update the annual total
     */
    public function calculateAnnualTotal(): float
    {
        $total = $this->jan + $this->feb + $this->mar + $this->apr +
                 $this->may + $this->jun + $this->jul + $this->aug +
                 $this->sep + $this->oct + $this->nov + $this->dec;
        
        $this->annual_total = $total;
        
        return $total;
    }

    /**
     * Get the amount for a specific month
     */
    public function getMonthAmount(string $month): float
    {
        $month = strtolower(substr($month, 0, 3));
        return $this->{$month} ?? 0;
    }

    /**
     * Set the amount for a specific month
     */
    public function setMonthAmount(string $month, float $amount): void
    {
        $month = strtolower(substr($month, 0, 3));
        $this->{$month} = $amount;
        $this->calculateAnnualTotal();
    }

    /**
     * Spread an annual amount evenly across all months
     */
    public function spreadAnnualEvenly(float $annualAmount): void
    {
        $monthlyAmount = round($annualAmount / 12, 2);
        $remainder = $annualAmount - ($monthlyAmount * 12);

        foreach (Budget::getMonthColumns() as $col => $name) {
            $this->{$col} = $monthlyAmount;
        }

        // Add remainder to last month
        $this->dec += $remainder;
        $this->calculateAnnualTotal();
    }

    /**
     * Get all monthly amounts as an array
     */
    public function getMonthlyAmountsAttribute(): array
    {
        $amounts = [];
        foreach (Budget::getMonthColumns() as $col => $name) {
            $amounts[$col] = $this->{$col};
        }
        return $amounts;
    }
}
