<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;

class PayrollBatch extends Model
{
    use SoftDeletes, BelongsToTenant, LogsActivity;

    const STATUS_DRAFT = 'draft';
    const STATUS_APPROVED = 'approved';
    const STATUS_PROCESSING = 'processing'; // queued to be paid (N7)
    const STATUS_FAILED = 'failed';         // the queued job failed; can be retried
    const STATUS_PAID = 'paid';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'batch_number',
        'pay_period_start',
        'pay_period_end',
        'total_gross',
        'total_deductions',
        'total_net',
        'employee_count',
        'tax_rate',
        'status',
        'failure_reason',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
        'paid_at',
    ];

    protected $casts = [
        'pay_period_start' => 'date',
        'pay_period_end' => 'date',
        'total_gross' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public static function generateNumber($tenantId)
    {
        $last = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();

        $number = $last ? intval(substr($last->batch_number, 4)) + 1 : 1;
        return 'PBN-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    public function recalculateTotals()
    {
        $this->update([
            'total_gross' => $this->payrolls()->sum('gross_salary'),
            'total_deductions' => $this->payrolls()->sum('total_deductions'),
            'total_net' => $this->payrolls()->sum('net_salary'),
            'employee_count' => $this->payrolls()->count(),
        ]);
    }
}
