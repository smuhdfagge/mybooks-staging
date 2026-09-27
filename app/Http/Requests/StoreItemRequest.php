<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create items');
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', Rule::exists('item_categories', 'id')->where('tenant_id', $tenantId)],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:product,service'],
            'unit' => ['nullable', 'string', 'max:50'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_taxable' => ['boolean'],
            'track_inventory' => ['boolean'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
