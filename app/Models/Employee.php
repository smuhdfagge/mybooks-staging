<?php

namespace App\Models;

use App\Support\DocumentNumber;
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
        'rsa_pin' => ['type' => 'masked', 'label' => 'RSA PIN'],
        'nhf_number' => ['type' => 'masked', 'label' => 'NHF Number'],
        'tax_state_id' => ['type' => 'reference', 'label' => 'PAYE State', 'model' => State::class],
        'pension_fund_administrator_id' => ['type' => 'reference', 'label' => 'Pension Fund Administrator', 'model' => PensionFundAdministrator::class],
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
        'tax_state_id',
        'pension_fund_administrator_id',
        'rsa_pin',
        'nhf_number',
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
        'rsa_pin' => 'encrypted',
        'nhf_number' => 'encrypted',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * State whose IRS receives this employee's PAYE.
     *
     * @return BelongsTo<State, $this>
     */
    public function taxState(): BelongsTo
    {
        return $this->belongsTo(State::class, 'tax_state_id');
    }

    /** @return BelongsTo<PensionFundAdministrator, $this> */
    public function pensionFundAdministrator(): BelongsTo
    {
        return $this->belongsTo(PensionFundAdministrator::class);
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
     * Next free EMP-nnnnn number for this business, from the locked
     * sequence (R2). Imported staff with their own IDs don't affect it, and
     * taken numbers are skipped (R1).
     */
    public static function generateEmployeeId($tenantId)
    {
        return DocumentNumber::next((int) $tenantId, static::class, 'employee_id', 'EMP-', 5);
    }
}
