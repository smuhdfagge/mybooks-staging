<?php

namespace App\Http\Requests;

use App\Models\Employee;

/**
 * Changing an employee, from the web form or the API (finding Q5). Header
 * fields may be left out so the API can send only what changes. A
 * termination date must fall after the hire date, the one sent or the one
 * on file. The employee ID is not changed here.
 */
class UpdateEmployeeRequest extends StoreEmployeeRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit employees');
    }

    public function rules(): array
    {
        $rules = parent::rules();

        foreach (['first_name', 'last_name', 'hire_date', 'employment_type'] as $field) {
            $rules[$field] = array_merge(['sometimes'], array_values(array_diff((array) $rules[$field], ['required'])));
        }
        unset($rules['employee_id']);

        $rules['termination_date'] = ['nullable', 'date'];
        $employee = $this->route('employee');
        if ($this->has('hire_date')) {
            $rules['termination_date'][] = 'after:hire_date';
        } elseif ($employee instanceof Employee && $employee->hire_date) {
            $rules['termination_date'][] = 'after:'.$employee->hire_date->toDateString();
        }

        return $rules;
    }
}
