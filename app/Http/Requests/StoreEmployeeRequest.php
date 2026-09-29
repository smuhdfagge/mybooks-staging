<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding an employee, from the web form or the API (finding Q5). Status and
 * salary type use the values the table holds (the API offered "inactive"
 * and "yearly", which the table does not). Email is optional, as on the
 * web; employment type is required, as on the web; date of birth must be
 * in the past. The API may still give its own employee ID.
 */
class StoreEmployeeRequest extends FormRequest
{
    public const STATUSES = ['active', 'on-leave', 'terminated', 'resigned'];

    public function authorize(): bool
    {
        return $this->user()->can('create employees');
    }

    /**
     * The web form shows the next employee ID as a preview only; the number
     * is given on save, so a preview someone else took meanwhile is no error.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->routeIs('api.*')) {
            $this->request->remove('employee_id');
        }
        // A new employee without a type is full-time, the column's default,
        // so API clients that never sent it keep working.
        if ($this->isMethod('post') && ! $this->filled('employment_type')) {
            $this->merge(['employment_type' => 'full-time']);
        }
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'employee_id' => ['nullable', 'string', 'max:50', Rule::unique('employees', 'employee_id')->where('tenant_id', $tenantId)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'designation_id' => ['nullable', Rule::exists('designations', 'id')->where('tenant_id', $tenantId)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female,other'],
            'marital_status' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'hire_date' => ['required', 'date'],
            'employment_type' => ['required', 'in:full-time,part-time,contract,intern'],
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'salary_type' => ['nullable', 'in:monthly,hourly,annual'],
            'salary_structure_id' => ['nullable', Rule::exists('salary_structures', 'id')->where('tenant_id', $tenantId)],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_routing_number' => ['nullable', 'string', 'max:50'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'annual_rent' => ['nullable', 'numeric', 'min:0'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ];
    }
}
