<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use App\Traits\AuditsSensitiveFields;

class SalaryStructure extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, LogsActivity, AuditsSensitiveFields;

    protected static array $sensitiveFields = [
        'basic_salary' => ['type' => 'monetary', 'label' => 'Basic Salary'],
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'version',
        'basic_salary',
        'is_active',
        'effective_from',
        'effective_to',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'version' => 'integer',
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function items()
    {
        return $this->hasMany(SalaryStructureItem::class)->orderBy('sort_order');
    }

    public function allowances()
    {
        return $this->hasMany(SalaryStructureItem::class)->where('type', 'allowance')->orderBy('sort_order');
    }

    public function deductions()
    {
        return $this->hasMany(SalaryStructureItem::class)->where('type', 'deduction')->orderBy('sort_order');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function getTotalAllowancesAttribute()
    {
        return $this->calculateAllowances();
    }

    public function getTotalDeductionsAttribute()
    {
        return $this->calculateDeductions();
    }

    public function getGrossSalaryAttribute()
    {
        return $this->basic_salary + $this->calculateAllowances();
    }

    public function getNetSalaryAttribute()
    {
        return $this->gross_salary - $this->calculateDeductions();
    }

    public function calculateAllowances(): float
    {
        $total = 0;
        foreach ($this->allowances as $item) {
            $total += $item->amount_type === 'percentage'
                ? ($this->basic_salary * $item->amount / 100)
                : $item->amount;
        }
        return round($total, 2);
    }

    public function calculateDeductions(): float
    {
        $total = 0;
        $grossSalary = $this->basic_salary + $this->calculateAllowances();
        foreach ($this->deductions as $item) {
            $total += $item->amount_type === 'percentage'
                ? ($grossSalary * $item->amount / 100)
                : $item->amount;
        }
        return round($total, 2);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function getActiveForEmployee($employeeId)
    {
        $employee = Employee::find($employeeId);
        return $employee?->salaryStructure;
    }

    public function versions()
    {
        return $this->hasMany(SalaryStructureVersion::class)->orderByDesc('version');
    }

    /**
     * Create an immutable snapshot of the current structure state.
     * Called before updates to preserve the pre-change version.
     */
    public function createVersionSnapshot(?string $changeReason = null): SalaryStructureVersion
    {
        return SalaryStructureVersion::create([
            'salary_structure_id' => $this->id,
            'version' => $this->version ?? 1,
            'name' => $this->name,
            'basic_salary' => $this->basic_salary,
            'effective_from' => $this->effective_from,
            'effective_to' => $this->effective_to,
            'items' => $this->items->map(fn ($item) => [
                'type' => $item->type,
                'name' => $item->name,
                'amount_type' => $item->amount_type,
                'amount' => $item->amount,
                'is_taxable' => $item->is_taxable,
                'sort_order' => $item->sort_order,
            ])->toArray(),
            'change_reason' => $changeReason,
            'changed_by' => auth()->id(),
            'created_at' => now(),
        ]);
    }

    /**
     * Build a snapshot array suitable for storing on a payroll record.
     */
    public function toSnapshot(): array
    {
        return [
            'structure_id' => $this->id,
            'version' => $this->version ?? 1,
            'name' => $this->name,
            'basic_salary' => (float) $this->basic_salary,
            'effective_from' => $this->effective_from?->toDateString(),
            'items' => $this->items->map(fn ($item) => [
                'type' => $item->type,
                'name' => $item->name,
                'amount_type' => $item->amount_type,
                'amount' => (float) $item->amount,
                'is_taxable' => $item->is_taxable,
            ])->toArray(),
            'snapshot_at' => now()->toIso8601String(),
        ];
    }
}
