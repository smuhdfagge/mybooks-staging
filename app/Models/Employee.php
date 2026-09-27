<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use App\Traits\AuditsSensitiveFields;

class Employee extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, LogsActivity, AuditsSensitiveFields;

    protected static array $sensitiveFields = [
        'salary' => ['type' => 'monetary', 'label' => 'Base Salary'],
        'bank_account_number' => ['type' => 'masked', 'label' => 'Bank Account Number'],
        'bank_routing_number' => ['type' => 'masked', 'label' => 'Bank Routing Number'],
        'bank_name' => ['type' => 'plain', 'label' => 'Bank Name'],
        'tax_id' => ['type' => 'masked', 'label' => 'Tax ID'],
        'salary_structure_id' => ['type' => 'reference', 'label' => 'Salary Structure', 'model' => SalaryStructure::class],
        'status' => ['type' => 'plain', 'label' => 'Employment Status'],
    ];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'department_id',
        'designation_id',
        'employee_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'gender',
        'marital_status',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'hire_date',
        'termination_date',
        'employment_type',
        'salary',
        'salary_type',
        'salary_structure_id',
        'bank_name',
        'bank_account_number',
        'bank_routing_number',
        'tax_id',
        'emergency_contact_name',
        'emergency_contact_phone',
        'status',
        'notes',
        'photo_path',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'hire_date' => 'date',
        'termination_date' => 'date',
        'salary' => 'encrypted',
        'bank_account_number' => 'encrypted',
        'bank_routing_number' => 'encrypted',
        'tax_id' => 'encrypted',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class);
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function loans()
    {
        return $this->hasMany(EmployeeLoan::class);
    }

    public function activeLoans()
    {
        return $this->hasMany(EmployeeLoan::class)->where('status', EmployeeLoan::STATUS_ACTIVE);
    }

    public function salaryStructure()
    {
        return $this->belongsTo(SalaryStructure::class);
    }

    public function managedDepartments()
    {
        return $this->hasMany(Department::class, 'manager_id');
    }

    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public static function generateEmployeeId($tenantId)
    {
        $lastEmployee = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();
        
        $number = $lastEmployee ? intval(substr($lastEmployee->employee_id, 4)) + 1 : 1;
        return 'EMP-' . str_pad($number, 5, '0', STR_PAD_LEFT);
    }
}
