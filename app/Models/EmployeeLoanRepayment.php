<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeLoanRepayment extends Model
{
    protected $fillable = [
        'employee_loan_id',
        'payroll_id',
        'installment_number',
        'amount',
        'principal_portion',
        'interest_portion',
        'remaining_balance',
        'deduction_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'principal_portion' => 'decimal:2',
        'interest_portion' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
        'deduction_date' => 'date',
    ];

    public function loan()
    {
        return $this->belongsTo(EmployeeLoan::class, 'employee_loan_id');
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }
}
