<?php

namespace App\Http\Requests;

use App\Models\AssemblyOrder;
use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** An assembly order from the web form or the API (session 14). */
class SaveAssemblyOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('adjust inventory');
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'kind' => ['nullable', Rule::in([AssemblyOrder::KIND_BUILD, AssemblyOrder::KIND_BREAKDOWN])],
            'bill_of_materials_id' => ['required', 'integer', Rule::exists('bill_of_materials', 'id')->where('tenant_id', $tenantId)],
            'assembly_date' => ['required', 'date'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'warehouse_id' => Warehouse::rule($tenantId),
            'to_warehouse_id' => Warehouse::rule($tenantId),
            'notes' => ['nullable', 'string', 'max:2000'],
            // After saving: keep as a draft or complete it now.
            'action' => ['nullable', Rule::in(['draft', 'complete'])],
        ];
    }

    public function messages(): array
    {
        return [
            'bill_of_materials_id.required' => 'Choose a bill of materials.',
            'bill_of_materials_id.exists' => 'Choose one of your bills of materials.',
            'quantity.gt' => 'Enter how many to make.',
            'warehouse_id.exists' => 'Choose one of your own warehouses.',
            'to_warehouse_id.exists' => 'Choose one of your own warehouses.',
        ];
    }
}
