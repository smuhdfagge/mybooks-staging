<?php

namespace App\Http\Resources;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Employee */
class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Bank account number and tax ID show only the last 4 characters
        // unless the user runs payroll ("edit payroll"), as on the web page;
        // salary needs "view payroll" (I7).
        $user = $request->user();
        $fullBank = (bool) $user?->can('edit payroll');
        $seesPay = (bool) $user?->can('view payroll');

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->first_name.' '.$this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'gender' => $this->gender,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'postal_code' => $this->postal_code,
            'hire_date' => $this->hire_date?->format('Y-m-d'),
            'termination_date' => $this->termination_date?->format('Y-m-d'),
            'status' => $this->status,
            'salary' => $seesPay ? (float) $this->salary : null,
            'salary_type' => $this->salary_type,
            'bank_name' => $this->bank_name,
            'bank_account_number' => $fullBank ? $this->bank_account_number : self::last4($this->bank_account_number),
            'tax_id' => $fullBank ? $this->tax_id : self::last4($this->tax_id),
            'sensitive_masked' => ! $fullBank,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'designation' => new DesignationResource($this->whenLoaded('designation')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private static function last4(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return '****'.substr($value, -4);
    }
}
