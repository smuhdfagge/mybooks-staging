<?php

namespace App\Models;

use App\Traits\AuditsSensitiveFields;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use AuditsSensitiveFields, BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

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
        'annual_rent',
        'emergency_contact_name',
        'emergency_contact_phone',
        'status',
        'notes',
        'photo_path',
    ];

    protected $casts = [
        'annual_rent' => 'decimal:2',
        'date_of_birth' => 'date',
        'hire_date' => 'date',
        'termination_date' => 'date',
        'salary' => 'encrypted',
        'bank_account_number' => 'encrypted',
        'bank_routing_number' => 'encrypted',
        'tax_id' => 'encrypted',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<Designation, $this> */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /** @return HasMany<Leave, $this> */
    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    /** @return HasMany<Payroll, $this> */
    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    /** @return HasMany<EmployeeLoan, $this> */
    public function loans(): HasMany
    {
        return $this->hasMany(EmployeeLoan::class);
    }

    /** @return HasMany<EmployeeLoan, $this> */
    public function activeLoans(): HasMany
    {
        return $this->hasMany(EmployeeLoan::class)->where('status', EmployeeLoan::STATUS_ACTIVE);
    }

    /** @return BelongsTo<SalaryStructure, $this> */
    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class);
    }

    /** @return HasMany<Department, $this> */
    public function managedDepartments(): HasMany
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

    /**
     * Next free EMP-nnnnn number for this business. Imported staff can have
     * their own IDs (e.g. "STAFF-9"), so this looks at the highest EMP- number
     * rather than the latest row, and skips any number already taken.
     */
    public static function generateEmployeeId($tenantId)
    {
        $existing = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('employee_id', 'like', 'EMP-%')
            ->pluck('employee_id');

        $highest = $existing
            ->map(fn ($id) => ctype_digit(substr($id, 4)) ? (int) substr($id, 4) : 0)
            ->max() ?? 0;

        $number = $highest + 1;
        do {
            $candidate = 'EMP-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
            $number++;
        } while (static::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('employee_id', $candidate)->exists());

        return $candidate;
    }
}
