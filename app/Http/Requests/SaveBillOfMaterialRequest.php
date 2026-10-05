<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A bill of materials from the web form (session 14). The rules that need the items are in SaveBillOfMaterial. */
class SaveBillOfMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route's permission middleware decides
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'item_id' => ['required', 'integer', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            'output_quantity' => ['required', 'numeric', 'gt:0'],
            'is_active' => ['nullable', 'boolean'],
            'components' => ['required', 'array', 'min:1'],
            'components.*.item_id' => ['nullable', 'integer', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'components.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'components.*.waste_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'components.*.notes' => ['nullable', 'string', 'max:500'],
            'costs' => ['nullable', 'array'],
            'costs.*.description' => ['nullable', 'string', 'max:255'],
            'costs.*.amount' => ['nullable', 'numeric', 'min:0'],
            'costs.*.account_id' => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
        ];
    }

    public function messages(): array
    {
        return [
            'item_id.required' => 'Choose the item this makes.',
            'item_id.exists' => 'Choose one of your own items.',
            'output_quantity.gt' => 'Enter how many one batch makes.',
            'components.required' => 'Add at least one component.',
            'components.*.item_id.exists' => 'Choose one of your own items.',
            'costs.*.account_id.exists' => 'Choose one of your own accounts.',
        ];
    }
}
